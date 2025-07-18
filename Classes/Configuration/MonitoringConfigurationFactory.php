<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * MonitoringConfigurationFactory.
 *
 * The extension configuration is somewhat difficult to validate against strict types. One configure through the Install
 * Tool of course. But more often configuration is set or changed in the config/system/*.php or similar approaches.
 * That is why this factory exists. It forces strict types that the rest of the EXT:monitoring can rely on.
 *
 * @author Martin Adler <m.adler@mailbox.org>
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
         *      }
         *   } $config
         */
        $config = $this->extensionConfiguration->get('monitoring') ?? [];

        $adminUserConfig = $config['authorizer']['mteu\Monitoring\Authorization\AdminUserAuthorizer'] ?? [];
        $tokenAuthorizerConfig = $config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer'] ?? [];

        $adminUserConfiguration = new AdminUserAuthorizerConfiguration(
            enabled: $this->boolean($adminUserConfig['enabled'] ?? false),
            priority: $this->integer($adminUserConfig['priority'] ?? -10),
        );

        $tokenAuthorizerConfiguration = new TokenAuthorizerConfiguration(
            enabled: $this->boolean($tokenAuthorizerConfig['enabled'] ?? false),
            priority: $this->integer($tokenAuthorizerConfig['priority'] ?? 10),
            secret: $this->string($tokenAuthorizerConfig['secret'] ?? ''),
            authHeaderName: $this->string($tokenAuthorizerConfig['authHeaderName'] ?? ''),
        );

        return new MonitoringConfiguration(
            endpoint: $this->string($config['api']['endpoint'] ?? ''),
            tokenAuthorizerConfiguration: $tokenAuthorizerConfiguration,
            adminUserAuthorizerConfiguration: $adminUserConfiguration,
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
        return (string)$value;
    }
}
