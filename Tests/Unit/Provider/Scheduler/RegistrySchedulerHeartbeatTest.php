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

namespace mteu\Monitoring\Tests\Unit\Provider\Scheduler;

use mteu\Monitoring\Provider\Scheduler\RegistrySchedulerHeartbeat;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Registry;

/**
 * RegistrySchedulerHeartbeatTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(RegistrySchedulerHeartbeat::class)]
final class RegistrySchedulerHeartbeatTest extends Framework\TestCase
{
    #[Test]
    public function returnsEndTimeFromRegistry(): void
    {
        self::assertSame(
            1_700_000_000,
            $this->createHeartbeat(['start' => 1_699_999_990, 'end' => 1_700_000_000])->getLastRunEndTime(),
        );
    }

    #[Test]
    public function castsNumericStringEndTimeToInt(): void
    {
        self::assertSame(
            1_700_000_000,
            $this->createHeartbeat(['end' => '1700000000'])->getLastRunEndTime(),
        );
    }

    #[Test]
    public function returnsNullWhenNoRunWasRecorded(): void
    {
        self::assertNull($this->createHeartbeat(null)->getLastRunEndTime());
    }

    #[Test]
    public function returnsNullWhenRegistryValueIsNotAnArray(): void
    {
        self::assertNull($this->createHeartbeat('garbage')->getLastRunEndTime());
    }

    #[Test]
    public function returnsNullWhenEndTimeIsMissing(): void
    {
        self::assertNull($this->createHeartbeat(['start' => 1_700_000_000])->getLastRunEndTime());
    }

    #[Test]
    public function returnsNullWhenEndTimeIsNotNumeric(): void
    {
        self::assertNull($this->createHeartbeat(['end' => 'not-a-timestamp'])->getLastRunEndTime());
    }

    private function createHeartbeat(mixed $lastRun): RegistrySchedulerHeartbeat
    {
        $registry = self::createStub(Registry::class);
        $registry->method('get')->willReturnMap([
            ['tx_scheduler', 'lastRun', null, $lastRun],
        ]);

        return new RegistrySchedulerHeartbeat($registry);
    }
}
