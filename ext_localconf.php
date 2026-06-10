<?php

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

defined('TYPO3') or die();

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['typo3_monitoring'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['typo3_monitoring'] = [
        'frontend' => TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [],
        'groups' => ['system'],
    ];
}

// Notification dedup state. Intentionally NOT in the 'system' group so that
// routine "flush system caches" / provider-cache-flush actions cannot make
// the reporter forget it already alerted (and re-notify on the next run).
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['typo3_monitoring_notification'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['typo3_monitoring_notification'] = [
        'frontend' => TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [],
        'groups' => [],
    ];
}

// Optional integration with the TYPO3 Scheduler. Guarded so the extension still
// installs and the CLI path (monitoring:report) keeps working without the
// typo3/cms-scheduler system extension.
if (class_exists(\TYPO3\CMS\Scheduler\Task\AbstractTask::class)) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\mteu\Monitoring\Task\MonitoringReportTask::class] = [
        'extension' => 'monitoring',
        'title' => 'Monitoring: Dispatch Reporters',
        'description' => 'Evaluates monitoring status and dispatches push notifications to active reporters.',
    ];
}
