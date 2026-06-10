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
use mteu\Monitoring\Task\MonitoringReportTask;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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
    public function executeDelegatesToDispatcherAndReturnsTrue(): void
    {
        $task = new MonitoringReportTask();

        // With no recipients/webhook configured the dispatcher returns
        // NotificationDecision::None and the task should still succeed.
        self::assertTrue($task->execute());
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
}
