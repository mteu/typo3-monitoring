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

namespace mteu\Monitoring\Handler;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use Psr\Log\LoggerInterface;

/**
 * MonitoringExecutionHandler.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringExecutionHandler
{
    public function __construct(
        private MonitoringCacheManager $cacheManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Executes every enabled and active provider.
     *
     * @param iterable<MonitoringProvider> $providers
     * @return array<non-empty-string, Result>
     */
    public function collectResults(iterable $providers): array
    {
        $results = [];

        foreach ($providers as $provider) {
            if (!$provider->isEnabled() || !$provider->isActive()) {
                continue;
            }

            $name = $provider->getName();

            if (array_key_exists($name, $results)) {
                $this->logger->warning('Duplicate monitoring provider name. An earlier result is overwritten', [
                    'provider' => $name,
                    'class' => $provider::class,
                ]);
            }

            try {
                $results[$name] = $this->executeProvider($provider);
            } catch (\Throwable $e) {
                $this->logger->error('Monitoring provider threw during execution', [
                    'provider' => $name,
                    'exception' => $e->getMessage(),
                ]);

                $results[$name] = new MonitoringResult(
                    $name,
                    Status::Unhealthy,
                    sprintf('Provider threw during execution: %s', $e->getMessage()),
                );
            }
        }

        return $results;
    }

    public function executeProvider(MonitoringProvider $provider): Result
    {
        return $this->executeProviderWithMetadata($provider)->result;
    }

    public function executeProviderWithMetadata(MonitoringProvider $provider, bool $useCache = true): ProviderExecutionOutcome
    {
        return $provider instanceof CacheableMonitoringProvider
            ? $this->executeWithCaching($provider, $useCache)
            : $this->executeWithoutCaching($provider);
    }

    private function executeWithoutCaching(MonitoringProvider $provider): ProviderExecutionOutcome
    {
        return new ProviderExecutionOutcome($provider->execute(), false);
    }

    private function executeWithCaching(
        CacheableMonitoringProvider $provider,
        bool $useCache,
    ): ProviderExecutionOutcome {
        $cacheKey = $this->sanitizeIdentifier($provider->getCacheKey());

        if ($useCache) {
            $cachedResult = $this->cacheManager->getCachedResult($cacheKey);

            if ($cachedResult !== null) {
                return new ProviderExecutionOutcome($cachedResult, true);
            }
        }

        $result = $provider->execute();

        $this->cacheManager->setCachedResult(
            $cacheKey,
            $result,
            [$this->sanitizeIdentifier($provider::class)],
            $provider->getCacheLifetime(),
        );

        return new ProviderExecutionOutcome($result, false);
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return str_replace('\\', '_', $identifier);
    }
}
