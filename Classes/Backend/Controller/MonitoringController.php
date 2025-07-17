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
use mteu\Monitoring\Configuration\Extension;
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
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
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

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

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
        private readonly Extension $extensionConfiguration,
        private readonly FlashMessageService $flashMessageService,
        private readonly LanguageServiceFactory $languageServiceFactory,
        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private readonly iterable $monitoringProviders,
        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $authorizers,
        private readonly MonitoringExecutionHandler $executionHandler,
        private readonly MonitoringCacheManager $cacheManager,
        private readonly UriBuilder $uriBuilder,
    ) {
        $endpoint = $this->extensionConfiguration->getEndpointFromConfiguration();

        if ($endpoint !== '') {
            $this->endpoint = $endpoint;
        }

        $secret = $this->extensionConfiguration->getSecretFromConfiguration();

        if ($secret !== '') {
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
        $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('typo3_monitoring');

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

        $flashMessageQueue->addMessage($message);

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

    private function getAuthToken(): string
    {
        return $this->hashService->hmac($this->endpoint, $this->secret);
    }
}
