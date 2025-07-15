<?php

declare(strict_types=1);

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
        'iconIdentifier' => 'monitoring',
        'routes' => [
            '_default' => [
                'target' => \mteu\Monitoring\Backend\Controller\MonitoringController::class,
            ],
        ],
    ],
];
