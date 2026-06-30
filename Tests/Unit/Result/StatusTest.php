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

use mteu\Monitoring\Result\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StatusTest.
 *
 * The severity-ordering primitives the whole extension aggregates health with:
 * MonitoringResult/Middleware fold sub-results via worst(), the dispatcher gates
 * notifications via isAtLeast(). A regression here silently flips an HTTP verdict
 * or suppresses an alert.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(Status::class)]
final class StatusTest extends TestCase
{
    #[Test]
    public function severityRanksHealthyBelowDegradedBelowUnhealthy(): void
    {
        self::assertLessThan(Status::Degraded->severity(), Status::Healthy->severity());
        self::assertLessThan(Status::Unhealthy->severity(), Status::Degraded->severity());
    }

    #[Test]
    #[DataProvider('atLeastProvider')]
    public function isAtLeastComparesBySeverityAndIsInclusiveAtTheFloor(
        Status $status,
        Status $floor,
        bool $expected,
    ): void {
        self::assertSame($expected, $status->isAtLeast($floor));
    }

    /**
     * @return \Generator<string, array{Status, Status, bool}>
     */
    public static function atLeastProvider(): \Generator
    {
        yield 'equal severity is inclusive' => [Status::Degraded, Status::Degraded, true];
        yield 'more severe clears the floor' => [Status::Unhealthy, Status::Degraded, true];
        yield 'less severe is below the floor' => [Status::Healthy, Status::Degraded, false];
    }

    #[Test]
    #[DataProvider('worstProvider')]
    public function worstReturnsTheHighestSeverityStatus(Status $expected, Status ...$statuses): void
    {
        self::assertSame($expected, Status::worst(...$statuses));
    }

    /**
     * @return \Generator<string, array{0: Status, ...}>
     */
    public static function worstProvider(): \Generator
    {
        yield 'no arguments defaults to healthy' => [Status::Healthy];
        yield 'all healthy stays healthy' => [Status::Healthy, Status::Healthy, Status::Healthy];
        yield 'a single degraded wins over healthy' => [Status::Degraded, Status::Healthy, Status::Degraded];
        yield 'unhealthy beats degraded regardless of order' => [Status::Unhealthy, Status::Degraded, Status::Unhealthy, Status::Healthy];
    }
}
