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
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Result\Status;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
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

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        LanguageServiceFactory $languageServiceFactory,

        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,
        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        /** @var Reporter[] $reporters */
        #[AutowireIterator(tag: 'monitoring.reporter', defaultPriorityMethod: 'getPriority')]
        private iterable $reporters,
        private MonitoringExecutionHandler $executionHandler,
        private MonitoringCacheManager $cacheManager,
        private MonitoringConfiguration $monitoringConfiguration,
        private UriBuilder $uriBuilder,
        private FormProtectionFactory $formProtectionFactory,
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
            'providerUnhealthyCount' => count(array_filter($providers, static fn(array $provider) => ($provider['isActive'] ?? false) && ($provider['isHealthy'] ?? true) === false)),
            'providerDegradedCount' => count(array_filter($providers, static fn(array $provider) => ($provider['isActive'] ?? false) && ($provider['status'] ?? null) === Status::Degraded->value)),
            'providerInterface' => MonitoringProvider::class,
            'flushProviderCacheUri' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_flush_provider_cache'),
            'serviceCards' => $this->buildCardsTemplateVariables(),
        ];

        $currentModule = $request->getAttribute('module');

        return $this->createModuleTemplate($request, $currentModule?->getIdentifier() ?? 'monitoring')
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Monitoring');
    }

    /**
     * Build template variables for all monitoring providers
     *
     * @return array<class-string<\mteu\Monitoring\Provider\MonitoringProvider>, array{
     *     name: string,
     *     isCached: bool,
     *     isActive: bool,
     *     isEnabled: bool,
     *     isHealthy?: bool,
     *     status?: string,
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

            $providerTemplateVariables[$monitoringProvider::class] = [
                'name' => $monitoringProvider->getName(),
                'isCached' => $monitoringProvider instanceof CacheableMonitoringProvider,
                'isEnabled' => $monitoringProvider->isEnabled(),
                'isActive' => $monitoringProvider->isActive(),
                'description' => $monitoringProvider->getDescription(),
            ];

            if (!$monitoringProvider->isActive() || ! $monitoringProvider->isEnabled()) {
                continue;
            }

            $result = $this->executionHandler->executeProvider($monitoringProvider);
            $providerTemplateVariables[$monitoringProvider::class]['isHealthy'] = $result->isHealthy();
            $providerTemplateVariables[$monitoringProvider::class]['status'] = $result->getStatus()->value;

            if ($result->hasSubResults()) {
                $providerTemplateVariables[$monitoringProvider::class]['subResults'] = $result->getSubResults();
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

    /**
     * @return array<string, array{
     *     iconIdentifier: string,
     *     title: string,
     *     body: string,
     *     url: string,
     *     linkTitle: string,
     *     linkIconIdentifier?: string,
     *     isExternalLink?: bool,
     * }>
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    private function buildCardsTemplateVariables(): array
    {
        return [
            'providers' => [
                'count' => $this->countActiveMonitoringProviders($this->monitoringProviders),
                'iconIdentifier' => 'actions-rocket',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.title'),
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':providers.card.body'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_providers'),
                'linkTitle' => $this->buildCardLinkLabel('providers.card.linkLabel', iterator_count($this->monitoringProviders)),
            ],
            'authorizers' => [
                'iconIdentifier' => 'actions-key',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.title'),
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':authorizers.card.body'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_authorizers'),
                'linkTitle' => $this->buildCardLinkLabel('authorizers.card.linkLabel', iterator_count($this->authorizers)),
            ],
            'reporters' => [
                'iconIdentifier' => 'actions-bullhorn',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.title'),
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':reporters.card.body'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_reporters'),
                'linkTitle' => $this->buildCardLinkLabel('reporters.card.linkLabel', iterator_count($this->reporters)),
            ],
            'documentation' => [
                'iconIdentifier' => 'actions-notebook-typoscript',
                'title' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.title'),
                'body' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.card.body'),
                'url' => 'https://github.com/mteu/typo3-monitoring/blob/main/Documentation/README.md',
                'isExternalLink' => true,
                'linkIconIdentifier' => 'actions-brand-github',
                'linkTitle' => $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':documentation.card.linkLabel'),
            ],
        ];
    }

    /**
     * @param iterable<MonitoringProvider> $providers
     */
    private function countActiveMonitoringProviders(iterable $providers): int
    {
        $count = 0;

        foreach (iterator_to_array($providers, true) as $provider) {
            if ($provider->isActive() && $provider->isEnabled()) {
                $count++;
            }
        }

        return $count;
    }

    private function buildCardLinkLabel(string $keyPrefix, int $count): string
    {
        $languageService = $this->getLanguageService();

        if ($count === 0) {
            return $languageService->sL(self::LOCALLANG_FILE . ':' . $keyPrefix . '.zero');
        }

        $key = $keyPrefix . ($count === 1 ? '.singular' : '.plural');

        return sprintf($languageService->sL(self::LOCALLANG_FILE . ':' . $key), $count);
    }
}
