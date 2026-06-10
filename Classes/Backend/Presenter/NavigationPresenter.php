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

namespace mteu\Monitoring\Backend\Presenter;

use mteu\Monitoring\Backend\NavigationItem;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * NavigationPresenter.
 *
 * @author Martin Adler <martin.adler@init.de>
 * @license GPL-2.0-or-later
 */
final readonly class NavigationPresenter
{
    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        private UriBuilder $uriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @return NavigationItem[]
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function present(string $class): array
    {
        {
            return [
                new NavigationItem(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':module.backToOverview'),
                    (string)$this->uriBuilder->buildUriFromRoute('monitoring'),
                    isActive: $class === 'mteu\Monitoring\Backend\Controller\MonitoringController',
                ),
                new NavigationItem(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.title'),
                    (string)$this->uriBuilder->buildUriFromRoute('monitoring_providers'),
                    isActive: $class === 'mteu\Monitoring\Backend\Controller\ProviderController',
                ),
                new NavigationItem(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.title'),
                    (string)$this->uriBuilder->buildUriFromRoute('monitoring_authorizers'),
                    isActive: $class === 'mteu\Monitoring\Backend\Controller\AuthorizerController',
                ),
                new NavigationItem(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.title'),
                    (string)$this->uriBuilder->buildUriFromRoute('monitoring_reporters'),
                    isActive: $class === 'mteu\Monitoring\Backend\Controller\ReporterController',
                ),
            ];
        }
    }

    private function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
