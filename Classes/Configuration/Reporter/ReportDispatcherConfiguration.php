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

namespace mteu\Monitoring\Configuration\Reporter;

use mteu\Monitoring\Result\Status;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * ReportDispatcherConfiguration.
 *
 * Global behaviour of the dedup state machine shared by all reporters.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class ReportDispatcherConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'reportDispatcher.reminderInterval')]
        public int $reminderInterval = 86400,
        #[ExtConfProperty(path: 'reportDispatcher.notifyOnRecovery')]
        public bool $notifyOnRecovery = true,
        #[ExtConfProperty(path: 'reportDispatcher.failOpenOnMissingState')]
        public bool $failOpenOnMissingState = true,

        /**
         * Severity floor at which a provider result enters the failure set and is allowed to notify.
         * Defaults to {@see Status::Unhealthy} so a `degraded` result stays a silent HTTP 200.
         */
        #[ExtConfProperty(path: 'reportDispatcher.notifyFrom')]
        public Status $notifyFrom = Status::Unhealthy,
    ) {}
}
