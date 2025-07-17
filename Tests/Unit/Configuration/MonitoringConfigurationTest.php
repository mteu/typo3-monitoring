<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Configuration;

use mteu\Monitoring as Src;
use mteu\Monitoring\Configuration\MonitoringConfigurationFactory;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * MonitoringConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\MonitoringConfiguration::class)]
#[Framework\Attributes\CoversClass(Src\Configuration\MonitoringConfigurationFactory::class)]
final class MonitoringConfigurationTest extends Framework\TestCase
{
    private MonitoringConfigurationFactory $subject;

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     * @throws Exception
     */
    #[Test]
    #[Framework\Attributes\DataProvider('provideExtensionConfiguration')]
    public function testFromExtensionConfigurationWithValidData(array $config, array $expected): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('monitoring')
            ->willReturn($config);

        $this->subject = new Src\Configuration\MonitoringConfigurationFactory($extensionConfiguration);
        $result = $this->subject->create();

        self::assertSame($expected['endpoint'], $result->endpoint);
        self::assertSame($expected['tokenAuthorizerEnabled'], $result->tokenAuthorizerEnabled);
        self::assertSame($expected['tokenAuthorizerSecret'], $result->tokenAuthorizerSecret);
        self::assertSame($expected['tokenAuthorizerAuthHeaderName'], $result->tokenAuthorizerAuthHeaderName);
        self::assertSame($expected['tokenAuthorizerPriority'], $result->tokenAuthorizerPriority);
        self::assertSame($expected['adminUserAuthorizerEnabled'], $result->adminUserAuthorizerEnabled);
        self::assertSame($expected['adminUserAuthorizerPriority'], $result->adminUserAuthorizerPriority);
    }

    /**
     * @return \Generator<string, array{config: array<string, mixed>, expected: array<string, mixed>}>
     */
    public static function provideExtensionConfiguration(): \Generator
    {
        yield 'typo3 configuration if set from backend with string booleans' => [
            'config' => [
                'api' => [
                    'endpoint' => '/monitor/health',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => '1',
                        'priority' => '-10',
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                        'enabled' => '1',
                        'priority' => '10',
                        'secret' => 'foo',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/monitor/health',
                'tokenAuthorizerEnabled' => true,
                'tokenAuthorizerSecret' => 'foo',
                'tokenAuthorizerAuthHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                'tokenAuthorizerPriority' => 10,
                'adminUserAuthorizerEnabled' => true,
                'adminUserAuthorizerPriority' => -10,
            ],
        ];
        yield 'realistic configuration with mixed types' => [
            'config' => [
                'api' => [
                    'endpoint' => '/monitor/health',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => 1,
                        'priority' => '-10.0',
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                        'enabled' => 'true',
                        'priority' => 0,
                        'secret' => 123456789,
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/monitor/health',
                'tokenAuthorizerEnabled' => true,
                'tokenAuthorizerSecret' => '123456789',
                'tokenAuthorizerAuthHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                'tokenAuthorizerPriority' => 0,
                'adminUserAuthorizerEnabled' => true,
                'adminUserAuthorizerPriority' => -10,
            ],
        ];
        yield 'configuration with boolean false values' => [
            'config' => [
                'api' => [
                    'endpoint' => '/api/status',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => false,
                        'priority' => '100',
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'Authorization',
                        'enabled' => 0,
                        'priority' => '50',
                        'secret' => 'secret-key',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/api/status',
                'tokenAuthorizerEnabled' => false,
                'tokenAuthorizerSecret' => 'secret-key',
                'tokenAuthorizerAuthHeaderName' => 'Authorization',
                'tokenAuthorizerPriority' => 50,
                'adminUserAuthorizerEnabled' => false,
                'adminUserAuthorizerPriority' => 100,
            ],
        ];
        yield 'configuration with string false values' => [
            'config' => [
                'api' => [
                    'endpoint' => '/check',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => 'false',
                        'priority' => '0',
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-Token',
                        'enabled' => '0',
                        'priority' => '1',
                        'secret' => '',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/check',
                'tokenAuthorizerEnabled' => false,
                'tokenAuthorizerSecret' => '',
                'tokenAuthorizerAuthHeaderName' => 'X-Token',
                'tokenAuthorizerPriority' => 1,
                'adminUserAuthorizerEnabled' => false,
                'adminUserAuthorizerPriority' => 0,
            ],
        ];
        yield 'configuration with invalid boolean values defaults to false' => [
            'config' => [
                'api' => [
                    'endpoint' => '/health',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => 'invalid',
                        'priority' => '5',
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-Auth',
                        'enabled' => null,
                        'priority' => '15',
                        'secret' => 'test',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/health',
                'tokenAuthorizerEnabled' => false,
                'tokenAuthorizerSecret' => 'test',
                'tokenAuthorizerAuthHeaderName' => 'X-Auth',
                'tokenAuthorizerPriority' => 15,
                'adminUserAuthorizerEnabled' => false,
                'adminUserAuthorizerPriority' => 5,
            ],
        ];
        yield 'configuration with float priority values' => [
            'config' => [
                'api' => [
                    'endpoint' => '/status',
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => true,
                        'priority' => 99.9,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'Bearer',
                        'enabled' => 'yes',
                        'priority' => -5.5,
                        'secret' => 'bearer-secret',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/status',
                'tokenAuthorizerEnabled' => true,
                'tokenAuthorizerSecret' => 'bearer-secret',
                'tokenAuthorizerAuthHeaderName' => 'Bearer',
                'tokenAuthorizerPriority' => -5,
                'adminUserAuthorizerEnabled' => true,
                'adminUserAuthorizerPriority' => 99,
            ],
        ];
    }
}
