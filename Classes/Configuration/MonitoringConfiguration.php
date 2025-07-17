<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration;

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
        public bool $tokenAuthorizerEnabled,
        public string $tokenAuthorizerSecret,
        public string $tokenAuthorizerAuthHeaderName,
        public int $tokenAuthorizerPriority,
        public bool $adminUserAuthorizerEnabled,
        public int $adminUserAuthorizerPriority
    ) {}
}
