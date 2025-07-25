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

namespace mteu\Monitoring\Cache;

use mteu\Monitoring\Result\Result;

/**
 * CachedMonitoringResult.
 *
 * Wrapper for cached monitoring results that includes expiration information
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class CachedMonitoringResult
{
    public function __construct(
        private Result $result,
        private \DateTimeImmutable $cachedAt,
        private int $lifetime,
    ) {}

    public function getResult(): Result
    {
        return $this->result;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        // Use server timezone for consistent timezone handling
        $serverTimezone = new \DateTimeZone(date_default_timezone_get());
        return $this->cachedAt->setTimezone($serverTimezone)->add(new \DateInterval('PT' . $this->lifetime . 'S'));
    }

    public function isExpired(): bool
    {
        // Use server timezone for consistent timezone handling
        $serverTimezone = new \DateTimeZone(date_default_timezone_get());
        $now = new \DateTimeImmutable('now', $serverTimezone);
        return $this->getExpiresAt() < $now;
    }
}
