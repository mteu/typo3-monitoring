<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Configuration;

use mteu\Monitoring as Src;
use mteu\Monitoring\Configuration\MonitoringConfigurationFactory;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
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
    private ExtensionConfiguration $extensionConfiguration;

    protected function setUp(): void
    {
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->subject = new MonitoringConfigurationFactory($this->extensionConfiguration);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[Framework\Attributes\DataProvider('provideExtensionConfiguration')]
    public function testFromExtensionConfigurationWithValidData(array $config, array $expected): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('monitoring')
            ->willReturn($config);

        $result = $this->subject->create();

        self::assertSame($expected['endpoint'], $result->endpoint, 'Endpoint should match expected value');

        self::assertSame($expected['tokenAuthorizer']['enabled'], $result->tokenAuthorizerConfiguration->isEnabled(), 'Token authorizer enabled status should match');
        self::assertSame($expected['tokenAuthorizer']['secret'], $result->tokenAuthorizerConfiguration->secret, 'Token authorizer secret should match');
        self::assertSame($expected['tokenAuthorizer']['authHeaderName'], $result->tokenAuthorizerConfiguration->authHeaderName, 'Token authorizer auth header name should match');
        self::assertSame($expected['tokenAuthorizer']['priority'], $result->tokenAuthorizerConfiguration->getPriority(), 'Token authorizer priority should match');

        self::assertSame($expected['adminUserAuthorizer']['enabled'], $result->adminUserAuthorizerConfiguration->isEnabled(), 'Admin user authorizer enabled status should match');
        self::assertSame($expected['adminUserAuthorizer']['priority'], $result->adminUserAuthorizerConfiguration->getPriority(), 'Admin user authorizer priority should match');
    }

    /**
     * @return \Generator<string, array{config: array<string, mixed>, expected: array{endpoint: string, tokenAuthorizer: array{enabled: bool, secret: string, authHeaderName: string, priority: int}, adminUserAuthorizer: array{enabled: bool, priority: int}}}>
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
                        'priority' => -10,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                        'enabled' => '1',
                        'priority' => 10,
                        'secret' => 'foo',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/monitor/health',
                'tokenAuthorizer' => [
                    'enabled' => true,
                    'secret' => 'foo',
                    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                    'priority' => 10,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => true,
                    'priority' => -10,
                ],
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
                        'priority' => -10,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                        'enabled' => 'true',
                        'priority' => 0,
                        'secret' => '123456789',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/monitor/health',
                'tokenAuthorizer' => [
                    'enabled' => true,
                    'secret' => '123456789',
                    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                    'priority' => 0,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => true,
                    'priority' => -10,
                ],
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
                        'priority' => 100,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'Authorization',
                        'enabled' => 0,
                        'priority' => 50,
                        'secret' => 'secret-key',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/api/status',
                'tokenAuthorizer' => [
                    'enabled' => false,
                    'secret' => 'secret-key',
                    'authHeaderName' => 'Authorization',
                    'priority' => 50,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => false,
                    'priority' => 100,
                ],
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
                        'priority' => 0,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-Token',
                        'enabled' => '0',
                        'priority' => 1,
                        'secret' => '',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/check',
                'tokenAuthorizer' => [
                    'enabled' => false,
                    'secret' => '',
                    'authHeaderName' => 'X-Token',
                    'priority' => 1,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => false,
                    'priority' => 0,
                ],
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
                        'priority' => 5,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'X-Auth',
                        'enabled' => null,
                        'priority' => 15,
                        'secret' => 'test',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/health',
                'tokenAuthorizer' => [
                    'enabled' => false,
                    'secret' => 'test',
                    'authHeaderName' => 'X-Auth',
                    'priority' => 15,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => false,
                    'priority' => 5,
                ],
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
                        'priority' => 99,
                    ],
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'authHeaderName' => 'Bearer',
                        'enabled' => 'yes',
                        'priority' => -5,
                        'secret' => 'bearer-secret',
                    ],
                ],
            ],
            'expected' => [
                'endpoint' => '/status',
                'tokenAuthorizer' => [
                    'enabled' => true,
                    'secret' => 'bearer-secret',
                    'authHeaderName' => 'Bearer',
                    'priority' => -5,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => true,
                    'priority' => 99,
                ],
            ],
        ];
        yield 'minimal configuration with defaults' => [
            'config' => [
                'api' => [
                    'endpoint' => '/health',
                ],
            ],
            'expected' => [
                'endpoint' => '/health',
                'tokenAuthorizer' => [
                    'enabled' => false,
                    'secret' => '',
                    'authHeaderName' => '',
                    'priority' => 10,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => false,
                    'priority' => -10,
                ],
            ],
        ];
        yield 'empty configuration array' => [
            'config' => [],
            'expected' => [
                'endpoint' => '',
                'tokenAuthorizer' => [
                    'enabled' => false,
                    'secret' => '',
                    'authHeaderName' => '',
                    'priority' => 10,
                ],
                'adminUserAuthorizer' => [
                    'enabled' => false,
                    'priority' => -10,
                ],
            ],
        ];
    }

    #[Test]
    public function testCreateWithNoExtensionConfiguration(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('monitoring')
            ->willReturn([]);

        $result = $this->subject->create();

        self::assertSame('', $result->endpoint);
        self::assertFalse($result->tokenAuthorizerConfiguration->isEnabled());
        self::assertSame('', $result->tokenAuthorizerConfiguration->secret);
        self::assertSame('', $result->tokenAuthorizerConfiguration->authHeaderName);
        self::assertSame(10, $result->tokenAuthorizerConfiguration->getPriority());
        self::assertFalse($result->adminUserAuthorizerConfiguration->isEnabled());
        self::assertSame(-10, $result->adminUserAuthorizerConfiguration->getPriority());
    }

    #[Test]
    public function testCreateWithNullConfiguration(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('monitoring')
            ->willReturn(null);

        $result = $this->subject->create();

        self::assertSame('', $result->endpoint);
        self::assertFalse($result->tokenAuthorizerConfiguration->isEnabled());
        self::assertSame('', $result->tokenAuthorizerConfiguration->secret);
        self::assertSame('', $result->tokenAuthorizerConfiguration->authHeaderName);
        self::assertSame(10, $result->tokenAuthorizerConfiguration->getPriority());
        self::assertFalse($result->adminUserAuthorizerConfiguration->isEnabled());
        self::assertSame(-10, $result->adminUserAuthorizerConfiguration->getPriority());
    }
}
