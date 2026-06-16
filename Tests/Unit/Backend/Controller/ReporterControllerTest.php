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

namespace mteu\Monitoring\Tests\Unit\Backend\Controller;

use mteu\Monitoring\Backend\Controller\ReporterController;
use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportThreshold;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * ReporterControllerTest.
 *
 * @todo: Tests own logic but bypass constructors. Fixing properly needs refactoring the controllers.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReporterController::class)]
final class ReporterControllerTest extends Framework\TestCase
{
    #[Test]
    public function collectReporterStatusesReturnsEmptyArrayWhenNoReportersAreRegistered(): void
    {
        self::assertSame([], $this->collectReporterStatuses([]));
    }

    #[Test]
    public function collectReporterStatusesReportsEachReportersActiveStatePriorityThresholdAndName(): void
    {
        // Two distinct implementations: anonymous classes declared separately
        // are separate types, so they keep distinct ::class keys and static priorities.
        $activeReporter = new class () implements Reporter {
            public function isActive(): bool
            {
                return true;
            }

            public function getThreshold(): ReportThreshold
            {
                return ReportThreshold::StateChange;
            }

            public function report(ReportContext $context): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'ActiveReporter';
            }

            public static function getPriority(): int
            {
                return 30;
            }
        };

        $inactiveReporter = new class () implements Reporter {
            public function isActive(): bool
            {
                return false;
            }

            public function getThreshold(): ReportThreshold
            {
                return ReportThreshold::Unhealthy;
            }

            public function report(ReportContext $context): bool
            {
                return false;
            }

            public function getName(): string
            {
                return 'InactiveReporter';
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };

        $statuses = $this->collectReporterStatuses([$activeReporter, $inactiveReporter]);

        self::assertTrue($statuses[$activeReporter::class]['isActive']);
        self::assertSame(30, $statuses[$activeReporter::class]['priority']);
        self::assertSame('ActiveReporter', $statuses[$activeReporter::class]['name']);
        self::assertSame('state-change', $statuses[$activeReporter::class]['threshold']);

        self::assertFalse($statuses[$inactiveReporter::class]['isActive']);
        self::assertSame(10, $statuses[$inactiveReporter::class]['priority']);
        self::assertSame('InactiveReporter', $statuses[$inactiveReporter::class]['name']);
        self::assertSame('unhealthy', $statuses[$inactiveReporter::class]['threshold']);
    }

    /**
     * Invokes the private collectReporterStatuses() on a controller created
     * without its constructor, injecting only the reporters it reads.
     *
     * @param list<Reporter> $reporters
     * @return array<class-string, array{name: string, isActive: bool, priority: int, threshold: string}>
     */
    private function collectReporterStatuses(array $reporters): array
    {
        $reflection = new \ReflectionClass(ReporterController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('reporters')->setValue($controller, $reporters);

        $result = $reflection->getMethod('collectReporterStatuses')->invoke($controller);
        self::assertIsArray($result);

        /** @var array<class-string, array{name: string, isActive: bool, priority: int, threshold: string}> $result */
        return $result;
    }
}
