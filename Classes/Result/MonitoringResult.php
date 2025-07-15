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

namespace mteu\Monitoring\Result;

/**
 * MonitoringResult.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class MonitoringResult extends AbstractMonitoringResult
{
    /**
     * @param list<Result> $subResults
     */
    public function __construct(
        private readonly string $name,
        private bool $isHealthy = false,
        private readonly ?string $reason = null,
        private readonly array $subResults = [],
    ) {
        parent::__construct(
            name: $this->name,
            isHealthy: $this->isHealthy(),
            reason: $this->reason,
            subResults: $this->subResults,
        );
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    public function setHealthy(bool $isHealthy): self
    {
        $this->isHealthy = $isHealthy;

        return $this;
    }

}
