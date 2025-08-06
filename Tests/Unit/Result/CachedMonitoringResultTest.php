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

use mteu\Monitoring\Result\CachedMonitoringResult;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Tests\Unit\MonitoringTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * CachedMonitoringResultTest.
 *
 * Tests the CachedMonitoringResult business logic and Result interface implementation.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CachedMonitoringResult::class)]
final class CachedMonitoringResultTest extends MonitoringTestCase
{
    #[Test]
    public function implementsResultInterface(): void
    {
        $result = $this->createHealthyResult();
        $cached = new CachedMonitoringResult($result, new \DateTimeImmutable(), 300);

        self::assertInstanceOf(Result::class, $cached);
    }

    #[Test]
    public function constructorInitializesPropertiesCorrectly(): void
    {
        $result = new MonitoringResult('service', true, 'All good');
        $cachedAt = new \DateTimeImmutable('2023-01-01 12:00:00');
        $lifetime = 600;

        $cached = new CachedMonitoringResult($result, $cachedAt, $lifetime);

        self::assertSame($result, $cached->getResult());
        self::assertSame($lifetime, $cached->getLifetime());
    }

    #[Test]
    #[DataProvider('expirationDataProvider')]
    public function calculatesExpirationTimeFromCachedAtAndLifetime(
        \DateTimeImmutable $cachedAt,
        int $lifetime,
        \DateTimeImmutable $expectedExpiration
    ): void {
        $result = new MonitoringResult('test', true);
        $cached = new CachedMonitoringResult($result, $cachedAt, $lifetime);

        $actualExpiration = $cached->getExpiresAt();

        self::assertEquals($expectedExpiration->getTimestamp(), $actualExpiration->getTimestamp());
    }

    #[Test]
    #[DataProvider('expirationStatusProvider')]
    public function determinesExpirationStatusBasedOnCurrentTime(
        \DateTimeImmutable $cachedAt,
        int $lifetime,
        bool $expectedExpired
    ): void {
        $result = new MonitoringResult('test', true);
        $cached = new CachedMonitoringResult($result, $cachedAt, $lifetime);

        self::assertSame($expectedExpired, $cached->isExpired());
    }

    /**
     * @param list<MonitoringResult> $subResults
     */
    #[Test]
    #[DataProvider('resultDelegationProvider')]
    public function delegatesAllResultMethodsToWrappedInstance(
        string $name,
        bool $isHealthy,
        ?string $reason,
        array $subResults
    ): void {
        $result = new MonitoringResult($name, $isHealthy, $reason);
        foreach ($subResults as $subResult) {
            $result->addSubResult($subResult);
        }

        $cached = new CachedMonitoringResult($result, new \DateTimeImmutable(), 300);

        self::assertSame($name, $cached->getName());
        self::assertSame($isHealthy, $cached->isHealthy());
        self::assertSame($reason, $cached->getReason());
        self::assertSame(count($subResults) > 0, $cached->hasSubResults());
        self::assertCount(count($subResults), $cached->getSubResults());
        self::assertSame($subResults, $cached->getSubResults());
    }

    /**
     * @param list<MonitoringResult> $subResults
     */
    #[Test]
    #[DataProvider('serializationProvider')]
    public function serializationMethodsProduceIdenticalOutput(
        string $name,
        bool $isHealthy,
        ?string $reason,
        array $subResults
    ): void {
        $result = new MonitoringResult($name, $isHealthy, $reason);
        foreach ($subResults as $subResult) {
            $result->addSubResult($subResult);
        }

        $cached = new CachedMonitoringResult($result, new \DateTimeImmutable(), 300);

        /**
         * @var array{
         *      name: string,
         *      isHealthy: bool,
         *      description: string|null,
         *      subResults?: array<int, mixed>
         *  } $array
         */
        $array = $cached->toArray();
        $json = $cached->jsonSerialize();

        self::assertSame($array, $json, 'Both methods should return identical results');
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('isHealthy', $array);
        self::assertArrayHasKey('description', $array);
        self::assertSame($name, $array['name']);
        self::assertSame($reason, $array['description']);

        if (count($subResults) > 0) {
            self::assertArrayHasKey('subResults', $array);
            self::assertCount(count($subResults), ($array['subResults'] ?? []));
        }
    }

