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
