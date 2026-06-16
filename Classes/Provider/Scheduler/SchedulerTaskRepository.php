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

namespace mteu\Monitoring\Provider\Scheduler;

/**
 * Data access for scheduler task health. Each method maps to one access
 * pattern. Counts drive health; samples are best-effort reason decoration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface SchedulerTaskRepository
{
    public function countFailedTasks(): int;

    public function countOverdueTasks(int $overdueBefore): int;

    public function hasRunningTask(): bool;

    /**
     * @param positive-int $limit
     * @return list<SchedulerTask>
     */
    public function getFailedTaskSample(int $limit): array;

    /**
     * @param positive-int $limit
     * @return list<SchedulerTask>
     */
    public function getOverdueTaskSample(int $overdueBefore, int $limit): array;
}
