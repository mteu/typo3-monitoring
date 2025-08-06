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

namespace mteu\Monitoring\Result;

/**
 * CachedMonitoringResult.
 *
 * Wrapper for cached monitoring results that includes expiration information.
 * Implements Result interface using decorator pattern to delegate to wrapped result.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class CachedMonitoringResult implements Result
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

    // Result interface implementation - delegate to wrapped result
    public function getName(): string
    {
        return $this->result->getName();
    }

    public function isHealthy(): bool
    {
        return $this->result->isHealthy();
    }

    public function getReason(): ?string
    {
        return $this->result->getReason();
    }

    /**
     * @return Result[]
     */
    public function getSubResults(): array
    {
        return $this->result->getSubResults();
    }

    public function hasSubResults(): bool
    {
        return $this->result->hasSubResults();
    }

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, mixed>
     * }
     */
    public function toArray(): array
    {
        return $this->result->toArray();
    }

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->result->jsonSerialize();
    }
}
