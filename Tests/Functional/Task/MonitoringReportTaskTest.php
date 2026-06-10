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

use mteu\Monitoring\Task\MonitoringReportTask;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function taskIsRegisteredWithScheduler(): void
    {
        $vars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        self::assertIsArray($vars);

        $scheduler = $vars['SCHEDULER'] ?? null;
        self::assertIsArray($scheduler);

        $tasks = $scheduler['tasks'] ?? null;
        self::assertIsArray($tasks);

        self::assertArrayHasKey(MonitoringReportTask::class, $tasks);
    }
}
