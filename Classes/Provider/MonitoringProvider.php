<?php

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

declare(strict_types=1);

namespace mteu\Monitoring\Provider;

use mteu\Monitoring\Result\Result;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * MonitoringProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AutoconfigureTag(name: 'monitoring.provider')]
interface MonitoringProvider
{
    /**
     * The provider name is used as key in the json-result output on the health route, e.g. /monitor/health/
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * The description is only shown in the backend module of this extension.
     */
    public function getDescription(): string;

    /**
     * Allows your provider to implement the MonitoringProvider interface while being ignored for the actual monitoring.
     */
    public function isActive(): bool;

    public function execute(): Result;
}
