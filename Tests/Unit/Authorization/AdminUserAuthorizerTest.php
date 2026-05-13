<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\Monitoring\Tests\Unit\Authorization;

use mteu\Monitoring\Authorization\AdminUserAuthorizer;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Provider\MiddlewareStatusProviderConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

/**
 * AdminUserAuthorizerTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AdminUserAuthorizer::class)]
final class AdminUserAuthorizerTest extends Framework\TestCase
{
    private Context&MockObject $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function isActiveReflectsEnabledFlagProvider(): \Generator
    {
        yield 'enabled' => [true];
        yield 'disabled' => [false];
    }

    #[Test]
    #[DataProvider('isActiveReflectsEnabledFlagProvider')]
    public function isActiveReflectsConfiguredEnabledFlag(bool $enabled): void
    {
        $authorizer = $this->createAuthorizer($enabled);

        self::assertSame($enabled, $authorizer->isActive());
    }

    /**
     * @return \Generator<string, array{bool, bool, bool}>
     */
    public static function isAuthorizedTruthTable(): \Generator
    {
        yield 'admin + logged in -> authorized' => [true, true, true];
        yield 'admin + logged out -> rejected' => [true, false, false];
        yield 'non-admin + logged in -> rejected' => [false, true, false];
        yield 'non-admin + logged out -> rejected' => [false, false, false];
    }

    #[Test]
    #[DataProvider('isAuthorizedTruthTable')]
    public function isAuthorizedRequiresBothIsAdminAndIsLoggedInAspects(
        bool $isAdmin,
        bool $isLoggedIn,
        bool $expected,
    ): void {
        // willReturnCallback must not throw checked exceptions, so do not
        // self::fail / self::assertSame inside this closure — narrow on the
        // aspect/property names and let the outer assertSame catch any drift.
        // A regression that queried the wrong aspect or property would land
        // on the `default => false` arm here and break the truth-table rows
        // that expect `true`.
        $this->context
            ->method('getPropertyFromAspect')
            ->willReturnCallback(
                static fn(string $aspect, string $property): bool =>
                    $aspect === 'backend.user'
                        ? match ($property) {
                            'isAdmin'    => $isAdmin,
                            'isLoggedIn' => $isLoggedIn,
                            default      => false,
                        }
                : false,
            );

        $authorizer = $this->createAuthorizer();

        self::assertSame($expected, $authorizer->isAuthorized($this->createRequest()));
    }

    #[Test]
    public function isAuthorizedReturnsFalseWhenBackendUserAspectIsMissing(): void
    {
        $this->context
            ->method('getPropertyFromAspect')
            ->willThrowException(new AspectNotFoundException('…', 1700000000));

        $authorizer = $this->createAuthorizer();

        self::assertFalse($authorizer->isAuthorized($this->createRequest()));
    }

    private function createAuthorizer(bool $enabled = true): AdminUserAuthorizer
    {
        $configuration = new MonitoringConfiguration(
            tokenAuthorizerConfiguration: new TokenAuthorizerConfiguration(),
            adminUserAuthorizerConfiguration: new AdminUserAuthorizerConfiguration(
                enabled: $enabled,
                priority: -10,
            ),
            providerConfiguration: new MiddlewareStatusProviderConfiguration(),
            endpoint: '/monitor/health',
        );

        return new AdminUserAuthorizer($this->context, $configuration);
    }

    private function createRequest(): ServerRequest
    {
        return new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET');
    }
}
