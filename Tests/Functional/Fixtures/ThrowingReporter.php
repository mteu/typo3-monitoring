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
 * ThrowingReporter.
 *
 * Always blows up on delivery so tests can assert that the dispatcher
 * isolates a misbehaving reporter from the rest of the fan-out.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ThrowingReporter implements Reporter
{
    public function isActive(): bool
    {
        return true;
    }

    public function getThreshold(): ReportThreshold
    {
        return ReportThreshold::Always;
    }

    public function report(ReportContext $context): bool
    {
        throw new \RuntimeException('Delivery channel exploded', 1749463200);
    }

    public function getName(): string
    {
        return 'ThrowingReporter';
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
