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
 * Opinionated implementation of mteu\Monitoring\Result
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class MonitoringResult implements Result
{
    /**
     * @param list<Result> $subResults
     */
    public function __construct(
        private readonly string $name,
        private bool $isHealthy,
        private ?string $reason = null,
        private array $subResults = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isHealthy(): bool
    {
        if (!$this->isHealthy || !$this->hasSubResults()) {
            return $this->isHealthy;
        }

        foreach ($this->subResults as $subResult) {
            if (!$subResult->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    public function setHealthy(bool $isHealthy): Result
    {
        $this->isHealthy = $isHealthy;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): Result
    {
        $this->reason = $reason;

        return $this;
    }

    public function hasSubResults(): bool
    {
        return count($this->subResults) > 0;
    }

    /**
     * @return list<Result>
     */
    public function getSubResults(): array
    {
        return $this->subResults;
    }

    public function addSubResult(Result $result): Result
    {
        $this->subResults[] = $result;

        return $result;
    }

    /**
     * Magic getter for Fluid templates to access private properties as way dirty workaround to Fluid's inability to
     * invoke the `isHealthy()` method instead of directly accessing the property with the exact same name.
     */
    public function __get(string $property): mixed
    {
        return match ($property) {
            'name' => $this->name,
            'isHealthy' => $this->isHealthy,
            'description' => $this->reason,
            default => throw new \InvalidArgumentException(
                sprintf('Property "%s" does not exist on %s', $property, self::class)
            ),
        };
    }

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, array{name: string, isHealthy: bool, description: string|null}>
     * }
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'isHealthy' => $this->isHealthy,
            'description' => $this->reason,
        ];

        if ($this->subResults !== []) {
            $array['subResults'] = array_map(
                static fn(Result $subResult): array => $subResult->toArray(),
                $this->subResults
            );
        }

        return $array;
    }

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, array{name: string, isHealthy: bool, description: string|null}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
