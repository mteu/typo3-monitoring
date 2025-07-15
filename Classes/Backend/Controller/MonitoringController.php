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
use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
final class MonitoringController
{
    use AllowedMethodsTrait;
    use SlugifyCacheKeyTrait;

    /** @var non-empty-string $endpoint */
    private string $endpoint;

    /** @var non-empty-string $secret */
    private string $secret;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly HashService $hashService,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FlashMessageService $flashMessageService,
        private readonly LanguageServiceFactory $languageServiceFactory,
        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private readonly iterable $monitoringProviders,
        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $authorizers,
        private readonly MonitoringExecutionHandler $executionHandler,
        private readonly MonitoringCacheManager $cacheManager
    ) {
        $endpoint = $this->extensionConfiguration->get('typo3_monitoring', 'monitoring/endpoint');

        if (is_string($endpoint) && $endpoint !== '') {
            $this->endpoint = $endpoint;
        }

        $secret = $this->extensionConfiguration->get('typo3_monitoring', 'monitoring/secret');
        if (is_string($secret) && $secret !== '') {
            $this->secret = $secret;
        }
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

        /** @var NormalizedParams $params */
        $params = $request->getAttribute('normalizedParams');

        $templateVariables = [
            'providers' => [],
            'providerInterface' => MonitoringProvider::class,
            // This extension assumes the TokenAuthorizer to be used.
            // @todo: Make optional in later iteration
            'authHeaderName' => TokenAuthorizer::getAuthHeaderName(),
            'authToken' => $this->getAuthToken(),
            'endpoint' => $params->getRequestHost() . $this->endpoint,
            'flashMessageQueueIdentifier' => 'typo3_monitoring',
        ];

        foreach ($this->authorizers as $authorizer) {
            $templateVariables['authorizers'][$authorizer::class] = $authorizer::getPriority();
        }

        foreach ($this->monitoringProviders as $monitoringProvider) {
            $templateVariables['providers'][$monitoringProvider::class] = [
                'name' => $monitoringProvider->getName(),
                'isCached' => $monitoringProvider instanceof CacheableMonitoringProvider,
                'isActive' => $monitoringProvider->isActive(),
                'isHealthy' => $monitoringProvider->isHealthy(),
                'description' => $monitoringProvider->getDescription(),
            ];

            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $templateVariables['providers'][$monitoringProvider::class]['cacheLifetime'] = $monitoringProvider->getCacheLifetime();
            }

            $result = $this->executionHandler->executeProvider($monitoringProvider);

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
     */
    private function handleFlushProviderCache(string $providerClass, ServerRequestInterface $request): ResponseInterface
    {
        $success = $this->cacheManager->flushProviderCache($providerClass);
        $languageService = $this->getLanguageService();

        $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('typo3_monitoring');

        if ($success) {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf:provider.cache.flush.success.message'),
                    $providerClass
                ),
                $languageService->sL('LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf:provider.cache.flush.success.title'),
                ContextualFeedbackSeverity::OK
            );
        } else {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf:provider.cache.flush.error.message'),
                    $providerClass
                ),
                $languageService->sL('LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf:provider.cache.flush.error.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        $flashMessageQueue->addMessage($message);

        // Use the current request URI for redirect - this is the proper way in TYPO3 v13
        $currentUri = $request->getUri();
        $redirectUri = $currentUri->withQuery('');

        return new RedirectResponse((string)$redirectUri);
    }

    private function getLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    private function getAuthToken(): ?string
    {
        if ($this->endpoint === '' || $this->secret === '') {
            return null;
        }

        return $this->hashService->hmac($this->endpoint, $this->secret);
    }
}
