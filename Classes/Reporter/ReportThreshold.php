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

namespace mteu\Monitoring\Reporter;

/**
 * ReportThreshold.
 *
 * Per-reporter policy controlling which dispatch decisions actually trigger a
 * notification for that reporter.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
enum ReportThreshold: string
{
    case Always = 'always';
    case Unhealthy = 'unhealthy';
    case StateChange = 'state-change';

    public static function fromConfigValue(string $value): self
    {
        return self::tryFrom($value) ?? self::Unhealthy;
    }
}
