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

namespace mteu\Monitoring\Tests\Unit\Configuration\Provider;

use mteu\Monitoring as Src;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * SchedulerProviderConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Provider\SchedulerProviderConfiguration::class)]
final class SchedulerProviderConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function heartbeatCheckIsDisabledWhenThresholdIsZero(): void
    {
        $subject = new Src\Configuration\Provider\SchedulerProviderConfiguration(heartbeatThreshold: 0);

        self::assertFalse($subject->isHeartbeatCheckEnabled());
    }

    #[Test]
    public function overdueCheckIsDisabledWhenThresholdIsZero(): void
    {
        $subject = new Src\Configuration\Provider\SchedulerProviderConfiguration(overdueThreshold: 0);

        self::assertFalse($subject->isOverdueCheckEnabled());
    }

    #[Test]
    public function overdueCheckIsEnabledWhenThresholdIsPositive(): void
    {
        $subject = new Src\Configuration\Provider\SchedulerProviderConfiguration(overdueThreshold: 60);

        self::assertTrue($subject->isOverdueCheckEnabled());
    }
}
