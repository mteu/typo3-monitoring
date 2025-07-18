<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-monitoring".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace mteu\Monitoring\Backend\Controller;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfigurationFactory;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * MonitoringController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class MonitoringController
{
    use AllowedMethodsTrait;
    use SlugifyCacheKeyTrait;

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';
    private const string FLASHMESSAGE_QUEUE_IDENTIFIER = 'ext_monitoring_message_queue';

    private MonitoringConfiguration $monitoringConfiguration;

    public function __construct(
        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        private ModuleTemplateFactory $moduleTemplateFactory,
        private HashService $hashService,
        private FlashMessageService $flashMessageService,
        private LanguageServiceFactory $languageServiceFactory,
        private MonitoringExecutionHandler $executionHandler,
        private MonitoringCacheManager $cacheManager,
        private MonitoringConfigurationFactory $monitoringConfigurationFactory,
        private UriBuilder $uriBuilder,
    ) {
        $this->monitoringConfiguration = $this->monitoringConfigurationFactory->create();
    }

    /**
     * @throws MethodNotAllowedException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        // Handle flush action if present
        $action = $request->getQueryParams()['action'] ?? '';
        /** @var string $providerClass */
        $providerClass = $request->getQueryParams()['providerClass'] ?? '';

        if ($action === 'flushProviderCache' && $providerClass !== '') {
            return $this->handleFlushProviderCache($providerClass, $request);
        }

        $template = $this->moduleTemplateFactory->create($request);
        $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier(self::FLASHMESSAGE_QUEUE_IDENTIFIER);

        /** @var NormalizedParams $params */
        $params = $request->getAttribute('normalizedParams');

        $templateVariables = [
            'providers' => [],
            'providerInterface' => MonitoringProvider::class,
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
            'monitoringMessageQueueIdentifier' => self::FLASHMESSAGE_QUEUE_IDENTIFIER,
        ];

        if ($this->monitoringConfiguration->tokenAuthorizerEnabled) {

            if ($this->monitoringConfiguration->tokenAuthorizerSecret === '') {
                $messageQueue->addMessage(
                    new FlashMessage(
                        message: $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':settings.api.secret.missing'),
                        severity: ContextualFeedbackSeverity::WARNING,
                        storeInSession: true,
                    )
                );
            } else {
                $templateVariables['authHeaderName'] = $this->monitoringConfiguration->tokenAuthorizerAuthHeaderName;
                $templateVariables['authToken'] = $this->hashService->hmac(
                    $this->monitoringConfiguration->endpoint,
                    $this->monitoringConfiguration->tokenAuthorizerSecret,
                );
            }
        }

        foreach ($this->authorizers as $authorizer) {

            $templateVariables['authorizers'][$authorizer::class] = $authorizer::getPriority();
        }

        foreach ($this->monitoringProviders as $monitoringProvider) {

            $result = $this->executionHandler->executeProvider($monitoringProvider);

            $templateVariables['providers'][$monitoringProvider::class] = [
                'name' => $monitoringProvider->getName(),
                'isCached' => $monitoringProvider instanceof CacheableMonitoringProvider,
                'isActive' => $monitoringProvider->isActive(),
                'isHealthy' => $result->isHealthy(),
                'description' => $monitoringProvider->getDescription(),
            ];

            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $templateVariables['providers'][$monitoringProvider::class]['cacheLifetime'] = $monitoringProvider->getCacheLifetime();
            }

            if ($result->hasSubResults()) {
                $templateVariables['providers'][$monitoringProvider::class]['subResults'] = $result->getSubResults();
            }

            // Check for cache expiration time
            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $cacheKey = $monitoringProvider->getCacheKey();
                $expirationTime = $this->cacheManager->getCacheExpirationTime($this->slugifyString($cacheKey));

                if ($expirationTime !== null) {
                    $templateVariables['providers'][$monitoringProvider::class]['cacheExpiresAt'] = $expirationTime;
                }
            }
        }

        return $template
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Monitoring');
    }

    /**
     * Handle flush cache action with flash messages and redirect
     * @throws RouteNotFoundException
     */
    private function handleFlushProviderCache(string $providerClass, ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->getLanguageService();
        $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier(self::FLASHMESSAGE_QUEUE_IDENTIFIER);

        if ($this->cacheManager->flushProviderCache($providerClass)) {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.success.message'),
                    $providerClass,
                ),
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.success.title'),
                ContextualFeedbackSeverity::OK,
            );
        } else {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.error.message'),
                    $providerClass,
                ),
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.error.title'),
                ContextualFeedbackSeverity::ERROR,
            );
        }

        $messageQueue->addMessage($message);

        /** @phpstan-ignore method.internalClass, new.internalClass */
        return new RedirectResponse(
            $this->uriBuilder->buildUriFromRequest($request),
        );
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
