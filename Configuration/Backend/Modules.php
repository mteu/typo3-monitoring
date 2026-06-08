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

// @todo: I've yet to find a more sensible way to support both Icons
$moduleIconIdentifier = (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14 ? 'legacyModuleIcon' : 'moduleIcon';

return [
    'monitoring' => [
        'parent' => 'system',
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.labels.description',
        ],
        'iconIdentifier' => $moduleIconIdentifier,
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\MonitoringController::class,
            ],
            'authorizers' => [
                'path' => '/module/system/monitoring/authorizers',
                'target' => \mteu\Monitoring\Backend\Controller\AuthorizerController::class,
            ],
        ],
    ],
];
