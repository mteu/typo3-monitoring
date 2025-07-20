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

namespace mteu\Monitoring\Configuration;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Provider\SelfCareProviderConfiguration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * MonitoringConfigurationFactory.
 *
 * The extension configuration is somewhat difficult to validate against strict types. One configure through the Install
 * Tool of course. But more often configuration is set or changed in the config/system/*.php or similar approaches.
 * That is why this factory exists. It forces strict types that the rest of the EXT:monitoring can rely on.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringConfigurationFactory
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration
    ) {}

    public function create(): MonitoringConfiguration
    {
        /**
         * @var array{
         *       api?: array{
         *         endpoint?: string
         *       },
         *       authorizer?: array{
         *         "mteu\Monitoring\Authorization\TokenAuthorizer"?: array{
         *           enabled?: string|bool|int,
         *           secret?: string|int,
         *           authHeaderName?: string,
         *           priority?: string|bool|int|float,
         *         },
         *         "mteu\Monitoring\Authorization\AdminUserAuthorizer"?: array{
         *           enabled?: string|bool|int,
         *           priority?: string|bool|int|float,
         *         }
         *      },
         *     provider?: array{
         *      enabled?: string|bool|int,
         *      },
         *   } $config
         */
        $config = $this->extensionConfiguration->get('monitoring') ?? [];

        $adminUserConfig = $config['authorizer']['mteu\Monitoring\Authorization\AdminUserAuthorizer'] ?? [];
        $tokenAuthorizerConfig = $config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer'] ?? [];
        $selfCareProviderConfig = $config['provider']['mteu\Monitoring\Provider\SelfCareProvider'] ?? [];

        $adminUserConfiguration = new AdminUserAuthorizerConfiguration(
            isEnabled: $this->boolean($adminUserConfig['enabled'] ?? false),
            priority: $this->integer($adminUserConfig['priority'] ?? -10),
        );

        $tokenAuthorizerConfiguration = new TokenAuthorizerConfiguration(
            isEnabled: $this->boolean($tokenAuthorizerConfig['enabled'] ?? false),
            priority: $this->integer($tokenAuthorizerConfig['priority'] ?? 10),
            secret: $this->string($tokenAuthorizerConfig['secret'] ?? ''),
            authHeaderName: $this->string($tokenAuthorizerConfig['authHeaderName'] ?? ''),
        );

        $selfCareProviderConfiguration = new SelfCareProviderConfiguration(
            isEnabled: $selfCareProviderConfig['enabled'] ?? false,
        );

        return new MonitoringConfiguration(
            endpoint: $this->string($config['api']['endpoint'] ?? ''),
            tokenAuthorizerConfiguration: $tokenAuthorizerConfiguration,
            adminUserAuthorizerConfiguration: $adminUserConfiguration,
            selfCareProviderConfiguration: $selfCareProviderConfiguration,
        );
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function integer(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        return 0;
    }

    private function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        return '';
    }
}
