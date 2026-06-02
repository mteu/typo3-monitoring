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
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Tests\Unit\Fixtures\Clock\FrozenClock;
use mteu\Monitoring\Tests\Unit\Fixtures\Extension\InMemoryExtensionState;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerHeartbeat;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerTaskGateway;
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
final class SchedulerMonitoringTest extends Framework\TestCase
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
    public function executeProducesTwoSubResultsWhenOverdueCheckIsDisabled(): void
    {
        $result = $this->createProvider(
            configuration: new SchedulerProviderConfiguration(overdueThreshold: 0),
        )->execute();

        self::assertCount(2, $result->getSubResults());

        $names = array_map(static fn(Result $r): string => $r->getName(), $result->getSubResults());
        self::assertNotContains('OverdueTasks', $names);
    }

    #[Test]
    public function executeReportsUnhealthyWhenHeartbeatIsStale(): void
    {
        $result = $this->createProvider(lastRunEnd: self::NOW - 10_000)->execute();

        self::assertFalse($result->isHealthy());

        $heartbeat = $result->getSubResults()[0];
        self::assertSame('SchedulerHeartbeat', $heartbeat->getName());
        self::assertFalse($heartbeat->isHealthy());
    }

    #[Test]
    public function staleHeartbeatReasonStatesTheRealAgeNotASingleSecond(): void
    {
        // 10000 s -> "2 hours and 46 minutes". The old %d/formatAge bug printed "1 seconds".
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
    public function executeReportsUnhealthyWhenTasksFailed(): void
    {
        $result = $this->createProvider(failedCount: 3)->execute();

        self::assertFalse($result->isHealthy());

        $failed = $result->getSubResults()[1];
        self::assertSame('FailedTasks', $failed->getName());
        self::assertFalse($failed->isHealthy());
        self::assertStringContainsString('3 task(s)', (string)$failed->getReason());
    }

    #[Test]
    public function overdueReasonIncludesADeepLinkToTheTask(): void
    {
        $result = $this->createProvider(
            overdueCount: 1,
            overdueSample: [new SchedulerTask(7, 'Cleanup task')],
            taskLinkBaseUri: 'https://example.com/typo3/record/edit',
        )->execute();

        $overdue = $result->getSubResults()[2];
        self::assertSame('OverdueTasks', $overdue->getName());

        $reason = (string)$overdue->getReason();
        self::assertStringContainsString('#7 (Cleanup task)', $reason);
        self::assertStringContainsString('https://example.com/typo3/record/edit', $reason);
    }

    /**
     * @param list<SchedulerTask> $overdueSample
     */
    private function createProvider(
        ?SchedulerProviderConfiguration $configuration = null,
        ?int $lastRunEnd = self::NOW - 60,
        int $failedCount = 0,
        int $overdueCount = 0,
        array $overdueSample = [],
        bool $schedulerLoaded = true,
        ?string $taskLinkBaseUri = null,
    ): SchedulerProvider {
        return new SchedulerProvider(
            $configuration ?? new SchedulerProviderConfiguration(),
            new InMemorySchedulerTaskGateway(
                failedCount: $failedCount,
                overdueCount: $overdueCount,
                overdueSample: $overdueSample,
            ),
            new InMemorySchedulerHeartbeat($lastRunEnd),
            new FrozenClock(self::NOW),
            new InMemoryTaskLinkBuilder($taskLinkBaseUri),
            new InMemoryExtensionState(['scheduler' => $schedulerLoaded]),
            new NullLogger(),
        );
    }
}
