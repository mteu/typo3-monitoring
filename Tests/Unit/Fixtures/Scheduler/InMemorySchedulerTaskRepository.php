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

namespace mteu\Monitoring\Tests\Unit\Fixtures\Scheduler;

use mteu\Monitoring\Provider\Scheduler\SchedulerTask;
use mteu\Monitoring\Provider\Scheduler\SchedulerTaskRepository;

/**
 * InMemorySchedulerTaskGateway.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class InMemorySchedulerTaskRepository implements SchedulerTaskRepository
{
    /**
     * @param list<SchedulerTask> $failedSample
     * @param list<SchedulerTask> $overdueSample
     */
    public function __construct(
        private int $failedCount = 0,
        private int $overdueCount = 0,
        private array $failedSample = [],
        private array $overdueSample = [],
        private bool $hasRunningTask = false,
    ) {}

    public function countFailedTasks(): int
    {
        return $this->failedCount;
    }

    public function countOverdueTasks(int $overdueBefore): int
    {
        return $this->overdueCount;
    }

    public function hasRunningTask(): bool
    {
        return $this->hasRunningTask;
    }

    public function getFailedTaskSample(int $limit): array
    {
        return array_slice($this->failedSample, 0, $limit);
    }

    public function getOverdueTaskSample(int $overdueBefore, int $limit): array
    {
        return array_slice($this->overdueSample, 0, $limit);
    }
}