    #[Test]
    public function normalizesCachedTimeToServerTimezone(): void
    {
        $result = new MonitoringResult('test', true);

        $utcTime = new \DateTimeImmutable('2023-01-01 12:00:00', new \DateTimeZone('UTC'));
        $cached = new CachedMonitoringResult($result, $utcTime, 3600);

        $expiresAt = $cached->getExpiresAt();
        $expectedTimezone = date_default_timezone_get();

        self::assertSame($expectedTimezone, $expiresAt->getTimezone()->getName(), 'Should use server timezone for consistency');
    }

    public static function expirationDataProvider(): \Generator
    {
        $baseTime = new \DateTimeImmutable('2023-01-01 12:00:00');

        yield 'one hour lifetime' => [
            $baseTime,
            3600,
            new \DateTimeImmutable('2023-01-01 13:00:00'),
        ];

        yield 'five minutes lifetime' => [
            $baseTime,
            300,
            new \DateTimeImmutable('2023-01-01 12:05:00'),
        ];

        yield 'zero lifetime' => [
            $baseTime,
            0,
            $baseTime,
        ];

        yield 'one day lifetime' => [
            $baseTime,
            86400,
            new \DateTimeImmutable('2023-01-02 12:00:00'),
        ];
    }

    public static function expirationStatusProvider(): \Generator
    {
        $now = new \DateTimeImmutable();

        yield 'expired - cached 2 hours ago with 1 hour lifetime' => [
            $now->modify('-2 hours'),
            3600,
            true,
        ];

        yield 'not expired - cached 30 minutes ago with 1 hour lifetime' => [
            $now->modify('-30 minutes'),
            3600,
            false,
        ];

        yield 'just expired - cached exactly lifetime ago' => [
            $now->modify('-1 hour'),
            3600,
            true,
        ];

        yield 'not expired - cached 1 second ago with 1 hour lifetime' => [
            $now->modify('-1 second'),
            3600,
            false,
        ];

        yield 'expired - zero lifetime' => [
            $now->modify('-1 second'),
            0,
            true,
        ];
    }

    public static function resultDelegationProvider(): \Generator
    {
        yield 'simple healthy result' => [
            'database-check',
            true,
            'Connection successful',
            [],
        ];

        yield 'simple unhealthy result' => [
            'api-check',
            false,
            'Service unavailable',
            [],
        ];

        yield 'result with null reason' => [
            'basic-check',
            true,
            null,
            [],
        ];

        yield 'result with one sub-result' => [
            'complex-check',
            true,
            'Main service healthy',
            [new MonitoringResult('sub-check', true, 'Sub-service OK')],
        ];

        yield 'result with multiple sub-results' => [
            'comprehensive-check',
            false,
            'Some services failing',
            [
                new MonitoringResult('db-check', true, 'Database OK'),
                new MonitoringResult('cache-check', false, 'Cache unavailable'),
                new MonitoringResult('api-check', true, 'API responding'),
            ],
        ];
    }

    public static function serializationProvider(): \Generator
    {
        yield 'simple result serialization' => [
            'test-service',
            true,
            'Service operational',
            [],
        ];

        yield 'result with sub-results serialization' => [
            'parent-service',
            false,
            'Parent service degraded',
            [
                new MonitoringResult('child-1', true, 'Child 1 OK'),
                new MonitoringResult('child-2', false, 'Child 2 failed'),
            ],
        ];

        yield 'minimal result serialization' => [
            'minimal',
            false,
            null,
            [],
        ];
    }
}
