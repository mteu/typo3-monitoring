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

namespace mteu\Monitoring\Provider;

use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;

/**
 * SchedulerMonitoringProvider.
 *
 * Monitors the health of the TYPO3 scheduler with three sub-results:
 *
 *   1. heartbeat: Is cron still invoking the scheduler at all?
 *   2. failed tasks: Did any enabled task record an execution failure?
 *   3. overdue tasks: Is a scheduled task long past its next execution time?
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class BarProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'Bar (disabled, active)';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        return new MonitoringResult($this->getName(), true);
    }
}
