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

namespace mteu\Monitoring\Configuration\Reporter;

use mteu\Monitoring\Reporter\ReportThreshold;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * EmailReporterConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class EmailReporterConfiguration implements ReporterConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.enabled')]
        public bool $enabled = false,
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.priority')]
        public int $priority = 10,
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.threshold')]
        public string $threshold = 'state-change',
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.recipients')]
        public string $recipients = '',
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.senderAddress')]
        public string $senderAddress = '',
        #[ExtConfProperty(path: 'reporter.mteu\\Monitoring\\Reporter\\EmailReporter.subjectPrefix')]
        public string $subjectPrefix = '[Monitoring]',
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getThreshold(): ReportThreshold
    {
        return ReportThreshold::fromConfigValue($this->threshold);
    }

    /**
     * @return list<non-empty-string>
     */
    public function getRecipients(): array
    {
        $recipients = [];

        foreach (explode(',', $this->recipients) as $recipient) {
            $recipient = trim($recipient);

            if ($recipient !== '') {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }
}
