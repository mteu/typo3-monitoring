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

namespace mteu\Monitoring\Tests\Functional\Task;

use EliasHaeussler\PHPUnitAttributes\Attribute\RequiresPackage;
use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Reporter\ReportDispatcher;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportThreshold;
use mteu\Monitoring\Task\MonitoringReportTask;
use mteu\Monitoring\Tests\Functional\Fixtures\ConfigurableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\RecordingReporter;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Service\TaskService;

/**
 * MonitoringReportTaskTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringReportTask::class)]
final class MonitoringReportTaskTest extends MonitoringFunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'scheduler',
    ];

    #[Test]
    public function executeReturnsFalseWhenNotificationDueButNoReporterDelivers(): void
    {
        $this->registerDispatcher(
            [new ConfigurableProvider('alpha', healthy: false)],
            [new RecordingReporter(ReportThreshold::Always, active: false)],
        );

        self::assertFalse((new MonitoringReportTask())->execute());
    }

    #[Test]
    public function executeReturnsTrueWhenAnActiveReporterDelivers(): void
    {
        $this->registerDispatcher(
            [new ConfigurableProvider('alpha', healthy: false)],
            [new RecordingReporter(ReportThreshold::Always)],
        );

        self::assertTrue((new MonitoringReportTask())->execute());
    }

    /**
     * Proves the task is actually discoverable by the Scheduler's own registry
     * (i.e. registered under the key the scheduler reads), not merely that we
     * wrote some array key ourselves.
     */
    #[Test]
    #[RequiresPackage('typo3/cms-scheduler', '>= 14')]
    public function taskIsDiscoverableByTheScheduler(): void
    {
        $taskService = $this->getTaskService();
        self::assertTrue($taskService->isTaskTypeRegistered(MonitoringReportTask::class));
    }

    /**
     * v13 counterpart: the public discovery API is {@see TaskService::getAvailableTaskTypes()}
     * (made protected in v14, hence the reflection call to keep static analysis against v14 happy).
     */
    #[Test]
    #[RequiresPackage('typo3/cms-scheduler', '< 14')]
    public function taskIsDiscoverableByTheSchedulerOnV13(): void
    {
        $taskService = $this->getTaskService();

        // v13's getAvailableTaskTypes() localizes task titles via $GLOBALS['LANG']
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $taskTypes = (new \ReflectionMethod($taskService, 'getAvailableTaskTypes'))->invoke($taskService);

        self::assertIsArray($taskTypes);
        self::assertArrayHasKey(MonitoringReportTask::class, $taskTypes);
    }

    private function getTaskService(): TaskService
    {

        $taskService = $this->get(TaskService::class);
        self::assertInstanceOf(TaskService::class, $taskService);

        return $taskService;
    }

    /**
     * @param list<ConfigurableProvider> $providers
     * @param list<Reporter> $reporters
     */
    private function registerDispatcher(array $providers, array $reporters): void
    {
        $this->get(CacheManager::class)->getCache('typo3_monitoring_notification')->flush();

        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp(1_000)));

        $dispatcher = new ReportDispatcher(
            $providers,
            $reporters,
            $this->get(MonitoringExecutionHandler::class),
            $this->get(NotificationStateCacheManager::class),
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                new EmailReporterConfiguration(),
                new ReportDispatcherConfiguration(),
            ),
            $context,
            new NullLogger(),
        );

        GeneralUtility::addInstance(ReportDispatcher::class, $dispatcher);
    }
}
