<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;

/**
 * MonitoringConfiguration DTO.
 *
 * @author Martin Adler <m.adler@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringConfiguration
{
    public function __construct(
        public string $endpoint,
        public TokenAuthorizerConfiguration $tokenAuthorizerConfiguration,
        public AdminUserAuthorizerConfiguration $adminUserAuthorizerConfiguration,
    ) {}
}
