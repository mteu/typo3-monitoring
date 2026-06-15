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
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * MonitoringConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class MonitoringConfiguration
{
    /**
     * Normalized endpoint path: either an empty string (endpoint disabled) or
     * a single leading slash with no trailing slash. Both consumers
     * (MonitoringMiddleware::isValid(), MiddlewareStatusProvider::execute())
     * assume this exact shape, so it is normalized here.
     */
    public string $endpoint;

    public function __construct(
        public TokenAuthorizerConfiguration $tokenAuthorizerConfiguration,
        public AdminUserAuthorizerConfiguration $adminUserAuthorizerConfiguration,
        public EmailReporterConfiguration $emailReporterConfiguration,
        public ReportDispatcherConfiguration $reporterDispatcherConfiguration,
        #[ExtConfProperty(path: 'api.endpoint')]
        string $endpoint = '/monitor/health',
        #[ExtConfProperty(path: 'api.enforceHttps')]
        public bool $enforceHttps = false,
        #[ExtConfProperty(path: 'api.includeSubResults')]
        public bool $includeSubResults = true,
        #[ExtConfProperty(path: 'cache.defaultLifetime')]
        public int $cacheDefaultLifetime = 900,
    ) {
        $this->endpoint = self::normalizeEndpoint($endpoint);
    }

    /**
     * Forces a single leading slash and strips trailing slashes so the value
     * always matches PSR-7's `UriInterface::getPath()` form. An empty (or
     * whitespace-only) configured value disables the endpoint and is
     * preserved as an empty string.
     */
    private static function normalizeEndpoint(string $endpoint): string
    {
        $trimmed = trim($endpoint);

        if ($trimmed === '') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }
}
