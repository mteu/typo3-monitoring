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
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MiddlewareStatusProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;

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

    private const string FLASHMESSAGE_QUEUE_IDENTIFIER = 'ext_monitoring_message_queue';

    public function __construct(
        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        private ModuleTemplateFactory $moduleTemplateFactory,
        private MonitoringExecutionHandler $executionHandler,
        private MonitoringCacheManager $cacheManager,
        private MonitoringConfiguration $monitoringConfiguration,
        private UriBuilder $uriBuilder,
        private HashService $hashService,
        private FormProtectionFactory $formProtectionFactory,
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

        $templateVariables = [
            'authorizers' => $this->buildAuthorizerTemplateVariables(),
            'authorizerInterface' => Authorizer::class,
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
            'middlewareStatusResult' => $this->executionHandler->executeProvider(
                $this->getMiddlewareStatusProvider(),
            ),
            'providers' => $this->buildProviderTemplateVariables($request),
            'providerInterface' => MonitoringProvider::class,
            'monitoringMessageQueueIdentifier' => self::FLASHMESSAGE_QUEUE_IDENTIFIER,
            'flushProviderCacheUri' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_flush_provider_cache'),
        ];

        return $template
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Monitoring');
    }

    private function getMiddlewareStatusProvider(): MonitoringProvider
    {
        foreach ($this->monitoringProviders as $service) {
            if ($service instanceof MiddlewareStatusProvider) {
                return $service;
            }
        }

        throw new \LogicException('MiddlewareStatusProvider not found among tagged services.');
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
     * Build template variables for all monitoring providers
     *
     * @return array<class-string<\mteu\Monitoring\Provider\MonitoringProvider>, array{
     *     name: string,
     *     isCached: bool,
     *     isActive: bool,
     *     isHealthy: bool,
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

            // Don't execute and display this meta-provider in the backend.
            if ($monitoringProvider instanceof MiddlewareStatusProvider) {
                continue;
            }

            $result = $this->executionHandler->executeProvider($monitoringProvider);

            $providerTemplateVariables[$monitoringProvider::class] = [
                'name' => $monitoringProvider->getName(),
                'isCached' => $monitoringProvider instanceof CacheableMonitoringProvider,
                'isActive' => $monitoringProvider->isActive(),
                'isHealthy' => $result->isHealthy(),
                'description' => $monitoringProvider->getDescription(),
            ];

            if ($monitoringProvider instanceof CacheableMonitoringProvider) {
                $providerTemplateVariables[$monitoringProvider::class]['cacheLifetime'] = $monitoringProvider->getCacheLifetime();
                $providerTemplateVariables[$monitoringProvider::class]['flushToken'] = $formProtection->generateToken(
                    FlushProviderCacheController::CSRF_TOKEN_FORM_NAME,
                    FlushProviderCacheController::CSRF_TOKEN_ACTION,
                    $monitoringProvider::class,
                );
            }

            if ($result->hasSubResults()) {
                $providerTemplateVariables[$monitoringProvider::class]['subResults'] = $result->getSubResults();
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

        if (!$tokenConfig->isEnabled()) {
            return $templateVariables;
        }

        $templateVariables[TokenAuthorizer::class]['authHeaderName'] = $tokenConfig->authHeaderName;

        $secret = $tokenConfig->secret;

        if ($secret === '') {
            return $templateVariables;
        }

        $templateVariables[TokenAuthorizer::class]['authToken'] = $this->generateAuthToken($tokenConfig->secret);

        return $templateVariables;
    }
}
