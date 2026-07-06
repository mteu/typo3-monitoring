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

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * ProviderController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class ProviderController extends AbstractSubModuleController
{
    use AllowedMethodsTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        LanguageServiceFactory $languageServiceFactory,
        ModuleProvider $moduleProvider,
        UriBuilder $uriBuilder,
        /** @var MonitoringProvider[] $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,
        private MonitoringExecutionHandler $executionHandler,
        private MonitoringCacheManager $cacheManager,
        private MonitoringConfiguration $monitoringConfiguration,
        private FormProtectionFactory $formProtectionFactory,
    ) {
        parent::__construct($moduleTemplateFactory, $languageServiceFactory, $moduleProvider, $uriBuilder);
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

        $enabledProviders = array_filter($providers, static fn(array $provider): bool => $provider['isEnabled']);
        $disabledProviders = array_filter($providers, static fn(array $provider): bool => !$provider['isEnabled']);

        $templateVariables = [
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
            'providers' => $providers,
            'enabledProviders' => $enabledProviders,
            'disabledProviders' => $disabledProviders,
            'providerInterface' => MonitoringProvider::class,
            'flushProviderCacheUri' => (string)$this->uriBuilder->buildUriFromRoute('monitoring_flush_provider_cache'),
        ];

        return $this->createModuleTemplate($request, 'monitoring_providers')
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Providers');
    }

    /**
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

            if (!$providerTemplateVariables[$monitoringProvider::class]['isEnabled'] || !$providerTemplateVariables[$monitoringProvider::class]['isActive']) {
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

        uasort($providerTemplateVariables, $this->compareByName(...));

        return $providerTemplateVariables;
    }

    /**
     * @param array{name: string} $a
     * @param array{name: string} $b
     */
    private function compareByName(array $a, array $b): int
    {
        return strcasecmp($a['name'], $b['name']);
    }
}
