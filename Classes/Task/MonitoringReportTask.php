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

namespace mteu\Monitoring\Task;

use mteu\Monitoring\Reporter\ReportDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * MonitoringReportTask.
 *
 * Backend Scheduler trigger for the push reporters. Thin shell delegating to {@see ReportDispatcher} so it stays
 * identical to the cron-friendly {@see \mteu\Monitoring\Command\ReportCommand}.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class MonitoringReportTask extends AbstractTask
{
    public function execute(): bool
    {
        $result = GeneralUtility::makeInstance(ReportDispatcher::class)->dispatch();

        // A notification was due but no active reporter delivered it (e.g. the EmailReporter has no recipient
        // configured). Show the task as failed so the Scheduler flags the misconfiguration.
        return !$result->isUndelivered();
    }
}
