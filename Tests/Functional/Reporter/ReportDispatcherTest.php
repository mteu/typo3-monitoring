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

namespace mteu\Monitoring\Tests\Functional\Reporter;

use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Reporter\NotificationDecision;
use mteu\Monitoring\Reporter\ReportDispatcher;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportThreshold;
use mteu\Monitoring\Tests\Functional\Fixtures\ConfigurableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\RecordingReporter;
use mteu\Monitoring\Tests\Functional\Fixtures\ThrowingReporter;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;

/**
 * ReportDispatcherTest.
 *
 * Exercises the dispatcher's dedup state machine and reporter fan-out against
 * the real database-backed notification cache: threshold gating, reminder
 * throttling, fingerprint changes, retry-on-failed-delivery and isolation of
 * misbehaving reporters.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReportDispatcher::class)]
final class ReportDispatcherTest extends MonitoringFunctionalTestCase
{
    private const int REMINDER_INTERVAL = 100;

    protected function setUp(): void
    {
        parent::setUp();

        // Guarantee a clean dedup state per test.
        $this->get(CacheManager::class)->getCache('typo3_monitoring_notification')->flush();
    }

    #[Test]
    public function reporterThresholdsGateWhichDecisionsAreDelivered(): void
    {
        $always = new RecordingReporter(ReportThreshold::Always);
        $unhealthy = new RecordingReporter(ReportThreshold::Unhealthy);
        $stateChange = new RecordingReporter(ReportThreshold::StateChange);
        $reporters = [$always, $unhealthy, $stateChange];

        $provider = new ConfigurableProvider('alpha', healthy: false);

        // New outage: every threshold accepts the initial unhealthy notice.
        self::assertSame(
            NotificationDecision::Unhealthy,
            $this->dispatch([$provider], $reporters, 1_000),
        );

        // Reminder: only Always and Unhealthy accept.
        self::assertSame(
            NotificationDecision::Reminder,
            $this->dispatch([$provider], $reporters, 1_000 + self::REMINDER_INTERVAL),
        );

        // Recovery: only Always and StateChange accept.
        $provider->setHealthy(true);
        self::assertSame(
            NotificationDecision::Recovered,
            $this->dispatch([$provider], $reporters, 1_000 + self::REMINDER_INTERVAL + 5),
        );

        self::assertSame(3, $always->receivedCount());
        self::assertSame(2, $unhealthy->receivedCount());
        self::assertSame(2, $stateChange->receivedCount());
    }

    #[Test]
    public function repeatedFailureWithinReminderIntervalIsThrottled(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        $this->dispatch([$provider], [$reporter], 1_000);

        self::assertSame(
            NotificationDecision::None,
            $this->dispatch([$provider], [$reporter], 1_000 + self::REMINDER_INTERVAL - 1),
        );
        self::assertSame(1, $reporter->receivedCount());
    }

    #[Test]
    public function failedDeliveryDoesNotAdvanceReminderClock(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always, deliverSuccessfully: false);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        $this->dispatch([$provider], [$reporter], 1_000);

        // The send failed, so the next run must retry instead of throttling.
        self::assertSame(
            NotificationDecision::Reminder,
            $this->dispatch([$provider], [$reporter], 1_005),
        );
        self::assertSame(2, $reporter->receivedCount());
    }

    #[Test]
    public function changedFailureSetTriggersNewUnhealthyNotification(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always);
        $alpha = new ConfigurableProvider('alpha', healthy: false);
        $beta = new ConfigurableProvider('beta', healthy: false);

        $this->dispatch([$alpha], [$reporter], 1_000);

        // A second provider starts failing well within the reminder interval:
        // the fingerprint changed, so this is a fresh alert, not a reminder.
        self::assertSame(
            NotificationDecision::Unhealthy,
            $this->dispatch([$alpha, $beta], [$reporter], 1_010),
        );
        self::assertSame(2, $reporter->receivedCount());
    }

    #[Test]
    public function throwingReporterDoesNotPreventDeliveryThroughOtherReporters(): void
    {
        $recording = new RecordingReporter(ReportThreshold::Always);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        self::assertSame(
            NotificationDecision::Unhealthy,
            $this->dispatch([$provider], [new ThrowingReporter(), $recording], 1_000),
        );
        self::assertSame(1, $recording->receivedCount());

        // The recording reporter delivered successfully, so the run counts as
        // notified and the next run within the interval is throttled.
        self::assertSame(
            NotificationDecision::None,
            $this->dispatch([$provider], [new ThrowingReporter(), $recording], 1_005),
        );
    }

    #[Test]
    public function inactiveReporterIsSkipped(): void
    {
        $inactive = new RecordingReporter(ReportThreshold::Always, active: false);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        self::assertSame(
            NotificationDecision::Unhealthy,
            $this->dispatch([$provider], [$inactive], 1_000),
        );
        self::assertSame(0, $inactive->receivedCount());
    }

    #[Test]
    public function inactiveProviderIsExcludedFromEvaluation(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always);
        $inactiveFailing = new ConfigurableProvider('alpha', healthy: false, active: false);
        $activeHealthy = new ConfigurableProvider('beta', healthy: true);

        self::assertSame(
            NotificationDecision::None,
            $this->dispatch([$inactiveFailing, $activeHealthy], [$reporter], 1_000),
        );
        self::assertSame(0, $reporter->receivedCount());
    }

    #[Test]
    public function recoveryNoticeIsSuppressedWhenNotifyOnRecoveryIsDisabled(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        $this->dispatch([$provider], [$reporter], 1_000, notifyOnRecovery: false);

        $provider->setHealthy(true);
        self::assertSame(
            NotificationDecision::None,
            $this->dispatch([$provider], [$reporter], 1_005, notifyOnRecovery: false),
        );
        self::assertSame(1, $reporter->receivedCount());
    }

    #[Test]
    public function missingStateSuppressesFirstAlertWhenFailingClosed(): void
    {
        $reporter = new RecordingReporter(ReportThreshold::Always);
        $provider = new ConfigurableProvider('alpha', healthy: false);

        self::assertSame(
            NotificationDecision::None,
            $this->dispatch([$provider], [$reporter], 1_000, failOpenOnMissingState: false),
        );
        self::assertSame(0, $reporter->receivedCount());
    }

    /**
     * Runs a freshly wired dispatcher at the given wall-clock timestamp so the
     * dedup state machine sees advancing time across runs within one test.
     *
     * @param list<ConfigurableProvider> $providers
     * @param list<Reporter> $reporters
     */
    private function dispatch(
        array $providers,
        array $reporters,
        int $timestamp,
        bool $notifyOnRecovery = true,
        bool $failOpenOnMissingState = true,
    ): NotificationDecision {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp($timestamp)));

        $dispatcher = new ReportDispatcher(
            $providers,
            $reporters,
            $this->get(MonitoringExecutionHandler::class),
            $this->get(NotificationStateCacheManager::class),
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                new EmailReporterConfiguration(),
                new ReportDispatcherConfiguration(
                    reminderInterval: self::REMINDER_INTERVAL,
                    notifyOnRecovery: $notifyOnRecovery,
                    failOpenOnMissingState: $failOpenOnMissingState,
                ),
            ),
            $context,
            new NullLogger(),
        );

        return $dispatcher->dispatch();
    }
}
