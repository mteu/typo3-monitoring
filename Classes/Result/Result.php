<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring".
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

interface Result extends \JsonSerializable
{
    public function getName(): string;

    public function isHealthy(): bool;

    public function getReason(): ?string;

    /**
     * Returns any sub-results of this monitoring result
     *
     * @return Result[]
     */
    public function getSubResults(): array;

    public function hasSubResults(): bool;

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, mixed>
     * }
     */
    public function toArray(): array;

    /**
     * @return array{
     *     name: string,
     *     isHealthy: bool,
     *     description: string|null,
     *     subResults?: array<int, mixed>
     * }
     */
    public function jsonSerialize(): array;
}
