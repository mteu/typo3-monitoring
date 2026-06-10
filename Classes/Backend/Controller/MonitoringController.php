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

use mteu\Monitoring\Backend\Presenter\NavigationPresenter;
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Status\ServiceStatusRegistry;
use mteu\Monitoring\Status\ServiceType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * MonitoringController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class MonitoringController extends AbstractSubModuleController
{
    use AllowedMethodsTrait;
    private const string FLASHMESSAGE_QUEUE_IDENTIFIER = 'ext_monitoring_message_queue';

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        LanguageServiceFactory $languageServiceFactory,

        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        private ServiceStatusRegistry $serviceStatusRegistry,

        private NavigationPresenter $navigationPresenter,
        private MonitoringExecutionHandler $executionHandler,
        private MonitoringCacheManager $cacheManager,
        private MonitoringConfiguration $monitoringConfiguration,
        private UriBuilder $uriBuilder,
        private FormProtectionFactory $formProtectionFactory,
        private IconFactory $iconFactory,
    ) {
        parent::__construct($moduleTemplateFactory, $languageServiceFactory);
    }

    /**
     * @throws MethodNotAllowedException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        /** @var NormalizedParams $params */
        $params = $request->getAttribute('normalizedParams');

        $providers = $this->buildProviderTemplateVariables($request);

        $templateVariables = [
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
            'providers' => $providers,
            'providerUnhealthyCount' => count(array_filter($providers, static fn(array $p) => ($p['isActive'] ?? false) && ($p['isHealthy'] ?? true) === false)),
            'providerInterface' => MonitoringProvider::class,
            'monitoringMessageQueueIdentifier' => self::FLASHMESSAGE_QUEUE_IDENTIFIER,
            'flushProviderCacheUri' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_flush_provider_cache'),
            'serviceCards' => $this->buildCardsTemplateVariables(),
            'navigation' => $this->navigationPresenter->present(self::class),
        ];

        return $this->createModuleTemplate($request, 'monitoring')
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Monitoring');
    }

    private function registerDocHeaderButtons(ModuleTemplate $template): void
    {
        $buttonBar = $template->getDocHeaderComponent()->getButtonBar();

        $authorizerButton = (new LinkButton())
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('monitoring.authorizers'))
            ->setTitle($this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.title'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-lock', IconSize::SMALL));

        $buttonBar->addButton($authorizerButton, ButtonBar::BUTTON_POSITION_LEFT);
    }

    /**
     * Build template variables for all monitoring providers
     *
     * @return array<class-string<\mteu\Monitoring\Provider\MonitoringProvider>, array{
     *     name: string,
     *     isCached: bool,
     *     isActive: bool,
     *     isHealthy?: bool,
     *     description: string,
     *     cacheLifetime?: int,
     *     subResults?: array<\mteu\Monitoring\Result\Result>,
     *     cacheExpiresAt?: \DateTimeImmutable,
     *     flushToken?: string
     * }>
     */
    private function buildProviderTemplateVariables(ServerRequestInterface $request): array
    {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $providerTemplateVariables = [];

        foreach ($this->monitoringProviders as $monitoringProvider) {

            $isActive = $monitoringProvider->isActive();

            if ($isActive === false) {
                continue;
            }

            $providerTemplateVariables[$monitoringProvider::class] = [
                'name' => $monitoringProvider->getName(),
                'isCached' => $monitoringProvider instanceof CacheableMonitoringProvider,
                'isActive' => $isActive,
                'description' => $monitoringProvider->getDescription(),
            ];

            if ($isActive) {
                $result = $this->executionHandler->executeProvider($monitoringProvider);
                $providerTemplateVariables[$monitoringProvider::class]['isHealthy'] = $result->isHealthy();

                if ($result->hasSubResults()) {
                    $providerTemplateVariables[$monitoringProvider::class]['subResults'] = $result->getSubResults();
                }
            }

            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $providerTemplateVariables[$monitoringProvider::class]['cacheLifetime'] = $monitoringProvider->getCacheLifetime();
                $providerTemplateVariables[$monitoringProvider::class]['flushToken'] = $formProtection->generateToken(
                    FlushProviderCacheController::CSRF_TOKEN_FORM_NAME,
                    FlushProviderCacheController::CSRF_TOKEN_ACTION,
                    $monitoringProvider::class,
                );
            }

            // Check for cache expiration time
            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $expirationTime = $this->cacheManager->getCacheExpirationTime($monitoringProvider->getCacheKey());

                if ($expirationTime !== null) {
                    $providerTemplateVariables[$monitoringProvider::class]['cacheExpiresAt'] = $expirationTime;
                }
            }
        }

        return $providerTemplateVariables;
    }

    private function buildCardsTemplateVariables(): array
    {
        return [
            'providers' => [
                'iconIdentifier' => 'actions-rocket',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.title'),
                'subTitle' => null,
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.card.body'),
                'count' => $this->serviceStatusRegistry->count(ServiceType::Provider),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_providers'),
                'linkTitle' => sprintf(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.card.linkLabel'),
                    $this->serviceStatusRegistry->count(ServiceType::Provider)['discovered'],
                ),
            ],
            'authorizers' => [
                'iconIdentifier' => 'actions-key',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.title'),
                'subTitle' => $this->serviceStatusRegistry->count(ServiceType::Provider)['active'],
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.card.body'),
                'count' => $this->serviceStatusRegistry->count(ServiceType::Authorizer),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_authorizers'),
                'linkTitle' => sprintf(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.card.linkLabel'),
                    $this->serviceStatusRegistry->count(ServiceType::Authorizer)['discovered'],
                ),
            ],
            'reporters' => [
                'iconIdentifier' => 'actions-bullhorn',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.title'),
                'subTitle' => null,
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.card.body'),
                'count' => $this->serviceStatusRegistry->count(ServiceType::Reporter),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_reporters'),
                'linkTitle' => sprintf(
                    $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.card.linkLabel'),
                    $this->serviceStatusRegistry->count(ServiceType::Reporter)['discovered'],
                ),
            ],
            'documentation' => [
                'iconIdentifier' => 'actions-notebook-typoscript',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.title'),
                'subTitle' => null,
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.card.body'),
                'url' => 'https://github.com/mteu/typo3-monitoring/blob/main/Documentation/README.md',
                'isExternalLink' => true,
                'linkIconIdentifier' => 'actions-brand-github',
                'linkTitle' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.card.linkLabel'),
            ],
        ];
    }
}
