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

namespace mteu\Monitoring\Handler;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;

/**
 * MonitoringExecutionService.
 *
 * Handles execution of monitoring providers with caching support.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringExecutionHandler
{
    use SlugifyCacheKeyTrait;

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
        $cacheKey = $this->slugifyString(
            $provider->getCacheKey(),
        );

        $cachedResult = $this->cacheManager->getCachedResult($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $provider->execute();

        $cacheTags = [$this->slugifyString($provider::class)];

        $this->cacheManager->setCachedResult(
            $cacheKey,
            $result,
            $cacheTags,
            $provider->getCacheLifetime()
        );

        return $result;
    }
}
