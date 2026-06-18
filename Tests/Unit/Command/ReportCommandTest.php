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
use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Command\ReportCommand;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Reporter\ReportDispatcher;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportThreshold;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;

/**
 * ReportCommandTest.
 *
 * Pins the command's only responsibility: translating each dispatcher
 * {@see \mteu\Monitoring\Reporter\NotificationDecision} into the matching
 * console line while always exiting with {@see Command::SUCCESS}. The real
 * dispatcher is driven through an in-memory notification cache so every
 * decision branch is reachable without a database.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReportCommand::class)]
final class ReportCommandTest extends TestCase
{
    private const int REMINDER_INTERVAL = 100;

    /**
     * Shared in-memory notification state, so dispatcher runs within a single
     * test observe each other's persisted state.
     *
     * @var array<string, mixed>
     */
    private array $notificationCache = [];

    #[Test]
    public function commandIsRegisteredWithExpectedNameAndDescription(): void
    {
        $command = $this->createCommand([], 1_000, []);

        self::assertSame('monitoring:report', $command->getName());
        self::assertSame(
            'Evaluates monitoring status and dispatches push notifications to active reporters.',
            $command->getDescription(),
        );
    }

    #[Test]
    public function reportsNoNotificationWhenEverythingIsHealthy(): void
    {
        $tester = new CommandTester(
            $this->createCommand([$this->provider('alpha', healthy: true)], 1_000, [$this->reporter()]),
        );

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No notification dispatched.', $tester->getDisplay());
    }

    #[Test]
    public function reportsUnhealthyStatusOnFirstFailure(): void
    {
        $tester = new CommandTester(
            $this->createCommand([$this->provider('alpha', healthy: false)], 1_000, [$this->reporter()]),
        );

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dispatched: unhealthy status.', $tester->getDisplay());
    }

    #[Test]
    public function reportsFailureWhenDispatchDecidedButNoReporterDelivers(): void
    {
        $tester = new CommandTester(
            $this->createCommand([$this->provider('alpha', healthy: false)], 1_000, [$this->reporter(active: false)]),
        );

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No report dispatched', $tester->getDisplay());
        self::assertStringContainsString('no active reporter', $tester->getDisplay());
        self::assertStringNotContainsString('Dispatched: unhealthy status.', $tester->getDisplay());
    }

    #[Test]
    public function reportsReminderWhenStillUnhealthyAfterInterval(): void
    {
        $this->createTester([$this->provider('alpha', healthy: false)], 1_000)->execute([]);

        $tester = $this->createTester([$this->provider('alpha', healthy: false)], 1_000 + self::REMINDER_INTERVAL);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dispatched: reminder for ongoing unhealthy status.', $tester->getDisplay());
    }

    #[Test]
    public function reportsRecoveryAfterReturningToHealthy(): void
    {
        $this->createTester([$this->provider('alpha', healthy: false)], 1_000)->execute([]);

        $tester = $this->createTester([$this->provider('alpha', healthy: true)], 1_005);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dispatched: recovery notice.', $tester->getDisplay());
    }

    /**
     * @param list<MonitoringProvider> $providers
     */
    private function createTester(array $providers, int $timestamp): CommandTester
    {
        return new CommandTester($this->createCommand($providers, $timestamp, [$this->reporter()]));
    }

    /**
     * @param list<MonitoringProvider> $providers
     * @param list<Reporter> $reporters
     */
    private function createCommand(array $providers, int $timestamp, array $reporters): ReportCommand
    {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp($timestamp)));

        $dispatcher = new ReportDispatcher(
            $providers,
            $reporters,
            new MonitoringExecutionHandler(new MonitoringCacheManager(new CacheManager())),
            new NotificationStateCacheManager($this->inMemoryCacheManager()),
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                new EmailReporterConfiguration(),
                new ReportDispatcherConfiguration(reminderInterval: self::REMINDER_INTERVAL),
            ),
            $context,
            new NullLogger(),
        );

        return new ReportCommand($dispatcher);
    }

    /**
     * A CacheManager whose notification cache lives in process memory, so the
     * dedup state machine can persist and reload state between dispatcher runs.
     */
    private function inMemoryCacheManager(): CacheManager
    {
        $frontend = self::createStub(FrontendInterface::class);
        $frontend->method('has')->willReturnCallback(
            fn(string $entry): bool => array_key_exists($entry, $this->notificationCache),
        );
        $frontend->method('get')->willReturnCallback(
            fn(string $entry): mixed => $this->notificationCache[$entry] ?? false,
        );
        $frontend->method('set')->willReturnCallback(
            function (string $entry, mixed $variable): void {
                $this->notificationCache[$entry] = $variable;
            },
        );

        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')->willReturn($frontend);

        return $cacheManager;
    }

    // A reporter that accepts every decision and, when active, delivers successfully.
    private function reporter(bool $active = true): Reporter
    {
        return new readonly class ($active) implements Reporter {
            public function __construct(
                private bool $active,
            ) {}

            public function isActive(): bool
            {
                return $this->active;
            }

            public function getThreshold(): ReportThreshold
            {
                return ReportThreshold::Always;
            }

            public function report(ReportContext $context): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'Fake Reporter';
            }

            public static function getPriority(): int
            {
                return 0;
            }
        };
    }

    private function provider(string $name, bool $healthy): MonitoringProvider
    {
        return new readonly class ($name, $healthy) implements MonitoringProvider {
            public function __construct(
                private string $name,
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
                return true;
            }

            public function isActive(): bool
            {
                return true;
            }

            public function execute(): MonitoringResult
            {
                return new MonitoringResult($this->name, $this->healthy);
            }
        };
    }
}
