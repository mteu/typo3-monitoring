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

namespace mteu\Monitoring\ViewHelper\Backend;

use mteu\Monitoring\Result\Status;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * StatusContextViewHelper.
 *
 * Maps a {@see Status} value to its Bootstrap contextual class so badges and
 * panels can colour a health state consistently:
 *
 *   healthy => success, degraded => warning, unhealthy => danger
 *
 * Register in Fluid:
 * {namespace monitoring=mteu\Monitoring\ViewHelper\Backend}
 *
 * Inline Usage:
 * badge badge-{detail.statusValue -> monitoring:statusContext()}
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class StatusContextViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'status',
            'string',
            'Status value (healthy, degraded, unhealthy) to map to a Bootstrap context.',
        );
    }

    public function render(): string
    {
        $status = $this->arguments['status'] ?? $this->renderChildren();

        if (!is_string($status)) {
            return 'default';
        }

        return match (Status::tryFrom(trim($status))) {
            Status::Healthy => 'success',
            Status::Degraded => 'warning',
            Status::Unhealthy => 'danger',
            null => 'default',
        };
    }
}
