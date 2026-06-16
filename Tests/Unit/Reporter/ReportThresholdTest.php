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

namespace mteu\Monitoring\Tests\Unit\Reporter;

use mteu\Monitoring\Reporter\ReportThreshold;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ReportThresholdTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReportThreshold::class)]
final class ReportThresholdTest extends TestCase
{
    #[Test]
    #[DataProvider('configValueDataProvider')]
    public function fromConfigValueMapsKnownValuesAndFallsBackToUnhealthy(string $value, ReportThreshold $expected): void
    {
        self::assertSame($expected, ReportThreshold::fromConfigValue($value));
    }

    /**
     * @return \Generator<string, array{string, ReportThreshold}>
     */
    public static function configValueDataProvider(): \Generator
    {
        yield 'always' => ['always', ReportThreshold::Always];
        yield 'unhealthy' => ['unhealthy', ReportThreshold::Unhealthy];
        yield 'state-change' => ['state-change', ReportThreshold::StateChange];
        yield 'unknown value' => ['whenever', ReportThreshold::Unhealthy];
        yield 'empty value' => ['', ReportThreshold::Unhealthy];
    }
}
