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

namespace mteu\Monitoring\Tests\Unit\Provider;

use mteu\Monitoring\Configuration\Provider\SchedulerProviderConfiguration;
use mteu\Monitoring\Provider\Scheduler\SchedulerProvider;
use mteu\Monitoring\Provider\Scheduler\SchedulerTask;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Tests\Unit\Fixtures\Clock\FrozenClock;
use mteu\Monitoring\Tests\Unit\Fixtures\Extension\InMemoryExtensionState;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerHeartbeat;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerTaskRepository;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemoryTaskLinkBuilder;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

/**
 * SchedulerMonitoringProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SchedulerProvider::class)]
#[Framework\Attributes\UsesClass(SchedulerTask::class)]
final class SchedulerProviderTest extends Framework\TestCase
{
    private const int NOW = 1_700_000_000;

    #[Test]
    public function getName(): void
    {
        self::assertSame('Scheduler', $this->createProvider()->getName());
    }

    #[Test]
    public function getDescriptionMentionsTheScheduler(): void
    {
        self::assertStringContainsString('scheduler', $this->createProvider()->getDescription());
    }

    #[Test]
    public function isActiveReturnsFalseWhenDisabledInConfiguration(): void
    {
        $provider = $this->createProvider(
            configuration: new SchedulerProviderConfiguration(enabled: false),
        );

        self::assertFalse($provider->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenSchedulerExtensionIsNotLoaded(): void
    {
        self::assertFalse($this->createProvider(schedulerLoaded: false)->isActive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenEnabledAndSchedulerExtensionIsLoaded(): void
    {
        self::assertTrue($this->createProvider(schedulerLoaded: true)->isActive());
    }

    #[Test]
    public function executeProducesThreeSubResultsByDefault(): void
    {
        $result = $this->createProvider()->execute();

        self::assertCount(3, $result->getSubResults());
    }

    #[Test]
    public function executeReportsHealthyWhenHeartbeatIsRecentAndNoTasksFailedOrOverdue(): void
    {
        $result = $this->createProvider()->execute();

        self::assertInstanceOf(MonitoringResult::class, $result);
        self::assertTrue($result->isHealthy());
    }

    #[Test]
    public function executeReportsUnhealthyWhenHeartbeatIsStale(): void
    {
        $result = $this->createProvider(lastRunEnd: self::NOW - 10_000)->execute();

        self::assertFalse($result->isHealthy());

        $heartbeat = $result->getSubResults()[0];
        self::assertSame('Scheduler', $heartbeat->getName());
        self::assertFalse($heartbeat->isHealthy());
    }

    #[Test]
    // @todo: remove date calcuation. replace with fluid viewhelper
    public function staleHeartbeatReasonStatesTheRealAgeNotASingleSecond(): void
    {
        // 10 000 s = 2 h 46 m 40 s; formatAge drops seconds when minutes > 0 → "2 hours and 46 minutes".
        $result = $this->createProvider(lastRunEnd: self::NOW - 10_000)->execute();

        $reason = (string)$result->getSubResults()[0]->getReason();

        self::assertStringContainsString('2 hours and 46 minutes ago', $reason);
        self::assertStringNotContainsString('1 seconds ago', $reason);
    }

    #[Test]
    public function executeReportsUnhealthyWhenNoRunWasRecorded(): void
    {
        $result = $this->createProvider(lastRunEnd: null)->execute();

        self::assertFalse($result->isHealthy());
    }

    #[Test]
    public function staleHeartbeatStaysHealthyWhileATaskIsRunning(): void
    {
        $result = $this->createProvider(
            lastRunEnd: self::NOW - 10_000,
            hasRunningTask: true,
        )->execute();

        $heartbeat = $result->getSubResults()[0];
        self::assertSame('Scheduler', $heartbeat->getName());
        self::assertTrue($heartbeat->isHealthy());
        self::assertStringContainsString('currently running', (string)$heartbeat->getReason());
    }

    #[Test]
    public function missingLastRunStaysHealthyWhileATaskIsRunning(): void
    {
        $result = $this->createProvider(
            lastRunEnd: null,
            hasRunningTask: true,
        )->execute();

        self::assertTrue($result->getSubResults()[0]->isHealthy());
    }

    #[Test]
    public function executeReportsUnhealthyWhenTasksFailed(): void
    {
        $result = $this->createProvider(failedCount: 3)->execute();
        $failed = $result->getSubResults()[2];
        self::assertStringContainsString('3 tasks', (string)$failed->getReason());

        $result = $this->createProvider(failedCount: 1)->execute();
        $failed = $result->getSubResults()[2];
        self::assertStringContainsString('1 task', (string)$failed->getReason());

        self::assertSame('Failed Tasks', $failed->getName());
        self::assertFalse($failed->isHealthy());
    }

    #[Test]
    public function unhealthyResultCarriesTopLevelReason(): void
    {
        $result = $this->createProvider(failedCount: 1)->execute();

        self::assertFalse($result->isHealthy());
        self::assertStringContainsString('One or more scheduler health checks failed. See sub-results for details.', (string)$result->getReason());
    }

    #[Test]
    public function overdueReasonIncludesADeepLinkToTheTask(): void
    {
        $result = $this->createProvider(
            overdueCount: 1,
            overdueSample: [new SchedulerTask(7, 'Cleanup task')],
            taskLinkBaseUri: 'https://example.com/typo3/record/edit',
        )->execute();

        $overdue = $result->getSubResults()[1];
        self::assertSame('Overdue Tasks', $overdue->getName());

        $reason = (string)$overdue->getReason();
        self::assertStringContainsString('#7 (Cleanup task)', $reason);
        self::assertStringContainsString('https://example.com/typo3/record/edit', $reason);
    }

    #[Test]
    public function failedReasonIncludesADeepLinkToTheTask(): void
    {
        $result = $this->createProvider(
            failedCount: 1,
            failedSample: [new SchedulerTask(42, 'Import task')],
            taskLinkBaseUri: 'https://example.com/typo3/record/edit',
        )->execute();

        $failed = $result->getSubResults()[2];
        self::assertSame('Failed Tasks', $failed->getName());

        $reason = (string)$failed->getReason();
        self::assertStringContainsString('#42 (Import task)', $reason);
        self::assertStringContainsString('https://example.com/typo3/record/edit', $reason);
    }

    #[Test]
    public function disabledHeartbeatCheckStaysHealthyWithoutAnyRecordedRun(): void
    {
        $result = $this->createProvider(
            configuration: new SchedulerProviderConfiguration(heartbeatThreshold: 0),
            lastRunEnd: null,
        )->execute();

        $heartbeat = $result->getSubResults()[0];
        self::assertTrue($heartbeat->isHealthy());
        self::assertStringContainsString('disabled', (string)$heartbeat->getReason());
    }

    #[Test]
    public function disabledOverdueCheckStaysHealthyDespiteOverdueTasks(): void
    {
        $result = $this->createProvider(
            configuration: new SchedulerProviderConfiguration(overdueThreshold: 0),
            overdueCount: 5,
        )->execute();

        $overdue = $result->getSubResults()[1];
        self::assertTrue($overdue->isHealthy());
        self::assertStringContainsString('disabled', (string)$overdue->getReason());
    }

    #[Test]
    public function heartbeatAgeEqualToTheThresholdIsStillHealthy(): void
    {
        $configuration = new SchedulerProviderConfiguration();

        $result = $this->createProvider(
            configuration: $configuration,
            lastRunEnd: self::NOW - $configuration->heartbeatThreshold,
        )->execute();

        self::assertTrue($result->getSubResults()[0]->isHealthy());
    }

    #[Test]
    public function sampleFallsBackToThePlainLabelWhenNoLinkCanBeBuilt(): void
    {
        $result = $this->createProvider(
            failedCount: 1,
            failedSample: [new SchedulerTask(42, 'Import task')],
            taskLinkBaseUri: null,
        )->execute();

        $reason = (string)$result->getSubResults()[2]->getReason();
        self::assertStringContainsString('<li>#42 (Import task)</li>', $reason);
        self::assertStringNotContainsString('<a ', $reason);
    }

    /**
     * @param list<SchedulerTask> $overdueSample
     * @param list<SchedulerTask> $failedSample
     */
    private function createProvider(
        ?SchedulerProviderConfiguration $configuration = null,
        ?int $lastRunEnd = self::NOW - 60,
        int $failedCount = 0,
        int $overdueCount = 0,
        array $overdueSample = [],
        array $failedSample = [],
        bool $schedulerLoaded = true,
        ?string $taskLinkBaseUri = null,
        bool $hasRunningTask = false,
    ): SchedulerProvider {
        return new SchedulerProvider(
            $configuration ?? new SchedulerProviderConfiguration(),
            new InMemorySchedulerTaskRepository(
                failedCount: $failedCount,
                overdueCount: $overdueCount,
                failedSample: $failedSample,
                overdueSample: $overdueSample,
                hasRunningTask: $hasRunningTask,
            ),
            new InMemorySchedulerHeartbeat($lastRunEnd),
            new FrozenClock(self::NOW),
            new InMemoryTaskLinkBuilder($taskLinkBaseUri),
            new InMemoryExtensionState(['scheduler' => $schedulerLoaded]),
            new NullLogger(),
        );
    }
}
