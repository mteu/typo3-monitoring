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
use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ReportContextTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReportContext::class)]
final class ReportContextTest extends TestCase
{
    #[Test]
    public function isHealthyWhenNoProviderIsUnhealthy(): void
    {
        $context = new ReportContext(
            NotificationDecision::None,
            ['db' => new MonitoringResult('db', true)],
            [],
            null,
        );

        self::assertTrue($context->isHealthy());
    }

    #[Test]
    public function isUnhealthyWhenAtLeastOneProviderIsUnhealthy(): void
    {
        $context = new ReportContext(
            NotificationDecision::Unhealthy,
            ['db' => new MonitoringResult('db', false)],
            ['db'],
            'fp',
        );

        self::assertFalse($context->isHealthy());
    }
}
