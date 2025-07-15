<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-monitoring".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
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
