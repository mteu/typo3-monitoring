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

use mteu\Monitoring\Reporter\NotificationDecision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NotificationDecisionTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(NotificationDecision::class)]
final class NotificationDecisionTest extends TestCase
{
    #[Test]
    #[DataProvider('decisionDataProvider')]
    public function shouldDispatchForEveryDecisionExceptNone(NotificationDecision $decision, bool $expected): void
    {
        self::assertSame($expected, $decision->shouldDispatch());
    }

    /**
     * @return \Generator<string, array{NotificationDecision, bool}>
     */
    public static function decisionDataProvider(): \Generator
    {
        yield 'none' => [NotificationDecision::None, false];
        yield 'unhealthy' => [NotificationDecision::Unhealthy, true];
        yield 'reminder' => [NotificationDecision::Reminder, true];
        yield 'recovered' => [NotificationDecision::Recovered, true];
    }
}
