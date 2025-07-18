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

use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfigurationFactory;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * TokenAuthorizer.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TokenAuthorizer implements Authorizer
{
    private MonitoringConfiguration $configuration;
    private TokenAuthorizerConfiguration $tokenAuthorizerConfiguration;

    public function __construct(
        private HashService $hashService,
        private MonitoringConfigurationFactory $monitoringConfigurationFactory,
    ) {
        $this->configuration = $this->monitoringConfigurationFactory->create();
        $this->tokenAuthorizerConfiguration = $this->configuration->tokenAuthorizerConfiguration;
    }

    public function isActive(): bool
    {
        return
            $this->tokenAuthorizerConfiguration->isEnabled() &&
            $this->tokenAuthorizerConfiguration->secret !== '';
    }

    public function isAuthorized(ServerRequestInterface $request): bool
    {
        $authToken = $request->getHeaderLine($this->tokenAuthorizerConfiguration->authHeaderName);

        if ($authToken === '') {
            return false;
        }

        if ($this->tokenAuthorizerConfiguration->secret === '') {
            return false;
        }

        return $this->hashService->validateHmac(
            $this->configuration->endpoint,
            $this->tokenAuthorizerConfiguration->secret,
            $authToken,
        );
    }

    public static function getPriority(): int
    {
        return (new MonitoringConfigurationFactory(new ExtensionConfiguration()))
            ->create()->tokenAuthorizerConfiguration->getPriority();
    }
}
