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

namespace mteu\Monitoring\Tests\Unit\Configuration\Authorizer;

use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * TokenAuthorizerConfigurationTest.
 *
 * MonitoringConfiguration is built for every frontend request, so an incomplete
 * TokenAuthorizer block must map to inactive defaults instead of throwing.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(TokenAuthorizerConfiguration::class)]
final class TokenAuthorizerConfigurationTest extends Framework\TestCase
{
    private const string TOKEN_AUTHORIZER_KEY = 'mteu\\Monitoring\\Authorization\\TokenAuthorizer';

    /**
     * @return \Generator<string, array{array<string, mixed>}>
     */
    public static function incompleteExtensionConfigurationProvider(): \Generator
    {
        yield 'no authorizer block' => [
            ['api' => ['endpoint' => '/monitor/health']],
        ];
        yield 'authorizer block without token authorizer' => [
            ['authorizer' => ['mteu\\Monitoring\\Authorization\\AdminUserAuthorizer' => ['enabled' => '1']]],
        ];
        yield 'token authorizer block with priority only' => [
            ['authorizer' => [self::TOKEN_AUTHORIZER_KEY => ['priority' => '5']]],
        ];
        yield 'token authorizer block without secret' => [
            ['authorizer' => [self::TOKEN_AUTHORIZER_KEY => ['enabled' => '1']]],
        ];
    }

    /**
     * @param array<string, mixed> $extensionConfiguration
     */
    #[Test]
    #[DataProvider('incompleteExtensionConfigurationProvider')]
    public function incompleteBlockMapsToInactiveTokenAuthorizerInsteadOfThrowing(array $extensionConfiguration): void
    {
        $configuration = $this->mapMonitoringConfiguration($extensionConfiguration);

        $tokenAuthorizer = new TokenAuthorizer($configuration, new HashService());

        self::assertSame('', $configuration->tokenAuthorizerConfiguration->secret);
        self::assertFalse($tokenAuthorizer->isActive());
    }

    #[Test]
    public function completeBlockIsMappedAsConfigured(): void
    {
        $configuration = $this->mapMonitoringConfiguration([
            'authorizer' => [
                self::TOKEN_AUTHORIZER_KEY => [
                    'enabled' => '1',
                    'secret' => 'configured-secret',
                    'priority' => '20',
                    'authHeaderName' => 'X-Custom-Auth',
                ],
            ],
        ]);

        self::assertEquals(
            new TokenAuthorizerConfiguration(
                enabled: true,
                priority: 20,
                secret: 'configured-secret',
                authHeaderName: 'X-Custom-Auth',
            ),
            $configuration->tokenAuthorizerConfiguration,
        );
    }

    /**
     * @param array<string, mixed> $extensionConfiguration
     */
    private function mapMonitoringConfiguration(array $extensionConfiguration): MonitoringConfiguration
    {
        $extensionConfigurationReader = self::createStub(ExtensionConfiguration::class);
        $extensionConfigurationReader->method('get')->willReturn($extensionConfiguration);

        return (new TypedExtensionConfigurationProvider($extensionConfigurationReader, new TreeMapperFactory()))
            ->get(MonitoringConfiguration::class);
    }
}
