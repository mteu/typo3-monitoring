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

namespace mteu\Monitoring\Tests\Unit\Backend\Controller;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Backend\Controller\AuthorizerController;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * AuthorizerControllerTest.
 *
 * @todo: Tests own logic but bypass constructors. Fixing properly needs refactoring the controllers.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AuthorizerController::class)]
final class AuthorizerControllerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private const string ENCRYPTION_KEY = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        // HashService::hmac() salts with the encryption key; pin it so the generated auth token is deterministic.
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['encryptionKey' => self::ENCRYPTION_KEY]];
    }

    #[Test]
    public function collectAuthorizerStatusesReturnsEmptyArrayWhenNoAuthorizersAreRegistered(): void
    {
        self::assertSame([], $this->collectAuthorizerStatuses([]));
    }

    #[Test]
    public function collectAuthorizerStatusesReportsEachAuthorizersActiveStateAndPriority(): void
    {
        // Two distinct implementations: anonymous classes declared separately
        // are separate types, so they keep distinct ::class keys and static priorities.
        $activeAuthorizer = new class () implements Authorizer {
            public function isActive(): bool
            {
                return true;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                return true;
            }

            public function getDescription(): string
            {
                return 'Description text.';
            }

            public static function getPriority(): int
            {
                return 30;
            }
        };

        $inactiveAuthorizer = new class () implements Authorizer {
            public function isActive(): bool
            {
                return false;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                return false;
            }

            public function getDescription(): string
            {
                return 'Description text';
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };

        $statuses = $this->collectAuthorizerStatuses([$activeAuthorizer, $inactiveAuthorizer]);

        self::assertTrue($statuses[$activeAuthorizer::class]['isActive']);
        self::assertSame(30, $statuses[$activeAuthorizer::class]['priority']);
        self::assertFalse($statuses[$inactiveAuthorizer::class]['isActive']);
        self::assertSame(10, $statuses[$inactiveAuthorizer::class]['priority']);
    }

    #[Test]
    public function authTokenIsExposedOnlyWhenTheTokenAuthorizerIsEnabledWithASecret(): void
    {
        $config = $this->configurationWithToken(enabled: true, secret: 's3cret', authHeaderName: 'X-Auth');
        $hashService = new HashService();

        $variables = $this->buildAuthorizerTemplateVariables(
            [new TokenAuthorizer($config, $hashService)],
            $config,
            $hashService,
        );

        $token = $variables[TokenAuthorizer::class];
        self::assertSame('X-Auth', $token['authHeaderName']);
        // The exposed token is the HMAC of the endpoint signed with the secret.
        self::assertSame($hashService->hmac('/monitor/health', 's3cret'), $token['authToken']);
        self::assertArrayNotHasKey('emptySecret', $token);
    }

    #[Test]
    public function emptySecretIsFlaggedAndNoTokenIsLeakedToTheTemplate(): void
    {
        $config = $this->configurationWithToken(enabled: true, secret: '', authHeaderName: 'X-Auth');
        $hashService = new HashService();

        $variables = $this->buildAuthorizerTemplateVariables(
            [new TokenAuthorizer($config, $hashService)],
            $config,
            $hashService,
        );

        $token = $variables[TokenAuthorizer::class];
        self::assertTrue($token['emptySecret']);
        self::assertTrue($token['isEnabled']);
        self::assertArrayNotHasKey('authToken', $token);
        self::assertArrayNotHasKey('authHeaderName', $token);
    }

    #[Test]
    public function configuredSecretIsNotExposedWhileTheTokenAuthorizerIsDisabled(): void
    {
        $config = $this->configurationWithToken(enabled: false, secret: 's3cret', authHeaderName: 'X-Auth');
        $hashService = new HashService();

        $variables = $this->buildAuthorizerTemplateVariables(
            [new TokenAuthorizer($config, $hashService)],
            $config,
            $hashService,
        );

        $token = $variables[TokenAuthorizer::class];
        self::assertArrayNotHasKey('authToken', $token);
        self::assertArrayNotHasKey('authHeaderName', $token);
        self::assertArrayNotHasKey('emptySecret', $token);
    }

    private function configurationWithToken(bool $enabled, string $secret, string $authHeaderName): MonitoringConfiguration
    {
        return new MonitoringConfiguration(
            new TokenAuthorizerConfiguration(enabled: $enabled, secret: $secret, authHeaderName: $authHeaderName),
            new AdminUserAuthorizerConfiguration(),
            new EmailReporterConfiguration(),
            new ReportDispatcherConfiguration(),
            '/monitor/health',
        );
    }

    /**
     * Invokes the private buildAuthorizerTemplateVariables() on a controller
     * created without its constructor, injecting only the properties it reads.
     *
     * @param list<Authorizer> $authorizers
     * @return array<class-string, array<string, mixed>>
     */
    private function buildAuthorizerTemplateVariables(
        array $authorizers,
        MonitoringConfiguration $configuration,
        HashService $hashService,
    ): array {
        $reflection = new \ReflectionClass(AuthorizerController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('authorizers')->setValue($controller, $authorizers);
        $reflection->getProperty('monitoringConfiguration')->setValue($controller, $configuration);
        $reflection->getProperty('hashService')->setValue($controller, $hashService);

        $result = $reflection->getMethod('buildAuthorizerTemplateVariables')->invoke($controller);
        self::assertIsArray($result);

        /** @var array<class-string, array<string, mixed>> $result */
        return $result;
    }

    /**
     * Invokes the private collectAuthorizerStatuses() on a controller created
     * without its constructor, injecting only the authorizers it reads.
     *
     * @param list<Authorizer> $authorizers
     * @return array<class-string, array{isActive: bool, priority: int}>
     */
    private function collectAuthorizerStatuses(array $authorizers): array
    {
        $reflection = new \ReflectionClass(AuthorizerController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('authorizers')->setValue($controller, $authorizers);

        $result = $reflection->getMethod('collectAuthorizerStatuses')->invoke($controller);
        self::assertIsArray($result);

        /** @var array<class-string, array{isActive: bool, priority: int}> $result */
        return $result;
    }
}
