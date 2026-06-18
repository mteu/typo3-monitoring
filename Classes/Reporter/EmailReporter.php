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
use Symfony\Component\Mime\Exception\RfcComplianceException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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
    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        private EmailReporterConfiguration $configuration,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private LanguageServiceFactory $languageServiceFactory,
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
        $recipients = $this->parseRecipients($this->configuration->getRecipients());

        if ($recipients === []) {
            return false;
        }

        $languageService = $this->getLanguageService();
        $sender = $this->parseSender($this->configuration->senderAddress);

        try {
            $message = new MailMessage();
            $message
                ->to(...$recipients)
                ->subject($this->buildSubject($context, $languageService))
                ->text($this->buildBody($context, $languageService));

            if ($sender !== null) {
                $message->from($sender);
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

    /**
     * Turns configured recipient strings into validated Address objects, letting
     * Symfony's Address perform the RFC compliance check. Malformed entries are
     * logged and dropped so a single configuration typo cannot abort the dispatch
     * run with an uncaught RfcComplianceException.
     *
     * @param list<non-empty-string> $recipients
     * @return list<Address>
     */
    private function parseRecipients(array $recipients): array
    {
        $addresses = [];

        foreach ($recipients as $recipient) {
            try {
                $addresses[] = new Address($recipient);
            } catch (RfcComplianceException $e) {
                $this->logger->warning('EmailReporter skipping invalid recipient address', [
                    'recipient' => $recipient,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $addresses;
    }

    /**
     * Returns a validated sender Address, or null when none is configured or the
     * configured value is malformed (in which case the mailer falls back to the
     * default sender instead of throwing).
     */
    private function parseSender(string $senderAddress): ?Address
    {
        if ($senderAddress === '') {
            return null;
        }

        try {
            return new Address($senderAddress);
        } catch (RfcComplianceException $e) {
            $this->logger->warning('EmailReporter ignoring invalid sender address', [
                'sender' => $senderAddress,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getName(): string
    {
        return 'Email Reporter';
    }

    public static function getPriority(): int
    {
        // MakeInstance is unavoidable inside this static method
        $config = GeneralUtility::makeInstance(EmailReporterConfiguration::class);

        return $config->getPriority();
    }

    private function buildSubject(ReportContext $context, LanguageService $languageService): string
    {
        $prefix = $this->configuration->subjectPrefix;
        $prefix = $prefix === '' ? '' : $prefix . ' ';

        $summary = match ($context->decision) {
            NotificationDecision::Recovered => $this->translate($languageService, 'reporter.email.subject.recovered'),
            NotificationDecision::Reminder => $this->translate(
                $languageService,
                'reporter.email.subject.reminder',
                implode(', ', $context->unhealthyProviderNames),
            ),
            NotificationDecision::Unhealthy, NotificationDecision::None => $this->translate(
                $languageService,
                'reporter.email.subject.unhealthy',
                implode(', ', $context->unhealthyProviderNames),
            ),
        };

        return $prefix . $summary;
    }

    private function buildBody(ReportContext $context, LanguageService $languageService): string
    {
        $lines = [];

        $lines[] = match ($context->decision) {
            NotificationDecision::Recovered => $this->translate($languageService, 'reporter.email.body.recovered'),
            NotificationDecision::Reminder => $this->translate($languageService, 'reporter.email.body.reminder'),
            NotificationDecision::Unhealthy, NotificationDecision::None => $this->translate($languageService, 'reporter.email.body.unhealthy'),
        };
        $lines[] = '';

        foreach ($context->results as $result) {
            $status = $result->isHealthy()
                ? $this->translate($languageService, 'reporter.email.status.ok')
                : $this->translate($languageService, 'reporter.email.status.failed');
            $reason = $result->getReason();
            $lines[] = $this->translate(
                $languageService,
                'reporter.email.body.line',
                $status,
                $result->getName(),
                $reason !== null && $reason !== '' ? ' — ' . $reason : '',
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function translate(LanguageService $languageService, string $key, int|float|string ...$args): string
    {
        $label = $languageService->sL(self::LOCALLANG_FILE . ':' . $key);

        return $args === [] ? $label : sprintf($label, ...$args);
    }

    private function getLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->create('default');
    }
}
