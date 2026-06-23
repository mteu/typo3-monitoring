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
use mteu\Monitoring\Tests\Unit\Fixtures\Language\XliffLanguageServiceFactoryTrait;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerHeartbeat;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerTaskRepository;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Http\Uri;

/**
 * SchedulerProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SchedulerProvider::class)]
#[Framework\Attributes\UsesClass(SchedulerTask::class)]
final class SchedulerProviderTest extends Framework\TestCase
{
    use XliffLanguageServiceFactoryTrait;

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
    public function isEnabledReturnsTrueByDefault(): void
    {
        self::assertTrue($this->createProvider()->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenDisabledInConfiguration(): void
    {
        $provider = $this->createProvider(
            configuration: new SchedulerProviderConfiguration(enabled: false),
        );

        self::assertFalse($provider->isEnabled());
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
    public function staleHeartbeatReasonStatesLastRunFormatted(): void
    {
        // 10 000 s = 2 h 46 m 40 s
        $result = $this->createProvider(lastRunEnd: self::NOW - 10_000)->execute();

        $reason = (string)$result->getSubResults()[0]->getReason();
        self::assertStringContainsString('2 hrs ago', $reason);
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

    #[Test]
    public function usesSchedulerModuleRouteWhenItExists(): void
    {
        $uriBuilder = self::createMock(BackendUriBuilder::class);
        $uriBuilder
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('scheduler_manage', ['action' => 'edit', 'uid' => 42])
            ->willReturn(new Uri('/typo3/module/scheduler?action=edit&uid=42'))
        ;

        $link = $this->createProviderWithRouting(hasSchedulerRoute: true, uriBuilder: $uriBuilder)->buildEditLink(42);

        self::assertSame('/typo3/module/scheduler?action=edit&uid=42', $link);
    }

    #[Test]
    public function usesRecordEditRouteWhenSchedulerModuleRouteIsMissing(): void
    {
        $uriBuilder = self::createMock(BackendUriBuilder::class);
        $uriBuilder
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('record_edit', ['edit' => ['tx_scheduler_task' => [42 => 'edit']]])
            ->willReturn(new Uri('/typo3/record/edit?token=abc'))
        ;

        $link = $this->createProviderWithRouting(hasSchedulerRoute: false, uriBuilder: $uriBuilder)->buildEditLink(42);

        self::assertSame('/typo3/record/edit?token=abc', $link);
    }

    #[Test]
    public function buildEditLinkReturnsNullWhenNoRouteCanBeResolved(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());

        self::assertNull($this->createProviderWithRouting(hasSchedulerRoute: false, uriBuilder: $uriBuilder)->buildEditLink(42));
    }

    #[Test]
    public function renderEditLinkWrapsTheUriInAnAnchorTag(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturn(new Uri('/typo3/record/edit?uid=42'));

        $rendered = $this->createProviderWithRouting(hasSchedulerRoute: false, uriBuilder: $uriBuilder)
            ->renderEditLink(42, '#42 (Import task)');

        self::assertSame('<a href="/typo3/record/edit?uid=42">#42 (Import task)</a>', $rendered);
    }

    #[Test]
    public function renderEditLinkReturnsNullInsteadOfAnAnchorWithEmptyHref(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());

        $rendered = $this->createProviderWithRouting(hasSchedulerRoute: false, uriBuilder: $uriBuilder)
            ->renderEditLink(42, '#42 (Import task)');

        self::assertNull($rendered);
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
            new MockClock((new \DateTimeImmutable())->setTimestamp(self::NOW)),
            new NullLogger(),
            self::createStub(Router::class),
            $this->createUriBuilder($taskLinkBaseUri),
            $this->createEnglishLanguageServiceFactory(),
        );
    }

    private function createProviderWithRouting(
        bool $hasSchedulerRoute,
        BackendUriBuilder $uriBuilder,
    ): SchedulerProvider {
        $router = self::createStub(Router::class);
        $router->method('hasRoute')->willReturn($hasSchedulerRoute);

        return new SchedulerProvider(
            new SchedulerProviderConfiguration(),
            new InMemorySchedulerTaskRepository(
                failedCount: 0,
                overdueCount: 0,
                failedSample: [],
                overdueSample: [],
                hasRunningTask: false,
            ),
            new InMemorySchedulerHeartbeat(self::NOW - 60),
            new MockClock((new \DateTimeImmutable())->setTimestamp(self::NOW)),
            new NullLogger(),
            $router,
            $uriBuilder,
            $this->createEnglishLanguageServiceFactory(),
        );
    }

    private function createUriBuilder(?string $baseUri): BackendUriBuilder
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);

        if ($baseUri === null) {
            $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());
        } else {
            $uriBuilder->method('buildUriFromRoute')->willReturn(new Uri($baseUri));
        }

        return $uriBuilder;
    }
}
