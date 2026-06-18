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
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
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
    public function isInactiveWhenDisabled(): void
    {
        self::assertFalse(
            $this->reporter(enabled: false, recipients: 'ops@example.com')->isActive(),
        );
    }

    #[Test]
    public function isInactiveWhenNoRecipients(): void
    {
        self::assertFalse(
            $this->reporter(enabled: true, recipients: '')->isActive(),
        );
    }

    #[Test]
    public function isActiveWhenEnabledWithRecipients(): void
    {
        $reporter = $this->reporter(enabled: true, recipients: 'ops@example.com');

        self::assertTrue($reporter->isActive());
        self::assertSame('Email Reporter', $reporter->getName());
        self::assertSame(ReportThreshold::StateChange, $reporter->getThreshold());
    }

    #[Test]
    public function reportReturnsFalseWithoutRecipients(): void
    {
        $reporter = $this->reporter(enabled: true, recipients: '');

        $context = new ReportContext(NotificationDecision::Unhealthy, [], ['svc'], 'fp');

        self::assertFalse($reporter->report($context));
    }

    #[Test]
    public function reportSendsMailToAllConfiguredRecipients(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com, admin@example.com',
            sentMessage: $message,
        );

        $context = new ReportContext(
            NotificationDecision::Unhealthy,
            ['db' => new MonitoringResult('db', false)],
            ['db'],
            'fp',
        );

        self::assertTrue($reporter->report($context));
        self::assertInstanceOf(Email::class, $message);
        self::assertSame(
            ['ops@example.com', 'admin@example.com'],
            array_map(static fn(Address $address) => $address->getAddress(), $message->getTo()),
        );
    }

    #[Test]
    public function reportSetsSenderAddressWhenConfigured(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            senderAddress: 'monitoring@example.com',
            sentMessage: $message,
        );

        $reporter->report($this->unhealthyContext());

        self::assertInstanceOf(Email::class, $message);
        self::assertSame(
            ['monitoring@example.com'],
            array_map(static fn(Address $address) => $address->getAddress(), $message->getFrom()),
        );
    }

    #[Test]
    public function reportOmitsSenderAddressWhenNotConfigured(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            sentMessage: $message,
        );

        $reporter->report($this->unhealthyContext());

        self::assertInstanceOf(Email::class, $message);
        self::assertSame([], $message->getFrom());
    }

    #[Test]
    public function reportComposesUnhealthySubjectWithPrefix(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            sentMessage: $message,
        );

        $context = new ReportContext(
            NotificationDecision::Unhealthy,
            [
                'db' => new MonitoringResult('db', false),
                'scheduler' => new MonitoringResult('scheduler', false),
            ],
            ['db', 'scheduler'],
            'fp',
        );

        $reporter->report($context);

        self::assertInstanceOf(Email::class, $message);
        self::assertSame('[Monitoring] Unhealthy: db, scheduler', $message->getSubject());
    }

    #[Test]
    public function reportComposesSubjectWithoutPrefixWhenPrefixIsEmpty(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            subjectPrefix: '',
            sentMessage: $message,
        );

        $reporter->report($this->unhealthyContext());

        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Unhealthy: db', $message->getSubject());
    }

    #[Test]
    public function reportComposesReminderSubjectAndBody(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            sentMessage: $message,
        );

        $context = new ReportContext(
            NotificationDecision::Reminder,
            ['db' => new MonitoringResult('db', false, 'Connection refused')],
            ['db'],
            'fp',
        );

        $reporter->report($context);

        self::assertInstanceOf(Email::class, $message);
        self::assertSame('[Monitoring] Still unhealthy: db', $message->getSubject());
        self::assertSame(
            "The following services are still reporting an unhealthy state:\n"
            . "\n"
            . "[FAILED] db — Connection refused\n",
            $message->getTextBody(),
        );
    }

    #[Test]
    public function reportComposesRecoverySubjectAndBody(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            sentMessage: $message,
        );

        $context = new ReportContext(
            NotificationDecision::Recovered,
            [
                'db' => new MonitoringResult('db', true),
                'scheduler' => new MonitoringResult('scheduler', true),
            ],
            [],
            null,
        );

        $reporter->report($context);

        self::assertInstanceOf(Email::class, $message);
        self::assertSame('[Monitoring] All monitored services recovered', $message->getSubject());
        self::assertSame(
            "All monitored services are healthy again.\n"
            . "\n"
            . "[OK] db\n"
            . "[OK] scheduler\n",
            $message->getTextBody(),
        );
    }

    #[Test]
    public function reportBodyOmitsReasonSeparatorForResultsWithoutReason(): void
    {
        $reporter = $this->reporter(
            enabled: true,
            recipients: 'ops@example.com',
            sentMessage: $message,
        );

        $context = new ReportContext(
            NotificationDecision::Unhealthy,
            [
                'db' => new MonitoringResult('db', false),
                'cache' => new MonitoringResult('cache', false, 'Backend gone'),
            ],
            ['cache', 'db'],
            'fp',
        );

        $reporter->report($context);

        self::assertInstanceOf(Email::class, $message);
        self::assertSame(
            "The following services are reporting an unhealthy state:\n"
            . "\n"
            . "[FAILED] db\n"
            . "[FAILED] cache — Backend gone\n",
            $message->getTextBody(),
        );
    }

    #[Test]
    public function reportReturnsFalseAndLogsErrorWhenTransportFails(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unreachable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'EmailReporter failed to send monitoring report',
                ['exception' => 'SMTP unreachable'],
            );

        $reporter = new EmailReporter(
            new EmailReporterConfiguration(enabled: true, recipients: 'ops@example.com'),
            $mailer,
            $logger,
        );

        self::assertFalse($reporter->report($this->unhealthyContext()));
    }

    #[Test]
    public function reportSkipsInvalidRecipientsAndSendsToValidOnes(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'EmailReporter skipping invalid recipient address',
                self::callback(static fn(array $context): bool => $context['recipient'] === 'broken@@example'),
            );

        $message = null;
        $mailer = self::createStub(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(static function (RawMessage $sent) use (&$message): void {
                $message = $sent;
            });

        $reporter = new EmailReporter(
            new EmailReporterConfiguration(
                enabled: true,
                recipients: 'ops@example.com, broken@@example',
            ),
            $mailer,
            $logger,
        );

        self::assertTrue($reporter->report($this->unhealthyContext()));
        self::assertInstanceOf(Email::class, $message);
        self::assertSame(
            ['ops@example.com'],
            array_map(static fn(Address $address) => $address->getAddress(), $message->getTo()),
        );
    }

    #[Test]
    public function reportReturnsFalseWhenAllRecipientsAreInvalid(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $reporter = new EmailReporter(
            new EmailReporterConfiguration(
                enabled: true,
                recipients: 'broken@@example, also-broken',
            ),
            $mailer,
            self::createStub(LoggerInterface::class),
        );

        self::assertFalse($reporter->report($this->unhealthyContext()));
    }

    #[Test]
    public function reportIgnoresInvalidSenderAddressAndStillSends(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'EmailReporter ignoring invalid sender address',
                self::callback(static fn(array $context): bool => $context['sender'] === 'broken@@example'),
            );

        $message = null;
        $mailer = self::createStub(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(static function (RawMessage $sent) use (&$message): void {
                $message = $sent;
            });

        $reporter = new EmailReporter(
            new EmailReporterConfiguration(
                enabled: true,
                recipients: 'ops@example.com',
                senderAddress: 'broken@@example',
            ),
            $mailer,
            $logger,
        );

        self::assertTrue($reporter->report($this->unhealthyContext()));
        self::assertInstanceOf(Email::class, $message);
        self::assertSame([], $message->getFrom());
    }

    private function unhealthyContext(): ReportContext
    {
        return new ReportContext(
            NotificationDecision::Unhealthy,
            ['db' => new MonitoringResult('db', false)],
            ['db'],
            'fp',
        );
    }

    private function reporter(
        bool $enabled,
        string $recipients,
        string $senderAddress = '',
        string $subjectPrefix = '[Monitoring]',
        ?RawMessage &$sentMessage = null,
    ): EmailReporter {
        $mailer = self::createStub(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });

        return new EmailReporter(
            new EmailReporterConfiguration(
                enabled: $enabled,
                recipients: $recipients,
                senderAddress: $senderAddress,
                subjectPrefix: $subjectPrefix,
            ),
            $mailer,
            self::createStub(LoggerInterface::class),
        );
    }
}
