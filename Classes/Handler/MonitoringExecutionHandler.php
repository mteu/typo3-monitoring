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
use mteu\Monitoring\Result\Result;

/**
 * MonitoringExecutionHandler.
 * Handles execution of monitoring providers with caching support.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringExecutionHandler
{
    public function __construct(
        private MonitoringCacheManager $cacheManager,
    ) {}

    /**
     * Executes a monitoring provider with caching support if applicable.
     */
    public function executeProvider(MonitoringProvider $provider): Result
    {
        if ($provider instanceof CacheableMonitoringProvider) {
            return $this->executeWithCaching($provider);
        }

        return $provider->execute();
    }

    /**
     * Executes a cacheable provider with cache lookup and storage.
     */
    private function executeWithCaching(CacheableMonitoringProvider $provider): Result
    {
        $cacheKey = $provider->getCacheKey();

        $cachedResult = $this->cacheManager->getCachedResult($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $provider->execute();

        $cacheTags = [$provider::class];

        $this->cacheManager->setCachedResult(
            $cacheKey,
            $result,
            $cacheTags,
            $provider->getCacheLifetime()
        );

        return $result;
    }
}
