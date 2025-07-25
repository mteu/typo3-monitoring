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

namespace mteu\Monitoring\Configuration;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Provider\SelfCareProviderConfiguration;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * MonitoringConfiguration DTO.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class MonitoringConfiguration
{
    public function __construct(
        public TokenAuthorizerConfiguration $tokenAuthorizerConfiguration,
        public AdminUserAuthorizerConfiguration $adminUserAuthorizerConfiguration,
        public SelfCareProviderConfiguration $selfCareProviderConfiguration,
        #[ExtConfProperty(path: 'api.endpoint')]
        public string $endpoint = 'monitor/health',
    ) {}
}
