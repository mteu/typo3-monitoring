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

namespace mteu\Monitoring\Tests\Functional\Cache;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MonitoringCacheManagerTest.
 *
 * Functional tests for MonitoringCacheManager focusing on our implementation.
 * Uses real TYPO3 cache but tests our logic, not TYPO3's cache behavior.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringCacheManager::class)]
final class MonitoringCacheManagerTest extends FunctionalTestCase
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

    private MonitoringCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = $this->get(MonitoringCacheManager::class);
        $this->cacheManager->flushAll();
    }

    #[Test]
    public function ourCacheManagerBasicOperationsWork(): void
    {
        $result = new MonitoringResult('test-service', true, 'Service is healthy');
        $cacheKey = 'test-cache-key';

        // Test store and retrieve
        $stored = $this->cacheManager->setCachedResult($cacheKey, $result);
        self::assertTrue($stored, 'Our implementation should store results');

        $retrieved = $this->cacheManager->getCachedResult($cacheKey);
        self::assertNotNull($retrieved, 'Our implementation should retrieve stored results');
        self::assertSame($result->getName(), $retrieved->getName());
        self::assertSame($result->isHealthy(), $retrieved->isHealthy());
        self::assertSame($result->getReason(), $retrieved->getReason());
    }

    #[Test]
    public function ourImplementationHandlesNonExistentKeys(): void
    {
        $result = $this->cacheManager->getCachedResult('non-existent-key');
        self::assertNull($result, 'Our implementation should return null for non-existent keys');
    }

    #[Test]
    public function ourDefaultLifetimeLogicWorks(): void
    {
        $defaultLifetime = $this->cacheManager->getCacheLifetime();
        self::assertSame(900, $defaultLifetime, 'Our default lifetime should be 15 minutes (900 seconds)');
        
        $result = new MonitoringResult('test-service', true);
        $stored = $this->cacheManager->setCachedResult('default-test', $result, [], 0);
        self::assertTrue($stored, 'Should store with default lifetime when 0 provided');
    }

    #[Test]
    public function ourExpirationHandlingWorks(): void
    {
        // Test that our implementation sets expiration times correctly
        $result = new MonitoringResult('test-service', true);
        $stored = $this->cacheManager->setCachedResult('expiration-test', $result, [], 3600);
        self::assertTrue($stored, 'Should store with custom lifetime');

        // Verify expiration time is set
        $expirationTime = $this->cacheManager->getCacheExpirationTime('expiration-test');
        self::assertNotNull($expirationTime, 'Our implementation should set expiration time');
        
        // Verify it's approximately 1 hour from now (allow some tolerance)
        $expectedTime = new \DateTimeImmutable('+3600 seconds');
        $timeDiff = abs($expirationTime->getTimestamp() - $expectedTime->getTimestamp());
        self::assertLessThan(10, $timeDiff, 'Expiration should be approximately correct');
    }

    #[Test]
    public function ourProviderCacheFlushWorks(): void
    {
        $result = new MonitoringResult('provider-test', true);
        $providerClass = 'mteu\\Monitoring\\Provider\\TestProvider';
        
        // Our flushProviderCache should work with real cache backend
        $flushed = $this->cacheManager->flushProviderCache($providerClass);
        self::assertTrue($flushed, 'Our provider cache flush should work');
    }
}