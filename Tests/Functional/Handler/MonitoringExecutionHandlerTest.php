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

namespace mteu\Monitoring\Tests\Functional\Handler;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Tests\Functional\Fixtures\CacheableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\NonCacheableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\ShortCacheProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MonitoringExecutionHandlerTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringExecutionHandler::class)]
final class MonitoringExecutionHandlerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'monitoring',
        'typed_extconf',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'typo3_monitoring' => [
                        'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                        'options' => [],
                        'groups' => ['system'],
                    ],
                ],
            ],
        ],
    ];

    private MonitoringExecutionHandler $executionHandler;
    private MonitoringCacheManager $cacheManager;
    private CacheableProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executionHandler = $this->get(MonitoringExecutionHandler::class);
        $this->cacheManager = $this->get(MonitoringCacheManager::class);
        $this->provider = new CacheableProvider();

        // Clear cache before each test
        $this->cacheManager->flushAll();
    }

    #[Test]
    public function cacheableProviderResultsAreCachedOnFirstExecution(): void
    {
        // First execution should execute provider and cache result
        $result1 = $this->executionHandler->executeProvider($this->provider);

        self::assertTrue($result1->isHealthy());
        self::assertSame('test-cacheable-provider', $result1->getName());
        self::assertSame(1, $this->provider->getExecutionCount());
    }

    #[Test]
    public function cacheableProviderUsesCacheOnSecondExecution(): void
    {
        // First execution - should miss cache and execute provider
        $result1 = $this->executionHandler->executeProvider($this->provider);
        self::assertSame(1, $this->provider->getExecutionCount());

        // Second execution - should hit cache, skip provider execution
        $result2 = $this->executionHandler->executeProvider($this->provider);

        self::assertSame(1, $this->provider->getExecutionCount(), 'Provider should not execute again');
        self::assertTrue($result2->isHealthy());
        self::assertSame('test-cacheable-provider', $result2->getName());

        // Results should be equivalent but not same instance (deserialized from cache)
        self::assertEquals($result1->isHealthy(), $result2->isHealthy());
        self::assertEquals($result1->getName(), $result2->getName());
    }

    #[Test]
    public function nonCacheableProviderAlwaysExecutes(): void
    {
        $nonCacheableProvider = new NonCacheableProvider();

        // First execution
        $result1 = $this->executionHandler->executeProvider($nonCacheableProvider);
        self::assertSame(1, $nonCacheableProvider->getExecutionCount());

        // Second execution should execute again (no caching)
        $result2 = $this->executionHandler->executeProvider($nonCacheableProvider);
        self::assertSame(2, $nonCacheableProvider->getExecutionCount());

        self::assertTrue($result1->isHealthy());
        self::assertTrue($result2->isHealthy());
    }

    #[Test]
    public function cacheInvalidationWorksCorrectly(): void
    {
        // First execution - cache miss
        $result1 = $this->executionHandler->executeProvider($this->provider);
        self::assertSame(1, $this->provider->getExecutionCount());

        // Second execution - cache hit
        $result2 = $this->executionHandler->executeProvider($this->provider);
        self::assertSame(1, $this->provider->getExecutionCount());

        // Flush cache for this provider
        $flushed = $this->cacheManager->flushProviderCache($this->provider::class);
        self::assertTrue($flushed, 'Cache flush should succeed');

        // Third execution - cache miss again after flush
        $result3 = $this->executionHandler->executeProvider($this->provider);
        self::assertSame(2, $this->provider->getExecutionCount(), 'Provider should execute again after cache flush');

        self::assertTrue($result3->isHealthy());
    }

    #[Test]
    public function cacheExpirationIsTracked(): void
    {
        // Execute provider to cache result
        $this->executionHandler->executeProvider($this->provider);

        // Check cache expiration time is set
        $cacheKey = $this->provider->getCacheKey();
        $expirationTime = $this->cacheManager->getCacheExpirationTime($cacheKey);

        self::assertNotNull($expirationTime, 'Cache expiration time should be set');
        self::assertGreaterThan(new \DateTimeImmutable(), $expirationTime, 'Cache should expire in the future');
    }

    #[Test]
    public function shortLivedCacheExpires(): void
    {
        $shortCacheProvider = new ShortCacheProvider();

        // First execution - cache result with 1 second TTL
        $result1 = $this->executionHandler->executeProvider($shortCacheProvider);
        self::assertSame(1, $shortCacheProvider->getExecutionCount());

        // Wait for cache to expire
        sleep(2);

        // Second execution should miss cache (expired) and execute provider again
        $result2 = $this->executionHandler->executeProvider($shortCacheProvider);

        // FIXED: Expired cache should not be used, provider should execute again
        self::assertSame(2, $shortCacheProvider->getExecutionCount(), 'Expired cache should not be used');

        self::assertTrue($result1->isHealthy());
        self::assertTrue($result2->isHealthy());
    }

    #[Test]
    public function cacheKeyCollisionTest(): void
    {
        $provider1 = new CacheableProvider('provider-1');
        $provider2 = new CacheableProvider('provider_1'); // Different but might collide after slugification

        // Execute both providers
        $result1 = $this->executionHandler->executeProvider($provider1);
        $result2 = $this->executionHandler->executeProvider($provider2);

        // Both should have executed (no cache collision)
        self::assertSame(1, $provider1->getExecutionCount());
        self::assertSame(1, $provider2->getExecutionCount());

        // Verify cache keys are different
        $key1 = $provider1->getCacheKey();
        $key2 = $provider2->getCacheKey();

        self::assertNotEquals($key1, $key2, 'Cache keys should be unique to prevent collisions');
    }

    #[Test]
    public function cacheMissingBackendHandling(): void
    {
        // Create a separate MonitoringCacheManager with a non-existent cache identifier
        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache does not exist', 1234567890));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManagerMock);

        // This should handle NoSuchCacheException gracefully
        $result = $monitoringCacheManager->getCachedResult('test-key');
        self::assertNull($result, 'Should return null when cache backend is missing');

        $success = $monitoringCacheManager->setCachedResult('test-key', new MonitoringResult('test', true));
        self::assertFalse($success, 'Should return false when cache backend is missing');
    }
}
