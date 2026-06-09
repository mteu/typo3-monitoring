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

namespace mteu\Monitoring\Tests\Functional\Command;

use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Command\ReportCommand;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Reporter\ReportDispatcher;
use mteu\Monitoring\Tests\Functional\Fixtures\ConfigurableProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ReportCommandTest.
 *
 * Drives the command end-to-end against the real DI services and the
 * database-backed notification cache, walking a full status lifecycle
 * (healthy → unhealthy → still unhealthy → recovered) so the command's
 * output reflects the persisted dedup state, not just an isolated decision.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReportCommand::class)]
final class ReportCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'monitoring',
        'typed_extconf',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'typo3_monitoring' => [
                        'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                        'options' => [],
                        'groups' => ['system'],
                    ],
                    'typo3_monitoring_notification' => [
                        'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                        'options' => [],
                        'groups' => [],
                    ],
                ],
            ],
        ],
    ];

    private const int REMINDER_INTERVAL = 100;

    private NotificationStateCacheManager $stateStore;
    private MonitoringExecutionHandler $executionHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateStore = $this->get(NotificationStateCacheManager::class);
        $this->executionHandler = $this->get(MonitoringExecutionHandler::class);

        // Guarantee a clean dedup state per test.
        $this->get(CacheManager::class)->getCache('typo3_monitoring_notification')->flush();
    }

    #[Test]
    public function reportsNoNotificationWhenAllProvidersAreHealthy(): void
    {
        $tester = $this->runReport([new ConfigurableProvider('alpha', healthy: true)], 1_000);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No notification dispatched.', $tester->getDisplay());
    }

    #[Test]
    public function reportsUnhealthyStatusOnFirstFailure(): void
    {
        $tester = $this->runReport([new ConfigurableProvider('alpha', healthy: false)], 1_000);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dispatched: unhealthy status.', $tester->getDisplay());
    }

    #[Test]
    public function reportsReminderWhenStillUnhealthyAfterInterval(): void
    {
        $provider = new ConfigurableProvider('alpha', healthy: false);

        $this->runReport([$provider], 1_000);
        $tester = $this->runReport([$provider], 1_000 + self::REMINDER_INTERVAL);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dispatched: reminder for ongoing unhealthy status.', $tester->getDisplay());
    }

    #[Test]
    public function reportsRecoveryWhenStatusReturnsToHealthy(): void
    {
        $provider = new ConfigurableProvider('alpha', healthy: false);

        $this->runReport([$provider], 1_000);

        $provider->setHealthy(true);
        $tester = $this->runReport([$provider], 1_005);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dispatched: recovery notice.', $tester->getDisplay());
    }

    /**
     * Builds and runs a fresh command at the given wall-clock timestamp so the
     * dedup state machine sees advancing time across runs within one test.
     *
     * @param list<ConfigurableProvider> $providers
     */
    private function runReport(array $providers, int $timestamp): CommandTester
    {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp($timestamp)));

        $dispatcher = new ReportDispatcher(
            $providers,
            [], // the command's output reflects the decision regardless of reporters
            $this->executionHandler,
            $this->stateStore,
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                new EmailReporterConfiguration(),
                new ReportDispatcherConfiguration(reminderInterval: self::REMINDER_INTERVAL),
            ),
            $context,
            new NullLogger(),
        );

        $tester = new CommandTester(new ReportCommand($dispatcher));
        $tester->execute([]);

        return $tester;
    }
}
