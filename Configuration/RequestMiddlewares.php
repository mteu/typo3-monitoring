<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'frontend' => [
        'mteu/typo3_monitoring' => [
            'target' => \mteu\Monitoring\Middleware\MonitoringMiddleware::class,
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
        ],
    ],
];
