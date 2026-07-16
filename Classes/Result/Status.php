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
 * Status.
 *
 * The health state a {@see Result} reports. Ordered by severity so a parent result can be aggregated from its
 * sub-results and compared against a threshold. `Degraded` is operational-but-attention-worthy: it keeps the endpoint
 * at HTTP 200 and is not, on its own, a failure. Only `Unhealthy` is treated as an outage.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
enum Status: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    /**
     * Severity ranking; a higher value is worse.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Healthy => 0,
            self::Degraded => 1,
            self::Unhealthy => 2,
        };
    }

    /**
     * Whether this status is at least as severe as the given floor.
     */
    public function isAtLeast(self $floor): bool
    {
        return $this->severity() >= $floor->severity();
    }

    /**
     * Returns the worst (highest-severity) status of the given set, defaulting to {@see Status::Healthy}
     * when the set is empty.
     */
    public static function worst(self ...$statuses): self
    {
        $worst = self::Healthy;

        foreach ($statuses as $status) {
            if ($status->severity() > $worst->severity()) {
                $worst = $status;
            }
        }

        return $worst;
    }
}
