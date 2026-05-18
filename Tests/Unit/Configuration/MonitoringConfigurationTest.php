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

namespace mteu\Monitoring\Tests\Unit\Configuration;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * MonitoringConfigurationTest.
 *
 * Pins the endpoint-normalization contract. Both consumers
 * (MonitoringMiddleware::isValid(), MiddlewareStatusProvider::execute())
 * assume `endpoint` is either '' (disabled) or a single leading slash with
 * no trailing slash. A regression here silently disables the health endpoint
 * or produces a malformed self-request URL.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringConfiguration::class)]
final class MonitoringConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function defaultEndpointIsTheCanonicalLeadingSlashForm(): void
    {
        self::assertSame('/monitor/health', $this->createConfiguration()->endpoint);
    }

    #[Test]
    #[DataProvider('endpointNormalizationProvider')]
    public function endpointIsNormalizedToASingleLeadingSlashWithoutTrailingSlash(
        string $configured,
        string $expected,
    ): void {
        self::assertSame($expected, $this->createConfigurationWithEndpoint($configured)->endpoint);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function endpointNormalizationProvider(): \Generator
    {
        yield 'canonical leading slash' => ['/monitor/health', '/monitor/health'];
        yield 'missing leading slash' => ['monitor/health', '/monitor/health'];
        yield 'trailing slash' => ['/monitor/health/', '/monitor/health'];
        yield 'redundant slashes' => ['//monitor/health//', '/monitor/health'];
        yield 'surrounding whitespace' => ['  /monitor/health  ', '/monitor/health'];
        yield 'custom path no slash' => ['health', '/health'];
        yield 'empty string stays empty' => ['', ''];
        yield 'whitespace-only disables' => ['   ', ''];
    }

    private function createConfiguration(): MonitoringConfiguration
    {
        return new MonitoringConfiguration(
            new TokenAuthorizerConfiguration(),
            new AdminUserAuthorizerConfiguration(),
        );
    }

    private function createConfigurationWithEndpoint(string $endpoint): MonitoringConfiguration
    {
        return new MonitoringConfiguration(
            new TokenAuthorizerConfiguration(),
            new AdminUserAuthorizerConfiguration(),
            $endpoint,
        );
    }
}
