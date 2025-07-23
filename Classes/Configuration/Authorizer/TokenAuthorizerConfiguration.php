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

use mteu\TypedExtConf\Attribute\ExtConfProperty;

/**
 * TokenAuthorizerConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TokenAuthorizerConfiguration implements AuthorizerConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\TokenAuthorizer.enabled', required: true)]
        public bool $enabled = false,
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\TokenAuthorizer.priority')]
        public int $priority = 10,
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\TokenAuthorizer.secret', required: true)]
        public string $secret = '',
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\TokenAuthorizer.authHeaderName')]
        public string $authHeaderName = '',
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
