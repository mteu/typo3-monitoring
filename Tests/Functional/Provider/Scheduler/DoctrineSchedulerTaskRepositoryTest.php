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

namespace mteu\Monitoring\Tests\Functional\Provider\Scheduler;

use mteu\Monitoring\Provider\Scheduler\DoctrineSchedulerTaskRepository;
use mteu\Monitoring\Provider\Scheduler\SchedulerTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * DoctrineSchedulerTaskRepositoryTest.
 *
 * Exercises the hand-written query constraints against a real database:
 * which rows count as failed, overdue or running, and how rows are mapped
 * to tasks. Doctrine itself is not under test here.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(DoctrineSchedulerTaskRepository::class)]
final class DoctrineSchedulerTaskRepositoryTest extends FunctionalTestCase
{
    /**
     * Reference timestamp used as the "overdue before" boundary in tests.
     */
    private const int OVERDUE_BEFORE = 1_700_000_000;

    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'monitoring',
        'typed_extconf',
    ];

    private DoctrineSchedulerTaskRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DoctrineSchedulerTaskRepository($this->getConnectionPool());
    }

    #[Test]
    public function countFailedTasksCountsOnlyEnabledTasksWithARecordedFailure(): void
    {
        $this->createTask();
        $this->createTask(['lastexecution_failure' => 'serialized exception']);
        $this->createTask(['lastexecution_failure' => 'serialized exception', 'disable' => 1]);
        $this->createTask(['lastexecution_failure' => 'serialized exception', 'deleted' => 1]);

        self::assertSame(1, $this->repository->countFailedTasks());
    }

    #[Test]
    public function countOverdueTasksIgnoresManualAndFutureTasks(): void
    {
        // Manually triggered tasks carry no next execution time.
        $this->createTask(['nextexecution' => 0]);
        $this->createTask(['nextexecution' => self::OVERDUE_BEFORE + 3_600]);
        $this->createTask(['nextexecution' => self::OVERDUE_BEFORE - 100]);
        $this->createTask(['nextexecution' => self::OVERDUE_BEFORE - 100, 'disable' => 1]);

        self::assertSame(1, $this->repository->countOverdueTasks(self::OVERDUE_BEFORE));
    }

    #[Test]
    public function countOverdueTasksExcludesTasksScheduledExactlyAtTheBoundary(): void
    {
        $this->createTask(['nextexecution' => self::OVERDUE_BEFORE]);

        self::assertSame(0, $this->repository->countOverdueTasks(self::OVERDUE_BEFORE));
    }

    #[Test]
    public function hasRunningTaskIsFalseWhenNoTaskRecordsAnExecution(): void
    {
        $this->createTask();

        self::assertFalse($this->repository->hasRunningTask());
    }

    #[Test]
    public function hasRunningTaskDetectsATaskWithSerializedExecutions(): void
    {
        $this->createTask(['serialized_executions' => 'a:1:{i:0;s:3:"now";}']);

        self::assertTrue($this->repository->hasRunningTask());
    }

    #[Test]
    public function hasRunningTaskIgnoresDisabledTasks(): void
    {
        $this->createTask(['serialized_executions' => 'a:1:{i:0;s:3:"now";}', 'disable' => 1]);

        self::assertFalse($this->repository->hasRunningTask());
    }

    #[Test]
    public function failedTaskSampleRespectsTheLimit(): void
    {
        $this->createTask(['lastexecution_failure' => 'failure 1']);
        $this->createTask(['lastexecution_failure' => 'failure 2']);
        $this->createTask(['lastexecution_failure' => 'failure 3']);

        self::assertCount(2, $this->repository->getFailedTaskSample(2));
    }

    #[Test]
    public function failedTaskSampleLabelsTasksByDescriptionWithTaskTypeFallback(): void
    {
        $described = $this->createTask([
            'lastexecution_failure' => 'serialized exception',
            'description' => 'Nightly import',
        ]);
        $undescribed = $this->createTask([
            'lastexecution_failure' => 'serialized exception',
            'tasktype' => 'monitoring:selfcare',
        ]);

        $labels = array_map(
            static fn(SchedulerTask $task): string => $task->getLabel(),
            $this->repository->getFailedTaskSample(5),
        );

        self::assertContains(sprintf('#%d (Nightly import)', $described), $labels);
        self::assertContains(sprintf('#%d (monitoring:selfcare)', $undescribed), $labels);
    }

    #[Test]
    public function overdueTaskSampleContainsOnlyOverdueTasks(): void
    {
        $overdue = $this->createTask([
            'nextexecution' => self::OVERDUE_BEFORE - 100,
            'description' => 'Stuck task',
        ]);
        $this->createTask(['nextexecution' => self::OVERDUE_BEFORE + 3_600]);

        $sample = $this->repository->getOverdueTaskSample(self::OVERDUE_BEFORE, 5);

        self::assertCount(1, $sample);
        self::assertSame($overdue, $sample[0]->uid);
        self::assertSame(sprintf('#%d (Stuck task)', $overdue), $sample[0]->getLabel());
    }

    /**
     * @param array<string, int|string|null> $overrides
     */
    private function createTask(array $overrides = []): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_scheduler_task');

        $connection->insert('tx_scheduler_task', $overrides + [
            'tasktype' => 'monitoring:dummy',
            'description' => '',
            'nextexecution' => self::OVERDUE_BEFORE + 3_600,
            'lastexecution_failure' => '',
            'serialized_executions' => null,
            'disable' => 0,
            'deleted' => 0,
        ]);

        return (int)$connection->lastInsertId();
    }
}
