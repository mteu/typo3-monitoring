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

namespace mteu\Monitoring\Configuration\Provider;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * SchedulerProviderConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class SchedulerProviderConfiguration implements ProviderConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SchedulerProvider.enabled')]
        private bool $enabled = true,
        /**
         * Maximum age (in seconds) of the last scheduler run before the
         * heartbeat is considered unhealthy. This detects a broken cron job
         * that no longer invokes the scheduler at all. Set to 0 to disable
         * the heartbeat check.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SchedulerProvider.heartbeatThreshold')]
        public int $heartbeatThreshold = 900,
        /**
         * Grace period (in seconds) after a task's scheduled next execution
         * time before it is reported as overdue. Only tasks with an explicit
         * next-execution time are considered; tasks configured for manual
         * execution are excluded automatically. Set to 0 to disable.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SchedulerProvider.overdueThreshold')]
        public int $overdueThreshold = 300,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isHeartbeatCheckEnabled(): bool
    {
        return $this->heartbeatThreshold > 0;
    }

    public function isOverdueCheckEnabled(): bool
    {
        return $this->overdueThreshold > 0;
    }
}
