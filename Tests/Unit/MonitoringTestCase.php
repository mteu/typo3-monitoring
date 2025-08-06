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

namespace mteu\Monitoring\Tests\Unit;

use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use PHPUnit\Framework\TestCase;

/**
 * MonitoringTestCase.
 *
 * Abstract base class providing common helper methods for monitoring tests.
 * Reduces boilerplate and ensures consistent test patterns across the suite.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract class MonitoringTestCase extends TestCase
{
    /**
     * Creates a healthy monitoring result with optional customization.
     *
     * @param string $name Service name for the result
     * @param string|null $reason Optional reason/description
     * @return MonitoringResult
     */
    final protected function createHealthyResult(string $name = 'test-service', ?string $reason = null): MonitoringResult
    {
        return new MonitoringResult($name, true, $reason);
    }

    /**
     * Creates an unhealthy monitoring result with optional customization.
     *
     * @param string $name Service name for the result
     * @param string|null $reason Optional reason/description
     * @return MonitoringResult
     */
    final protected function createUnhealthyResult(string $name = 'test-service', ?string $reason = null): MonitoringResult
    {
        return new MonitoringResult($name, false, $reason);
    }

    /**
     * Creates a monitoring result with multiple sub-results based on health statuses.
     *
     * @param list<bool> $subHealthStatuses Array of boolean health statuses for sub-results
     * @param string $parentName Name of the parent service
     * @param bool $parentHealthy Health status of the parent service
     * @return MonitoringResult
     */
    final protected function createResultWithSubResults(
        array $subHealthStatuses,
        string $parentName = 'parent-service',
        bool $parentHealthy = true
    ): MonitoringResult {
        $result = new MonitoringResult($parentName, $parentHealthy);

        foreach ($subHealthStatuses as $i => $healthy) {
            $subResult = new MonitoringResult("sub-service-{$i}", $healthy);
            $result->addSubResult($subResult);
        }

        return $result;
    }

    /**
     * Creates a list of monitoring results with specified health statuses.
     *
     * @param list<bool> $healthStatuses Array of health statuses
     * @param string $namePrefix Prefix for service names
     * @return list<MonitoringResult>
     */
    final protected function createResultList(array $healthStatuses, string $namePrefix = 'service'): array
    {
        $results = [];
        foreach ($healthStatuses as $i => $healthy) {
            $results[] = new MonitoringResult("{$namePrefix}-{$i}", $healthy);
        }
        return $results;
    }

    /**
     * Asserts that a result has the expected basic properties.
     *
     * @param Result $result The result to check
     * @param string $expectedName Expected service name
     * @param bool $expectedHealthy Expected health status
     * @param string|null $expectedReason Expected reason (null means don't check)
     */
    final protected function assertResultProperties(
        Result $result,
        string $expectedName,
        bool $expectedHealthy,
        ?string $expectedReason = null
    ): void {
        self::assertSame($expectedName, $result->getName());
        self::assertSame($expectedHealthy, $result->isHealthy());

        if ($expectedReason !== null) {
            self::assertSame($expectedReason, $result->getReason());
        }
    }

    /**
     * Asserts that serialization methods produce consistent output.
     *
     * @param Result $result Result to test serialization for
     */
    final protected function assertSerializationConsistency(Result $result): void
    {
        $array = $result->toArray();
        $json = $result->jsonSerialize();

        self::assertSame($array, $json, 'toArray() and jsonSerialize() should return identical results');
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('isHealthy', $array);
        self::assertArrayHasKey('description', $array);
    }

    /**
     * Creates test data for common health calculation scenarios.
     *
     * @return \Generator<string, array{0: bool, 1: list<bool>, 2: bool}>
     */
    final public static function healthCalculationScenarios(): \Generator
    {
        yield 'healthy parent, no sub-results' => [true, [], true];
        yield 'unhealthy parent, no sub-results' => [false, [], false];
        yield 'healthy parent, all healthy subs' => [true, [true, true], true];
        yield 'healthy parent, mixed subs' => [true, [true, false], false];
        yield 'unhealthy parent, healthy subs' => [false, [true, true], false];
        yield 'unhealthy parent, unhealthy subs' => [false, [false, false], false];
    }

    /**
     * Creates common property test scenarios for magic getter testing.
     *
     * @return \Generator<string, array{0: string, 1: bool, 2: string|null, 3: string, 4: mixed}>
     */
    final public static function propertyAccessScenarios(): \Generator
    {
        yield 'name property access' => ['test-service', true, 'All good', 'name', 'test-service'];
        yield 'isHealthy property access' => ['test-service', false, 'Error occurred', 'isHealthy', false];
        yield 'description property with value' => ['test-service', true, 'Service operational', 'description', 'Service operational'];
        yield 'description property when null' => ['test-service', true, null, 'description', null];
    }
}
