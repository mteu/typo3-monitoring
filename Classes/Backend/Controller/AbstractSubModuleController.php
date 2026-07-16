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

namespace mteu\Monitoring\Backend\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AbstractSubModuleController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract readonly class AbstractSubModuleController
{
    protected const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';
    private const string FLASHMESSAGE_QUEUE_IDENTIFIER = 'ext_monitoring_message_queue';

    public function __construct(
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected LanguageServiceFactory $languageServiceFactory,
        protected ModuleProvider $moduleProvider,
        protected UriBuilder $uriBuilder,
    ) {}

    final protected function createModuleTemplate(ServerRequestInterface $request, string $bookmarkRoute = ''): ModuleTemplate
    {
        $template = $this->moduleTemplateFactory->create($request);
        $isV14OrHigher = (new Typo3Version())->getMajorVersion() >= 14;

        if ($isV14OrHigher) {
            $template->makeDocHeaderModuleMenu();
        } else {
            // v13 core builds this menu from the submodules only, so build it
            // manually to prepend an "Overview" entry for the parent module.
            $this->makeDocHeaderModuleMenuWithOverview($template, $request);
        }

        if ($bookmarkRoute !== '' && $isV14OrHigher) {
            $template->getDocHeaderComponent()->setShortcutContext(
                $bookmarkRoute,
                $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':route.label.' . $bookmarkRoute),
            );
        }

        return $template->assignMultiple([
            'monitoringMessageQueueIdentifier' => self::FLASHMESSAGE_QUEUE_IDENTIFIER,
        ]);
    }

    /**
     * v13 pendant of ModuleTemplate::makeDocHeaderModuleMenu() with an
     * additional first entry linking back to the overview module.
     */
    private function makeDocHeaderModuleMenuWithOverview(ModuleTemplate $template, ServerRequestInterface $request): void
    {
        $currentModule = $request->getAttribute('module');
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if (!$currentModule instanceof ModuleInterface || !$backendUser instanceof BackendUserAuthentication) {
            return;
        }

        $languageService = $this->getLanguageService();
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier('moduleMenu');
        $menu->setLabel(
            $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang.xlf:moduleMenu.dropdown.label'),
        );

        $menuItems = [
            'monitoring' => $languageService->sL('LLL:EXT:monitoring/Resources/Private/Language/locallang.mod.xlf:module.overview.labels.title'),
        ];

        foreach (['monitoring_providers', 'monitoring_authorizers', 'monitoring_reporters'] as $identifier) {
            $module = $this->moduleProvider->getModule($identifier, $backendUser);
            if ($module !== null) {
                $menuItems[$identifier] = $languageService->sL($module->getTitle());
            }
        }

        foreach ($menuItems as $identifier => $title) {
            $item = GeneralUtility::makeInstance(MenuItem::class)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute($identifier))
                ->setTitle($title);
            if ($identifier === $currentModule->getIdentifier()) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }

        $template->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    final protected function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
