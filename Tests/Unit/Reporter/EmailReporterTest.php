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

use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Reporter\EmailReporter;
use mteu\Monitoring\Reporter\NotificationDecision;
use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Reporter\ReportThreshold;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Mail\MailerInterface;

/**
 * EmailReporterTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(EmailReporter::class)]
final class EmailReporterTest extends TestCase
{
    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function isInactiveWhenDisabled(): void
    {
        self::assertFalse(
            $this->reporter(enabled: false, recipients: 'ops@example.com')->isActive(),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function isInactiveWhenNoRecipients(): void
    {
        self::assertFalse(
            $this->reporter(enabled: true, recipients: '')->isActive(),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function isActiveWhenEnabledWithRecipients(): void
    {
        $reporter = $this->reporter(enabled: true, recipients: 'ops@example.com');

        self::assertTrue($reporter->isActive());
        self::assertSame('EmailReporter', $reporter->getName());
        self::assertSame(ReportThreshold::Unhealthy, $reporter->getThreshold());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function reportReturnsFalseWithoutRecipients(): void
    {
        $reporter = $this->reporter(enabled: true, recipients: '');

        $context = new ReportContext(NotificationDecision::Unhealthy, [], ['svc'], 'fp');

        self::assertFalse($reporter->report($context));
    }

    private function reporter(bool $enabled, string $recipients): EmailReporter
    {
        return new EmailReporter(
            new EmailReporterConfiguration(enabled: $enabled, recipients: $recipients),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}
