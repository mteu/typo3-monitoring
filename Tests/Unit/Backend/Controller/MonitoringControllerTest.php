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

use mteu\Monitoring\Backend\Controller\MonitoringController;
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Status;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\FormProtection\AbstractFormProtection;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;

/**
 * MonitoringControllerTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringController::class)]
final class MonitoringControllerTest extends Framework\TestCase
{
    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function inactiveProviderIsNeitherExecutedNorAssignedAHealthValue(): void
    {
        $inactiveProvider = $this->createMock(MonitoringProvider::class);
        $inactiveProvider->method('getName')->willReturn('Inactive');
        $inactiveProvider->method('getDescription')->willReturn('disabled provider');
        $inactiveProvider->method('isEnabled')->willReturn(false);
        $inactiveProvider->method('isActive')->willReturn(false);

        $inactiveProvider->expects(self::never())->method('execute');

        $variables = $this->buildProviderTemplateVariables([$inactiveProvider]);

        self::assertArrayHasKey($inactiveProvider::class, $variables);
        $entry = $variables[$inactiveProvider::class];

        self::assertFalse($entry['isActive']);

        // "Not checked" must not masquerade as a health verdict.
        self::assertArrayNotHasKey('isHealthy', $entry);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function activeProviderIsExecutedOnceAndReportsItsHealth(): void
    {
        $activeProvider = $this->createMock(MonitoringProvider::class);
        $activeProvider->method('getName')->willReturn('Active');
        $activeProvider->method('getDescription')->willReturn('enabled provider');
        $activeProvider->method('isEnabled')->willReturn(true);
        $activeProvider->method('isActive')->willReturn(true);
        $activeProvider
            ->expects(self::once())
            ->method('execute')
            ->willReturn(new MonitoringResult('Active', Status::Healthy));

        $variables = $this->buildProviderTemplateVariables([$activeProvider]);

        $entry = $variables[$activeProvider::class];

        self::assertTrue($entry['isActive']);
        self::assertArrayHasKey('isHealthy', $entry);
        self::assertTrue($entry['isHealthy']);
    }

    #[Test]
    public function unhealthyCountIgnoresInactiveProvidersAndDegradedCountIsSeparate(): void
    {
        // Shape mirrors buildProviderTemplateVariables() output. A degraded provider
        // stays isHealthy (degraded is not an outage), so it must not inflate the
        // unhealthy tally; an inactive provider never ran and must count for neither.
        $providers = [
            'active-unhealthy' => ['isActive' => true, 'isHealthy' => false, 'status' => 'unhealthy'],
            'active-degraded' => ['isActive' => true, 'isHealthy' => true, 'status' => 'degraded'],
            'active-healthy' => ['isActive' => true, 'isHealthy' => true, 'status' => 'healthy'],
            'inactive' => ['isActive' => false],
        ];

        self::assertSame(1, $this->countProviders('countUnhealthyProviders', $providers));
        self::assertSame(1, $this->countProviders('countDegradedProviders', $providers));
    }

    /**
     * @param array<string, array<string, mixed>> $providers
     */
    private function countProviders(string $method, array $providers): int
    {
        $reflection = new \ReflectionClass(MonitoringController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $result = $reflection->getMethod($method)->invoke($controller, $providers);
        self::assertIsInt($result);

        return $result;
    }

    /**
     * Invokes the private buildProviderTemplateVariables() on a controller
     * created without its constructor, injecting only the four properties the
     * method actually reads.
     *
     * @param list<MonitoringProvider> $providers
     * @return array<class-string<MonitoringProvider>, array<string, mixed>>
     */
    private function buildProviderTemplateVariables(array $providers): array
    {
        $executionHandler = new MonitoringExecutionHandler(
            new MonitoringCacheManager($this->createMock(CacheManager::class)),
            new NullLogger(),
        );

        $formProtectionFactory = $this->createMock(FormProtectionFactory::class);
        $formProtectionFactory
            ->method('createFromRequest')
            ->willReturn($this->createMock(AbstractFormProtection::class));

        $reflection = new \ReflectionClass(MonitoringController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        foreach ([
            'monitoringProviders' => $providers,
            'executionHandler' => $executionHandler,
            'cacheManager' => new MonitoringCacheManager($this->createMock(CacheManager::class)),
            'formProtectionFactory' => $formProtectionFactory,
        ] as $property => $value) {
            $reflection->getProperty($property)->setValue($controller, $value);
        }

        $method = $reflection->getMethod('buildProviderTemplateVariables');

        $result = $method->invoke($controller, $this->createMock(ServerRequestInterface::class));
        self::assertIsArray($result);

        /** @var array<class-string<MonitoringProvider>, array<string, mixed>> $result */
        return $result;
    }
}
