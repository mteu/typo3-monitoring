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

namespace mteu\Monitoring\Authorization;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;

/**
 * AdminUserAuthorizer.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class AdminUserAuthorizer implements Authorizer
{
    private AdminUserAuthorizerConfiguration $adminUserConfiguration;

    public function __construct(
        private Context $context,
        private MonitoringConfiguration $monitoringConfiguration,
    ) {
        $this->adminUserConfiguration = $this->monitoringConfiguration->adminUserAuthorizerConfiguration;
    }

    public function isActive(): bool
    {
        return $this->adminUserConfiguration->isEnabled();
    }

    /**
     * @throws AspectNotFoundException
     */
    public function isAuthorized(ServerRequestInterface $request): bool
    {
        return
            /** @phpstan-ignore-next-line  */
            $this->context->getPropertyFromAspect('backend.user', 'isAdmin') &&
            $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    public static function getPriority(): int
    {
        return -10;
    }
}
