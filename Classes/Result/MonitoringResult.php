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
        private Status $status,
        private ?string $reason = null,
        private array $subResults = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The effective status: the worst of this result's own status and every
     * sub-result's status.
     */
    public function getStatus(): Status
    {
        if ($this->subResults === []) {
            return $this->status;
        }

        $statuses = [$this->status];
        foreach ($this->subResults as $subResult) {
            $statuses[] = $subResult->getStatus();
        }

        return Status::worst(...$statuses);
    }

    public function isHealthy(): bool
    {
        return $this->getStatus() !== Status::Unhealthy;
    }

    /**
     * Fluid-friendly accessor for {@see isHealthy()}.
     */
    public function getIsHealthy(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Fluid-friendly accessor returning the backing string of {@see getStatus()}.
     */
    public function getStatusValue(): string
    {
        return $this->getStatus()->value;
    }

    public function setStatus(Status $status): Result
    {
        $this->status = $status;

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
     * @return array{
     *     name: string,
     *     status: string,
     *     description: string|null,
     *     subResults?: array<int, array{name: string, status: string, description: string|null}>
     * }
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'status' => $this->getStatus()->value,
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
     *     status: string,
     *     description: string|null,
     *     subResults?: array<int, array{name: string, status: string, description: string|null}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
