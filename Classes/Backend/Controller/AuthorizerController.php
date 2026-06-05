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

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * AuthorizerController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class AuthorizerController
{
    use AllowedMethodsTrait;

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        private ModuleTemplateFactory $moduleTemplateFactory,
        private MonitoringConfiguration $monitoringConfiguration,
        private UriBuilder $uriBuilder,
        private HashService $hashService,
        private IconFactory $iconFactory,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @throws MethodNotAllowedException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        $template = $this->moduleTemplateFactory->create($request);

        /** @var NormalizedParams $params */
        $params = $request->getAttribute('normalizedParams');

        $this->registerDocHeaderButtons($template);

        $templateVariables = [
            'authorizers' => $this->buildAuthorizerTemplateVariables(),
            'authorizerInterface' => Authorizer::class,
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
        ];

        return $template
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Authorizers');
    }

    private function registerDocHeaderButtons(ModuleTemplate $template): void
    {
        $buttonBar = $template->getDocHeaderComponent()->getButtonBar();

        $backButton = (new LinkButton())
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('monitoring'))
            ->setTitle($this->getLanguageService()->sL(self::LOCALLANG_FILE . ':module.backToOverview'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL));

        $buttonBar->addButton($backButton, ButtonBar::BUTTON_POSITION_LEFT);
    }

    /**
     * @return array<class-string, array{isActive: bool, priority: int}>
     */
    private function collectAuthorizerStatuses(): array
    {
        $statuses = [];

        foreach ($this->authorizers as $authorizer) {
            $statuses[$authorizer::class] = [
                'isActive' => $authorizer->isActive(),
                'priority' => $authorizer::getPriority(),
                'description' => $authorizer->getDescription(),
            ];
        }

        return $statuses;
    }

    private function generateAuthToken(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        return $this->hashService->hmac($this->monitoringConfiguration->endpoint, $secret);
    }

    /**
     * Process authorizers and build template variables
     *
     * @return array{}|non-empty-array<class-string, array{authHeaderName: string, authToken?: string}|array{isActive: bool, priority: int, authHeaderName?: string, authToken?: string}>
     */
    private function buildAuthorizerTemplateVariables(): array
    {
        $templateVariables = $this->collectAuthorizerStatuses();

        if ($templateVariables === []) {
            return [];
        }

        $tokenConfig = $this->monitoringConfiguration->tokenAuthorizerConfiguration;

        if ($tokenConfig->secret === '') {
            $templateVariables[TokenAuthorizer::class]['emptySecret'] = true;
            $templateVariables[TokenAuthorizer::class]['isEnabled'] = $tokenConfig->enabled;

            return $templateVariables;
        }

        if (!$tokenConfig->isEnabled()) {
            return $templateVariables;
        }

        $templateVariables[TokenAuthorizer::class]['authHeaderName'] = $tokenConfig->authHeaderName;
        $templateVariables[TokenAuthorizer::class]['authToken'] = $this->generateAuthToken($tokenConfig->secret);

        return $templateVariables;
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
