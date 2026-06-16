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
        'parent' => 'system',
        'position' => ['before' => 'permissions_pages'],
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.labels.description',
        ],
        // @todo: I've yet to find a more sensible way to support both Icons
        'iconIdentifier' => (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14 ? 'module-monitoring-legacy' : 'module-monitoring',
    ],
    // @todo: remove once v13 support is dropped and use 'showSubmoduleOverview' => true on main container
    'monitoring_overview' => [
        'parent' => 'monitoring',
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring/overview',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.overview.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.overview.labels.description',
        ],
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\MonitoringController::class,
            ],
        ],
    ],
    'monitoring_providers' => [
        'parent' => 'monitoring',
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring/providers',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.providers.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.providers.labels.description',
        ],
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\ProviderController::class,
            ],
        ],
    ],
    'monitoring_authorizers' => [
        'parent' => 'monitoring',
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring/authorizers',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.authorizers.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.authorizers.labels.description',
        ],
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\AuthorizerController::class,
            ],
        ],
    ],
    'monitoring_reporters' => [
        'parent' => 'monitoring',
        'access' => 'systemMaintainer',
        'workspaces' => 'live',
        'path' => '/module/system/monitoring/reporters',
        'labels' => [
            'title' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.reporters.labels.title',
            'description' => 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.reporters.labels.description',
        ],
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\ReporterController::class,
            ],
        ],
    ],
];
