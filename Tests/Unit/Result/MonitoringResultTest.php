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

namespace mteu\Monitoring\Tests\Unit\Result;

use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Tests\Unit\MonitoringTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * MonitoringResultTest.
 *
 * Tests the MonitoringResult business logic including health calculation and sub-result handling.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringResult::class)]
final class MonitoringResultTest extends MonitoringTestCase
{
    #[Test]
    public function implementsResultInterface(): void
    {
        $result = $this->createHealthyResult();

        self::assertInstanceOf(Result::class, $result);
    }

    /**
     * @param list<MonitoringResult> $subResults
     */
    #[Test]
    #[DataProvider('constructorDataProvider')]
    public function constructorInitializesStateFromParameters(
        string $name,
        bool $isHealthy,
        ?string $reason,
        array $subResults
    ): void {
        $result = new MonitoringResult($name, $isHealthy ? Status::Healthy : Status::Unhealthy, $reason, $subResults);

        self::assertSame($name, $result->getName());
        self::assertSame($reason, $result->getReason());
        self::assertSame($subResults, $result->getSubResults());
        self::assertSame(count($subResults) > 0, $result->hasSubResults());
    }

    /**
     * @param list<bool> $subHealthStatuses
     */
    #[Test]
    #[DataProvider('healthCalculationScenarios')]
    public function calculatesFinalHealthFromMainAndSubResults(
        bool $initialHealth,
        array $subHealthStatuses,
        bool $expectedHealth
    ): void {
        $result = $this->createResultWithSubResults($subHealthStatuses, 'test', $initialHealth);

        self::assertSame($expectedHealth, $result->isHealthy());
    }

    #[Test]
    public function setStatusModifiesStatusAndReturnsSelf(): void
    {
        $result = new MonitoringResult('test', Status::Healthy);
        self::assertTrue($result->isHealthy());

        $returned = $result->setStatus(Status::Unhealthy);
        self::assertFalse($result->isHealthy());
        self::assertSame($result, $returned, 'Should return self for fluent interface');

        $result->setStatus(Status::Degraded);
        self::assertSame(Status::Degraded, $result->getStatus());
        self::assertTrue($result->isHealthy(), 'degraded is not an outage');
    }

    #[Test]
    public function setReasonModifiesReasonAndReturnsSelf(): void
    {
        $result = new MonitoringResult('test', Status::Healthy, 'Initial reason');
        self::assertSame('Initial reason', $result->getReason());

        $returned = $result->setReason('Updated reason');
        self::assertSame('Updated reason', $result->getReason());
        self::assertSame($result, $returned, 'Should return self for fluent interface');
    }

    /**
     * @param list<MonitoringResult> $initialSubResults
     * @param list<MonitoringResult> $additionalResults
     */
    #[Test]
    #[DataProvider('subResultsProvider')]
    public function managesSubResultCollectionWithProperOrdering(array $initialSubResults, array $additionalResults): void
    {
        $result = new MonitoringResult('parent', Status::Healthy, null, $initialSubResults);

        self::assertCount(count($initialSubResults), $result->getSubResults());
        self::assertSame(count($initialSubResults) > 0, $result->hasSubResults());

        foreach ($additionalResults as $subResult) {
            $returned = $result->addSubResult($subResult);
            self::assertSame($subResult, $returned, 'Should return the added sub-result');
        }

        $totalExpected = count($initialSubResults) + count($additionalResults);
        self::assertCount($totalExpected, $result->getSubResults());
        self::assertSame($totalExpected > 0, $result->hasSubResults());

        $allExpected = array_merge($initialSubResults, $additionalResults);
        self::assertSame($allExpected, $result->getSubResults(), 'Order should be maintained');
    }

    /**
     * @param list<bool> $subHealthStatuses
     */
    #[Test]
    #[DataProvider('healthCalculationScenarios')]
    public function getIsHealthyMatchesIsHealthyIncludingSubResultFolding(
        bool $initialHealth,
        array $subHealthStatuses,
        bool $expectedHealth
    ): void {
        $result = $this->createResultWithSubResults($subHealthStatuses, 'test', $initialHealth);

        self::assertSame($expectedHealth, $result->getIsHealthy());
        self::assertSame($result->isHealthy(), $result->getIsHealthy());
    }

    /**
     * @param list<MonitoringResult> $subResults
     */
    #[Test]
    #[DataProvider('serializationProvider')]
    public function serializationMethodsProduceConsistentArrayOutput(
        string $name,
        bool $isHealthy,
        ?string $reason,
        array $subResults
    ): void {
        $result = new MonitoringResult($name, $isHealthy ? Status::Healthy : Status::Unhealthy, $reason);
        foreach ($subResults as $subResult) {
            $result->addSubResult($subResult);
        }

        $this->assertSerializationConsistency($result);
        $this->assertResultProperties($result, $name, $isHealthy, $reason);

        $array = $result->toArray();
        if (count($subResults) > 0) {
            self::assertArrayHasKey('subResults', $array);
            self::assertCount(count($subResults), ($array['subResults'] ?? []));

            foreach (($array['subResults'] ?? []) as $subArray) {
                self::assertIsArray($subArray);
                self::assertArrayHasKey('name', $subArray);
                self::assertArrayHasKey('status', $subArray);
                self::assertArrayHasKey('description', $subArray);
            }
        } else {
            self::assertArrayNotHasKey('subResults', $array, 'Should not have subResults key when no sub-results exist');
        }
    }

