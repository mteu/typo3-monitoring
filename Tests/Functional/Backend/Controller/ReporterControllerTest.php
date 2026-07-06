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

namespace mteu\Monitoring\Tests\Functional\Backend\Controller;

use mteu\Monitoring\Backend\Controller\ReporterController;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * ReporterControllerTest.
 *
 * Settings retrieval only: how the email reporter's configured values are
 * resolved into the backend overview (recipients, subject prefix, sender with
 * its system-default fallback). The reporter's transport behaviour is covered
 * by the EmailReporter tests, not here.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ReporterController::class)]
final class ReporterControllerTest extends MonitoringFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pin the system mail sender on the live config so MailUtility resolves deterministically.
        $configuration = $GLOBALS['TYPO3_CONF_VARS'];
        self::assertIsArray($configuration);

        $configuration['MAIL'] = [
            'defaultMailFromName' => 'TYPO3 Monitoring Test',
            'defaultMailFromAddress' => 'system@example.com',
        ];
        $GLOBALS['TYPO3_CONF_VARS'] = $configuration;
    }

    #[Test]
    public function configuredEmailSettingsAreResolvedForTheOverview(): void
    {
        $info = $this->emailReporterAdditionalInformation(
            new EmailReporterConfiguration(
                recipients: 'ops@example.com, admin@example.com',
                senderAddress: 'monitoring@example.com',
                subjectPrefix: '[Custom]',
            ),
        );

        self::assertSame('ops@example.com, admin@example.com', $info['Recipients']);
        self::assertSame('[Custom]', $info['Subject prefix']);
        self::assertSame('TYPO3 Monitoring Test <monitoring@example.com>', $info['Sender']);
    }

    #[Test]
    public function missingRecipientsAndSenderFallBackToTheSystemDefaults(): void
    {
        $info = $this->emailReporterAdditionalInformation(new EmailReporterConfiguration());

        self::assertSame('No email recipients configured.', $info['Recipients']);
        // Empty senderAddress falls back to the system mail address, flagged as the default.
        self::assertSame('TYPO3 Monitoring Test <system@example.com> (System default)', $info['Sender']);
    }

    /**
     * @return array<string, string>
     */
    private function emailReporterAdditionalInformation(EmailReporterConfiguration $emailConfiguration): array
    {
        $controller = new ReporterController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(LanguageServiceFactory::class),
            $this->get(ModuleProvider::class),
            $this->get(UriBuilder::class),
            [],
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                $emailConfiguration,
                new ReportDispatcherConfiguration(),
            ),
        );

        return $controller->getEmailReporterAdditionalInformation();
    }
}
