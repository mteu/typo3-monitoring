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
    // v14: container renders its own overview
    $monitoring['showSubmoduleOverview'] = true;
} else {
    // Mark the module standalone so its menu link is
    $monitoring['standalone'] = true;
}

$modules = ['monitoring' => $monitoring];

// On v14 these are submodules of the container (grouped menu).
// On v13 they are NOT children of a standalone module, so we're registering them as standalone destinations for the cards.
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

if ($isV14OrHigher) {
    $modules['monitoring_providers'] = $childCommon(ProviderController::class, '/module/system/monitoring/providers', $l10n . 'module.providers');
    $modules['monitoring_authorizers'] = $childCommon(AuthorizerController::class, '/module/system/monitoring/authorizers', $l10n . 'module.authorizers');
    $modules['monitoring_reporters'] = $childCommon(ReporterController::class, '/module/system/monitoring/reporters', $l10n . 'module.reporters');
} else {
    // v13: standalone sub-areas, hidden from the module menu, reachable only
    // via the overview cards (buildUriFromRoute in the controller).
    $standalone = static function (string $target, string $path) use ($l10n): array {
        return [
            'access' => 'systemMaintainer',
            'workspaces' => 'live',
            'standalone' => true,
            'appearance' => ['renderInModuleMenu' => false],
            'path' => $path,
            'labels' => [
                'title' => $l10n . 'module.labels.title',
                'description' => $l10n . 'module.labels.description',
            ],
            'routes' => ['_default' => ['target' => $target]],
        ];
    };
    $modules['monitoring_providers'] = $standalone(ProviderController::class, '/module/system/monitoring/providers');
    $modules['monitoring_authorizers'] = $standalone(AuthorizerController::class, '/module/system/monitoring/authorizers');
    $modules['monitoring_reporters'] = $standalone(ReporterController::class, '/module/system/monitoring/reporters');
}

return $modules;
