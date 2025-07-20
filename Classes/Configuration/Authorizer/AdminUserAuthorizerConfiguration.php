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

namespace mteu\Monitoring\Configuration\Authorizer;

/**
 * AdminUserConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class AdminUserAuthorizerConfiguration implements AuthorizerConfiguration
{
    public function __construct(
        private bool $isEnabled = false,
        private int $priority = -10,
    ) {}

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
