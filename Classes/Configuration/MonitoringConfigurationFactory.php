<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * MonitoringConfigurationFactory.
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
         *       api: array{
         *         endpoint: string
         *       },
         *       authorizer: array{
         *         "mteu\Monitoring\Authorization\TokenAuthorizer": array{
         *           enabled: string|bool|int,
         *           secret: string,
         *           authHeaderName: string,
         *           priority: string|bool|int|float,
         *         },
         *         "mteu\Monitoring\Authorization\AdminUserAuthorizer": array{
         *           enabled: string|bool|int,
         *           priority: string|bool|int|float,
         *         }
         *      }
         *   } $config
         */
        $config = $this->extensionConfiguration->get('monitoring');

        return new MonitoringConfiguration(
            endpoint: $config['api']['endpoint'],
            tokenAuthorizerEnabled: $this->boolean($config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer']['enabled']),
            tokenAuthorizerSecret: (string)$config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer']['secret'],
            tokenAuthorizerAuthHeaderName: $config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer']['authHeaderName'],
            tokenAuthorizerPriority: (int)$config['authorizer']['mteu\Monitoring\Authorization\TokenAuthorizer']['priority'],
            adminUserAuthorizerEnabled: $this->boolean($config['authorizer']['mteu\Monitoring\Authorization\AdminUserAuthorizer']['enabled']),
            adminUserAuthorizerPriority: (int)$config['authorizer']['mteu\Monitoring\Authorization\AdminUserAuthorizer']['priority'],
        );
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
