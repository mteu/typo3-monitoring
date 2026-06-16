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

namespace mteu\Monitoring\Tests\Unit\Configuration\Reporter;

use mteu\Monitoring as Src;
use mteu\Monitoring\Reporter\ReportThreshold;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * EmailReporterConfigurationTest.
 *
 * The string-to-enum mapping itself is covered by ReportThresholdTest; this
 * test only pins the configuration's own parsing behavior.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Reporter\EmailReporterConfiguration::class)]
final class EmailReporterConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function thresholdDelegatesToEnumMapping(): void
    {
        $subject = new Src\Configuration\Reporter\EmailReporterConfiguration(threshold: 'state-change');

        self::assertSame(ReportThreshold::StateChange, $subject->getThreshold());
    }

    #[Test]
    public function recipientsAreSplitAndTrimmed(): void
    {
        $subject = new Src\Configuration\Reporter\EmailReporterConfiguration(
            recipients: ' a@example.com , ,b@example.com ',
        );

        self::assertSame(['a@example.com', 'b@example.com'], $subject->getRecipients());
    }

    #[Test]
    public function recipientsAreEmptyWhenUnconfigured(): void
    {
        $subject = new Src\Configuration\Reporter\EmailReporterConfiguration();

        self::assertSame([], $subject->getRecipients());
    }
}
