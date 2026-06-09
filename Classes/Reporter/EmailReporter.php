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

namespace mteu\Monitoring\Reporter;

use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EmailReporter.
 *
 * Sends the monitoring status via TYPO3's core mailer. SMTP/transport
 * credentials come from `$GLOBALS['TYPO3_CONF_VARS']['MAIL']` and are never
 * stored in extension configuration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class EmailReporter implements Reporter
{
    public function __construct(
        private EmailReporterConfiguration $configuration,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {}

    public function isActive(): bool
    {
        return $this->configuration->isEnabled() && $this->configuration->getRecipients() !== [];
    }

    public function getThreshold(): ReportThreshold
    {
        return $this->configuration->getThreshold();
    }

    public function report(ReportContext $context): bool
    {
        $recipients = $this->configuration->getRecipients();

        if ($recipients === []) {
            return false;
        }

        try {
            $message = new MailMessage();
            $message
                ->to(...$recipients)
                ->subject($this->buildSubject($context))
                ->text($this->buildBody($context));

            if ($this->configuration->senderAddress !== '') {
                $message->from(new Address($this->configuration->senderAddress));
            }

            $this->mailer->send($message);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('EmailReporter failed to send monitoring report', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'EmailReporter';
    }

    public static function getPriority(): int
    {
        // MakeInstance is unavoidable inside this static method
        $config = GeneralUtility::makeInstance(EmailReporterConfiguration::class);

        return $config->getPriority();
    }

    private function buildSubject(ReportContext $context): string
    {
        $prefix = $this->configuration->subjectPrefix;
        $prefix = $prefix === '' ? '' : $prefix . ' ';

        $summary = match ($context->decision) {
            NotificationDecision::Recovered => 'All monitored services recovered',
            NotificationDecision::Reminder => sprintf(
                'Still unhealthy: %s',
                implode(', ', $context->unhealthyProviderNames),
            ),
            NotificationDecision::Unhealthy, NotificationDecision::None => sprintf(
                'Unhealthy: %s',
                implode(', ', $context->unhealthyProviderNames),
            ),
        };

        return $prefix . $summary;
    }

    private function buildBody(ReportContext $context): string
    {
        $lines = [];

        $lines[] = match ($context->decision) {
            NotificationDecision::Recovered => 'All monitored services are healthy again.',
            NotificationDecision::Reminder => 'The following services are still reporting an unhealthy state:',
            NotificationDecision::Unhealthy, NotificationDecision::None => 'The following services are reporting an unhealthy state:',
        };
        $lines[] = '';

        foreach ($context->results as $result) {
            $status = $result->isHealthy() ? 'OK' : 'FAILED';
            $reason = $result->getReason();
            $lines[] = sprintf(
                '[%s] %s%s',
                $status,
                $result->getName(),
                $reason !== null && $reason !== '' ? ' — ' . $reason : '',
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
