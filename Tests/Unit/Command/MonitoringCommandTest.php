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

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Command\MonitoringCommand;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Status;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;

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

        $tester = new CommandTester(new MonitoringCommand([$inactiveProvider], $this->createExecutionHandler()));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('No active providers available', $tester->getDisplay());
    }

    #[Test]
    public function returnsSuccessWhenAllActiveProvidersAreHealthy(): void
    {
        $healthy = $this->createProvider('HealthyProvider', isActive: true, isHealthy: true);

        $tester = new CommandTester(new MonitoringCommand([$healthy], $this->createExecutionHandler()));
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

        $tester = new CommandTester(new MonitoringCommand([$unhealthy], $this->createExecutionHandler()));
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

        $tester = new CommandTester(new MonitoringCommand([$active, $inactive], $this->createExecutionHandler()));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('ActiveProvider', $display);
        self::assertStringNotContainsString('InactiveProvider', $display);
    }

    #[Test]
    public function skipsDisabledProvidersEvenWhenTheyClaimToBeActive(): void
    {
        $active = $this->createProvider('EnabledActiveProvider', isActive: true, isHealthy: true);

        // Contradicts itself: disabled by the operator, yet isActive() === true.
        // isEnabled() is the kill-switch, so it must not be executed.
        $disabledButActive = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'DisabledButActiveProvider';
            }

            public function isEnabled(): bool
            {
                return false;
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
                return new MonitoringResult('DisabledButActiveProvider', Status::Unhealthy);
            }
        };

        $tester = new CommandTester(new MonitoringCommand([$active, $disabledButActive], $this->createExecutionHandler()));
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('EnabledActiveProvider', $display);
        self::assertStringNotContainsString('DisabledButActiveProvider', $display);
    }

    #[Test]
    public function rendersAllActiveProvidersWithStatusMarkers(): void
    {
        $healthy = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'AlphaProvider';
            }
            public function isEnabled(): bool
            {
                return true;
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
                return new MonitoringResult('AlphaProvider', Status::Healthy);
            }
        };

        $unhealthy = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'BetaProvider';
            }
            public function isEnabled(): bool
            {
                return true;
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
                return new MonitoringResult('BetaProvider', Status::Unhealthy);
            }
        };

        $tester = new CommandTester(new MonitoringCommand([$healthy, $unhealthy], $this->createExecutionHandler()));
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

        $tester = new CommandTester(new MonitoringCommand([$plain], $this->createExecutionHandler()));
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('PlainProvider', $display);
        self::assertStringNotContainsString('(cached)', $display);
    }

    #[Test]
    public function commandIsRegisteredWithExpectedNameAndDescription(): void
    {
        $command = new MonitoringCommand([], $this->createExecutionHandler());

        self::assertSame('monitoring:run', $command->getName());
        self::assertSame('This command runs monitoring.', $command->getDescription());
    }

    /**
     * Builds a real execution handler whose cache backend is absent, so every
     * provider runs fresh and is never reported from cache. Warm-cache and
     * {@code --no-cache} behaviour is covered by the functional command test.
     */
    private function createExecutionHandler(): MonitoringExecutionHandler
    {
        return new MonitoringExecutionHandler(new MonitoringCacheManager(new CacheManager()));
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

            public function isEnabled(): bool
            {
                return $this->active;
            }

            public function isActive(): bool
            {
                return $this->active;
            }

            public function execute(): MonitoringResult
            {
                return new MonitoringResult($this->name, $this->healthy ? Status::Healthy : Status::Unhealthy);
            }
        };
    }
}
