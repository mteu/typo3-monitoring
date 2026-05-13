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

namespace mteu\Monitoring\Tests\Unit\Command;

use mteu\Monitoring\Command\MonitoringCommand;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * MonitoringCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(MonitoringCommand::class)]
final class MonitoringCommandTest extends Framework\TestCase
{
    #[Test]
    public function returnsInvalidWhenNoActiveProvidersAvailable(): void
    {
        $inactiveProvider = $this->createProvider('Inactive', isActive: false, isHealthy: true);

        $tester = new CommandTester(new MonitoringCommand([$inactiveProvider]));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('No active providers available', $tester->getDisplay());
    }

    #[Test]
    public function returnsSuccessWhenAllActiveProvidersAreHealthy(): void
    {
        $healthy = $this->createProvider('HealthyProvider', isActive: true, isHealthy: true);

        $tester = new CommandTester(new MonitoringCommand([$healthy]));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Checking Monitoring status', $display);
        self::assertStringContainsString('HealthyProvider', $display);
        self::assertStringContainsString('Monitoring status: OK', $display);
    }

    #[Test]
    public function returnsFailureWhenActiveProviderIsUnhealthy(): void
    {
        $unhealthy = $this->createProvider('BrokenProvider', isActive: true, isHealthy: false);

        $tester = new CommandTester(new MonitoringCommand([$unhealthy]));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('BrokenProvider', $display);
        self::assertStringContainsString('Monitoring status: FAILED', $display);
    }

    #[Test]
    public function skipsInactiveProvidersInOutput(): void
    {
        $active = $this->createProvider('ActiveProvider', isActive: true, isHealthy: true);
        $inactive = $this->createProvider('InactiveProvider', isActive: false, isHealthy: true);

        $tester = new CommandTester(new MonitoringCommand([$active, $inactive]));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('ActiveProvider', $display);
        self::assertStringNotContainsString('InactiveProvider', $display);
    }

    #[Test]
    public function marksCacheableProvidersAsCachedInOutput(): void
    {
        $cacheable = new class () implements CacheableMonitoringProvider {
            public function getName(): string
            {
                return 'CacheableProvider';
            }
            public function getDescription(): string
            {
                return '';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): MonitoringResult
            {
                return new MonitoringResult('CacheableProvider', true);
            }
            public function getCacheKey(): string
            {
                return 'cache-key';
            }
            public function getCacheLifetime(): int
            {
                return 60;
            }
        };

        $tester = new CommandTester(new MonitoringCommand([$cacheable]));
        $tester->execute([]);

        self::assertStringContainsString('(cached)', $tester->getDisplay());
    }

    #[Test]
    public function rendersAllActiveProvidersWithStatusMarkers(): void
    {
        $healthy = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'AlphaProvider';
            }
            public function getDescription(): string
            {
                return '';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): MonitoringResult
            {
                return new MonitoringResult('AlphaProvider', true);
            }
        };

        $unhealthy = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'BetaProvider';
            }
            public function getDescription(): string
            {
                return '';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): MonitoringResult
            {
                return new MonitoringResult('BetaProvider', false);
            }
        };

        $tester = new CommandTester(new MonitoringCommand([$healthy, $unhealthy]));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertMatchesRegularExpression('/✅\s*AlphaProvider/u', $display);
        self::assertMatchesRegularExpression('/🚨\s*BetaProvider/u', $display);
        self::assertStringContainsString('Monitoring status: FAILED', $display);
    }

    #[Test]
    public function nonCacheableProviderIsNotMarkedAsCached(): void
    {
        $plain = $this->createProvider('PlainProvider', isActive: true, isHealthy: true);

        $tester = new CommandTester(new MonitoringCommand([$plain]));
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('PlainProvider', $display);
        self::assertStringNotContainsString('(cached)', $display);
    }

    #[Test]
    public function commandIsRegisteredWithExpectedNameAndDescription(): void
    {
        $command = new MonitoringCommand([]);

        self::assertSame('monitoring:run', $command->getName());
        self::assertSame('This command runs monitoring.', $command->getDescription());
    }

    private function createProvider(string $name, bool $isActive, bool $isHealthy): MonitoringProvider
    {

        return new readonly class ($name, $isActive, $isHealthy) implements MonitoringProvider {
            public function __construct(
                private string $name,
                private bool $active,
                private bool $healthy,
            ) {}

            public function getName(): string
            {
                assert($this->name !== '');

                return $this->name;
            }

            public function getDescription(): string
            {
                return '';
            }

            public function isActive(): bool
            {
                return $this->active;
            }

            public function execute(): MonitoringResult
            {
                return new MonitoringResult($this->name, $this->healthy);
            }
        };
    }
}
