<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Configuration;

use mteu\Monitoring as Src;
use mteu\Monitoring\Configuration\MonitoringConfigurationFactory;
use mteu\TypedExtConf\Mapper\ConfigurationMapper;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

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
    private ConfigurationMapper&MockObject $configurationMapper;

    protected function setUp(): void
    {
        $this->configurationMapper = $this->createMock(ConfigurationMapper::class);
        $this->subject = new MonitoringConfigurationFactory($this->configurationMapper);
    }

    #[Test]
    public function testCreateDelegatesToConfigurationMapper(): void
    {
        $expectedConfiguration = new Src\Configuration\MonitoringConfiguration(
            endpoint: '/monitor/health',
            tokenAuthorizerConfiguration: new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
                enabled: true,
                priority: 10,
                secret: 'test-secret',
                authHeaderName: 'X-Auth',
            ),
            adminUserAuthorizerConfiguration: new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(
                enabled: false,
                priority: -10,
            ),
        );

        $this->configurationMapper->expects(self::once())
            ->method('map')
            ->with(Src\Configuration\MonitoringConfiguration::class)
            ->willReturn($expectedConfiguration);

        $result = $this->subject->create();

        self::assertSame('/monitor/health', $result->endpoint);
        self::assertTrue($result->tokenAuthorizerConfiguration->isEnabled());
        self::assertSame('test-secret', $result->tokenAuthorizerConfiguration->secret);
        self::assertSame('X-Auth', $result->tokenAuthorizerConfiguration->authHeaderName);
        self::assertSame(10, $result->tokenAuthorizerConfiguration->getPriority());
        self::assertFalse($result->adminUserAuthorizerConfiguration->isEnabled());
        self::assertSame(-10, $result->adminUserAuthorizerConfiguration->getPriority());
    }
}
