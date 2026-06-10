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
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * AbstractSubModuleController.
 *
 * Shared scaffolding for the monitoring sub-modules. Renders a template within
 * the standard module chrome, exposes the doc header module menu so users can
 * switch between the overview and the sub-modules, and disables the automatic
 * reload button (a TYPO3 v14+ feature) for consistency with the main module.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract readonly class AbstractSubModuleController
{
    protected const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * Creates a ModuleTemplate with the shared sub-module chrome applied:
     * the doc header module menu and (on TYPO3 v14+) the disabled reload button.
     */
    protected function createModuleTemplate(ServerRequestInterface $request, string $bookmarkRoute = ''): ModuleTemplate
    {
        $template = $this->moduleTemplateFactory->create($request);
        $template->makeDocHeaderModuleMenu();

        $docHeaderComponent = $template->getDocHeaderComponent();

        //if (method_exists($docHeaderComponent, 'disableAutomaticReloadButton')) {
        //    $docHeaderComponent->disableAutomaticReloadButton();
        //}

        if ($bookmarkRoute !== '') {
            $docHeaderComponent->setShortcutContext(
                $bookmarkRoute,
                $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':route.label.' . $bookmarkRoute),
            );
        }

        return $template;
    }

    protected function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
