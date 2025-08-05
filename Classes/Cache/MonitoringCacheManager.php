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

namespace mteu\Monitoring\Cache;

use mteu\Monitoring\Result\Result;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * MonitoringCacheManager.
 *
 * Manages caching for monitoring results with expiration tracking and tag-based invalidation.
 * Basically a wrapper around TYPO3\CMS\Core\Cache\CacheManager for storing, retrieving, and managing cached
 * monitoring results.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringCacheManager
{
    private const string CACHE_IDENTIFIER = 'typo3_monitoring';

    public function __construct(
        private CacheManager $cacheManager,
    ) {}

    /**
     * Retrieves a cached monitoring result by cache key.
     *
     * @param string $cacheKey The cache key to retrieve
     * @return Result|null The cached result or null if not found or expired
     */
    public function getCachedResult(string $cacheKey): ?Result
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

            if ($cache->has($cacheKey)) {
                $cachedData = $cache->get($cacheKey);

                if ($cachedData instanceof CachedMonitoringResult) {
                    if (!$cachedData->isExpired()) {
                        return $cachedData->getResult();
                    }

                    // Remove expired cache entry
                    $cache->remove($cacheKey);
                }
            }

        } catch (NoSuchCacheException) {
            return null;
        }

        return null;
    }

    /**
     * Gets cache expiration time for a specific cache key.
     *
     * @param string $cacheKey The cache key to check
     * @return \DateTimeImmutable|null The expiration time or null if not found
     */
    public function getCacheExpirationTime(string $cacheKey): ?\DateTimeImmutable
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

            if ($cache->has($cacheKey)) {
                $cachedData = $cache->get($cacheKey);

                if ($cachedData instanceof CachedMonitoringResult) {
                    return $cachedData->getExpiresAt();
                }
            }
        } catch (NoSuchCacheException) {
            return null;
        }

        return null;
    }

    /**
     * Stores a monitoring result in cache with optional tags and custom lifetime.

     * @param string[] $cacheTags Optional cache tags for invalidation
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function setCachedResult(
        string $cacheKey,
        Result $result,
        array $cacheTags = [],
        int $cacheLifeTime = 0,
    ): bool {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

            $lifetime = $cacheLifeTime === 0 ? $this->getCacheLifetime() : $cacheLifeTime;

            $cachedResult = new CachedMonitoringResult(
                $result,
                new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())),
                $lifetime
            );

            $cache->set(
                $cacheKey,
                $cachedResult,
                $cacheTags,
                $lifetime,
            );

            return true;
        } catch (NoSuchCacheException) {
            return false;
        }
    }

    /**
     * Gets the default cache lifetime in seconds.
     *
     * @return int Default cache lifetime (15 minutes)
     */
    public function getCacheLifetime(): int
    {
        return 60 * 15;
    }

    /**
     * @param string[] $tags Array of cache tags to flush
     */
    public function flushByTags(array $tags): bool
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
            $cache->flushByTags($tags);
            return true;
        } catch (NoSuchCacheException) {
            return false;
        }
    }

    /**
     * Flush cache for a specific provider class.
     */
    public function flushProviderCache(string $providerClass): bool
    {
        return $this->flushByTags([$providerClass]);
    }

    public function flushByCacheKey(string $cacheKey): bool
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
            $cache->remove($cacheKey);
            return true;
        } catch (NoSuchCacheException) {
            return false;
        }
    }

    public function flushAll(): bool
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
            $cache->flush();
            return true;
        } catch (NoSuchCacheException) {
            return false;
        }
    }

    /**
     * @throws NoSuchCacheException If the cache is not configured
     */
    public function getCache(): FrontendInterface
    {
        return $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
    }
}
