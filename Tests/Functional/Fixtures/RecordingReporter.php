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

namespace mteu\Monitoring\Tests\Functional\Fixtures;

use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportThreshold;

/**
 * RecordingReporter.
 *
 * Captures every {@see ReportContext} it is handed so tests can assert when
 * (and how often) the dispatcher decided to notify.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class RecordingReporter implements Reporter
{
    /** @var list<ReportContext> */
    private array $received = [];

    public function __construct(
        private readonly ReportThreshold $threshold = ReportThreshold::Unhealthy,
        private readonly bool $active = true,
        private readonly bool $deliverSuccessfully = true,
    ) {}

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getThreshold(): ReportThreshold
    {
        return $this->threshold;
    }

    public function report(ReportContext $context): bool
    {
        $this->received[] = $context;

        return $this->deliverSuccessfully;
    }

    public function receivedCount(): int
    {
        return count($this->received);
    }

    public function getName(): string
    {
        return 'RecordingReporter';
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