    /**
     * A parent constructed healthy but carrying an unhealthy sub-result (the shape every
     * sub-result-driven provider produces, e.g. SchedulerProvider) must serialize as
     * unhealthy. Regression guard: toArray()/jsonSerialize() previously emitted the raw
     * constructor flag instead of the aggregated status, reporting healthy while
     * a sub-check was failing.
     */
    #[Test]
    public function serializationReflectsAggregatedSubResultHealth(): void
    {
        $result = new MonitoringResult('Scheduler', Status::Healthy);
        $result->addSubResult(new MonitoringResult('Heartbeat', Status::Healthy));
        $result->addSubResult(new MonitoringResult('Overdue Tasks', Status::Unhealthy, 'A task is overdue.'));

        self::assertFalse($result->isHealthy());
        self::assertSame('unhealthy', $result->toArray()['status']);
        self::assertSame('unhealthy', $result->jsonSerialize()['status']);

        $decoded = json_decode((string)json_encode($result), true);
        self::assertIsArray($decoded);
        self::assertSame('unhealthy', $decoded['status']);
    }

    /**
     * @param list<array{name: string, health: bool, reason?: string|null}> $subResultsData
     */
    #[Test]
    #[DataProvider('healthWithSubResultsProvider')]
    public function healthCalculationConsidersSubResultHealthStatus(
        bool $mainHealth,
        array $subResultsData,
        bool $expectedFinalHealth
    ): void {
        $result = new MonitoringResult('main', $mainHealth ? Status::Healthy : Status::Unhealthy);

        /**
         * @var array{
         *     name: string,
         *     health: bool,
         *     reason: ?string,
         * } $subData
         */
        foreach ($subResultsData as $subData) {
            $subResult = new MonitoringResult($subData['name'], $subData['health'] ? Status::Healthy : Status::Unhealthy, $subData['reason'] ?? null);
            $result->addSubResult($subResult);
        }

        self::assertSame($expectedFinalHealth, $result->isHealthy());
    }

    /**
     * @return \Generator<string, array{string, bool, string|null, array<MonitoringResult>}>
     */
    public static function constructorDataProvider(): \Generator
    {
        yield 'minimal constructor' => [
            'service-name',
            true,
            null,
            [],
        ];

        yield 'full constructor with reason' => [
            'database-check',
            false,
            'Connection timeout',
            [],
        ];

        yield 'constructor with sub-results' => [
            'complex-check',
            true,
            'All systems operational',
            [
                new MonitoringResult('sub-1', Status::Healthy),
                new MonitoringResult('sub-2', Status::Unhealthy),
            ],
        ];
    }

    /**
     * @return \Generator<string, array{array<MonitoringResult>, array<MonitoringResult>}>
     */
    public static function subResultsProvider(): \Generator
    {
        yield 'no initial, add some' => [
            [],
            [
                new MonitoringResult('added-1', Status::Healthy),
                new MonitoringResult('added-2', Status::Unhealthy),
            ],
        ];

        yield 'some initial, add more' => [
            [new MonitoringResult('initial', Status::Healthy)],
            [
                new MonitoringResult('added-1', Status::Unhealthy),
                new MonitoringResult('added-2', Status::Healthy),
            ],
        ];

        yield 'initial only, add none' => [
            [
                new MonitoringResult('initial-1', Status::Healthy),
                new MonitoringResult('initial-2', Status::Unhealthy),
            ],
            [],
        ];
    }

    /**
     * @return \Generator<string, array{string, bool, string|null, array<MonitoringResult>}>
     */
    public static function serializationProvider(): \Generator
    {
        yield 'simple result' => [
            'api-check',
            true,
            'API responding correctly',
            [],
        ];

        yield 'simple unhealthy result' => [
            'db-check',
            false,
            'Database connection failed',
            [],
        ];

        yield 'result with null reason' => [
            'basic-check',
            true,
            null,
            [],
        ];

        yield 'result with sub-results' => [
            'comprehensive-check',
            false,
            'Some services down',
            [
                new MonitoringResult('service-1', Status::Healthy, 'Service 1 OK'),
                new MonitoringResult('service-2', Status::Unhealthy, 'Service 2 failed'),
                new MonitoringResult('service-3', Status::Healthy, null),
            ],
        ];
    }

    /**
     * @return \Generator<string, array{bool, array<array{name: string, health: bool, reason: string}>, bool}>
     */
    public static function healthWithSubResultsProvider(): \Generator
    {
        yield 'main healthy, all subs healthy' => [
            true,
            [
                ['name' => 'sub-1', 'health' => true, 'reason' => 'OK'],
                ['name' => 'sub-2', 'health' => true, 'reason' => 'Good'],
            ],
            true,
        ];

        yield 'main healthy, one sub unhealthy' => [
            true,
            [
                ['name' => 'sub-1', 'health' => true, 'reason' => 'OK'],
                ['name' => 'sub-2', 'health' => false, 'reason' => 'Failed'],
            ],
            false,
        ];

        yield 'main unhealthy, all subs healthy' => [
            false,
            [
                ['name' => 'sub-1', 'health' => true, 'reason' => 'OK'],
                ['name' => 'sub-2', 'health' => true, 'reason' => 'Good'],
            ],
            false,
        ];

        yield 'main healthy, no subs' => [
            true,
            [],
            true,
        ];

        yield 'main unhealthy, no subs' => [
            false,
            [],
            false,
        ];
    }
}
