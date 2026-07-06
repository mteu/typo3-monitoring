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

use mteu\Monitoring\Backend\Controller\AuthorizerController;
use mteu\Monitoring\Backend\Controller\MonitoringController;
use mteu\Monitoring\Backend\Controller\ProviderController;
use mteu\Monitoring\Backend\Controller\ReporterController;
use TYPO3\CMS\Core\Information\Typo3Version;

$isV14OrHigher = (new Typo3Version())->getMajorVersion() >= 14;

$l10n = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:';

$monitoring = [
    'parent' => 'system',
    'position' => ['before' => 'permissions_pages'],
    'access' => 'systemMaintainer',
    'workspaces' => 'live',
    'path' => '/module/system/monitoring',
    'labels' => [
        'title' => $l10n . 'module.labels.title',
        'description' => $l10n . 'module.labels.description',
    ],
    // @todo: I've yet to find a better solution to supporting both Icons
    'iconIdentifier' => $isV14OrHigher ? 'module-monitoring' : 'module-monitoring-legacy',
    'routes' => [
        '_default' => [
            'target' => MonitoringController::class,
        ],
    ],
];

if ($isV14OrHigher) {
    // v14: adds an "Overview" entry to the core-rendered submodule dropdown.
    // v13 core does not know this key (it is silently ignored there); the  Overview entry is added manually in
    // AbstractSubModuleController instead.
    $monitoring['showSubmoduleOverview'] = true;
}

$modules = ['monitoring' => $monitoring];

$childCommon = static fn(string $target, string $path, string $prefix): array => [
    'parent' => 'monitoring',
    'access' => 'systemMaintainer',
    'workspaces' => 'live',
    'path' => $path,
    'labels' => [
        'title' => $prefix . '.labels.title',
        'description' => $prefix . '.labels.description',
    ],
    'routes' => ['_default' => ['target' => $target]],
];

$children = [
    'monitoring_providers' => $childCommon(ProviderController::class, '/module/system/monitoring/providers', $l10n . 'module.providers'),
    'monitoring_authorizers' => $childCommon(AuthorizerController::class, '/module/system/monitoring/authorizers', $l10n . 'module.authorizers'),
    'monitoring_reporters' => $childCommon(ReporterController::class, '/module/system/monitoring/reporters', $l10n . 'module.reporters'),
];

if (!$isV14OrHigher) {
    // v13 core forcibly rewrites any request to a module that has submodules to its last-used/first submodule
    // (BackendModuleValidator), which would defeat opening the overview first. Keep the sub-areas standalone and
    // out of the module menu there. The docheader submodule dropdown is built manually in AbstractSubModuleController
    // from these identifiers.
    foreach ($children as &$child) {
        unset($child['parent']);
        $child['standalone'] = true;
        $child['appearance'] = ['renderInModuleMenu' => false];
    }
    unset($child);
}

return array_merge($modules, $children);
