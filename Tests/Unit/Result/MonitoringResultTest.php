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
        $result = new MonitoringResult($name, $isHealthy, $reason, $subResults);

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
    public function setHealthyModifiesHealthStatusAndReturnsSelf(): void
    {
        $result = new MonitoringResult('test', true);
        self::assertTrue($result->isHealthy());

        $returned = $result->setHealthy(false);
        self::assertFalse($result->isHealthy());
        self::assertSame($result, $returned, 'Should return self for fluent interface');

        $result->setHealthy(true);
        self::assertTrue($result->isHealthy());
    }

    #[Test]
    public function setReasonModifiesReasonAndReturnsSelf(): void
    {
        $result = new MonitoringResult('test', true, 'Initial reason');
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
        $result = new MonitoringResult('parent', true, null, $initialSubResults);

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

    #[Test]
    #[DataProvider('propertyAccessScenarios')]
    public function magicGetterReturnsExpectedPropertyValues(
        string $name,
        bool $isHealthy,
        ?string $reason,
        string $property,
        mixed $expectedValue
    ): void {
        $result = new MonitoringResult($name, $isHealthy, $reason);

        self::assertSame($expectedValue, $result->__get($property));
    }

    #[Test]
    public function magicGetterThrowsForInvalidProperty(): void
    {
        $result = new MonitoringResult('test', true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property "invalid" does not exist');

        $result->__get('invalid');
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
        $result = new MonitoringResult($name, $isHealthy, $reason);
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
                self::assertArrayHasKey('isHealthy', $subArray);
                self::assertArrayHasKey('description', $subArray);
            }
        } else {
            self::assertArrayNotHasKey('subResults', $array, 'Should not have subResults key when no sub-results exist');
        }
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
        $result = new MonitoringResult('main', $mainHealth);

        /**
         * @var array{
         *     name: string,
         *     health: bool,
         *     reason: ?string,
         * } $subData
         */
        foreach ($subResultsData as $subData) {
            $subResult = new MonitoringResult($subData['name'], $subData['health'], $subData['reason'] ?? null);
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
                new MonitoringResult('sub-1', true),
                new MonitoringResult('sub-2', false),
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
                new MonitoringResult('added-1', true),
                new MonitoringResult('added-2', false),
            ],
        ];

        yield 'some initial, add more' => [
            [new MonitoringResult('initial', true)],
            [
                new MonitoringResult('added-1', false),
                new MonitoringResult('added-2', true),
            ],
        ];

        yield 'initial only, add none' => [
            [
                new MonitoringResult('initial-1', true),
                new MonitoringResult('initial-2', false),
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
                new MonitoringResult('service-1', true, 'Service 1 OK'),
                new MonitoringResult('service-2', false, 'Service 2 failed'),
                new MonitoringResult('service-3', true, null),
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
