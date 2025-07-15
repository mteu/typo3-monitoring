<?php

declare(strict_types=1);

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
