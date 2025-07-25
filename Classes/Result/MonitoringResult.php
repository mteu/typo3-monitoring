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
        if ($this->isHealthy ===  false || $this->hasSubResults() === false) {
            return $this->isHealthy;
        }

        foreach ($this->subResults as $subResult) {
            if ($subResult->isHealthy() === false) {
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
