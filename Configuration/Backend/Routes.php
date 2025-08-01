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

return [
    'monitoring' => [
        'path' => '/monitoring',
        'target' => \mteu\Monitoring\Backend\Controller\MonitoringController::class,
    ],
    'monitoring_flush_provider_cache' => [
        'path' => '/monitoring/flush-provider-cache',
        'target' => \mteu\Monitoring\Backend\Controller\MonitoringController::class . '::flushProviderCache',
    ],
];
