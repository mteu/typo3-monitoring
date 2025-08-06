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

namespace mteu\Monitoring\Tests\Unit\Cache;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Result\CachedMonitoringResult;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;

/**
 * MonitoringCacheManagerTest.
 *
 * Focuses on testing MonitoringCacheManager's own logic, not TYPO3 cache behavior.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringCacheManager::class)]
final class MonitoringCacheManagerTest extends TestCase
{
    private MonitoringCacheManager $monitoringCacheManager;

    protected function setUp(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $this->monitoringCacheManager = new MonitoringCacheManager($cacheManager);
    }

    #[Test]
    public function returnsNullWhenCacheBackendUnavailable(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1234567890));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);
        $result = $monitoringCacheManager->getCachedResult('test-key');

        self::assertNull($result, 'Should handle missing cache gracefully');
    }

    #[Test]
    public function detectsAndHandlesExpiredCacheEntries(): void
    {
        $monitoringResult = new MonitoringResult('test', true);
        $expiredCachedResult = new CachedMonitoringResult(
            $monitoringResult,
            new \DateTimeImmutable('-20 minutes'),
            900
        );

        self::assertTrue($expiredCachedResult->isExpired(), 'CachedMonitoringResult should detect expiration');

        $validCachedResult = new CachedMonitoringResult(
            $monitoringResult,
            new \DateTimeImmutable('-5 minutes'),
            900
        );

        self::assertFalse($validCachedResult->isExpired(), 'CachedMonitoringResult should detect valid cache');
    }

    #[Test]
    public function calculatesExpirationTimeUsingCachedAtAndLifetime(): void
    {
        $cachedAt = new \DateTimeImmutable('-5 minutes');
        $lifetime = 900;
        $expectedExpiration = $cachedAt->add(new \DateInterval('PT' . $lifetime . 'S'));

        $monitoringResult = new MonitoringResult('test', true);
        $cachedResult = new CachedMonitoringResult($monitoringResult, $cachedAt, $lifetime);

        $actualExpiration = $cachedResult->getExpiresAt();

        self::assertEquals($expectedExpiration->getTimestamp(), $actualExpiration->getTimestamp(), 'Expiration time should be calculated correctly');
        self::assertSame($lifetime, $cachedResult->getLifetime(), 'Lifetime should be preserved');
        self::assertSame($monitoringResult, $cachedResult->getResult(), 'Original result should be preserved');
    }

    #[Test]
    public function returnsFalseWhenCacheBackendUnavailableForStorage(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417132));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);
        $result = $monitoringCacheManager->setCachedResult('test-key', new MonitoringResult('test', true));

        self::assertFalse($result, 'Should handle missing cache gracefully');
    }

    #[Test]
    public function getCacheLifetimeReturnsDefaultValue(): void
    {
        $result = $this->monitoringCacheManager->getCacheLifetime();

        self::assertSame(900, $result, 'Should return 15 minutes (900 seconds) as default');
    }

    #[Test]
    public function returnsFalseWhenCacheBackendUnavailableForTagFlush(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417126));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);
        $result = $monitoringCacheManager->flushByTags(['tag1']);

        self::assertFalse($result, 'Should handle missing cache gracefully');
    }

    #[Test]
    public function flushProviderCacheConvertsClassNameToTag(): void
    {
        $providerClass = 'App\\Provider\\TestProvider';
        $expectedTag = 'App_Provider_TestProvider';

        $actualTag = str_replace('\\', '_', $providerClass);

        self::assertSame($expectedTag, $actualTag, 'Class name should be converted to valid cache tag');
    }

    #[Test]
    public function returnsFalseWhenCacheBackendUnavailableForKeyFlush(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417122));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);
        $result = $monitoringCacheManager->flushByCacheKey('test-key');

        self::assertFalse($result, 'Should handle missing cache gracefully');
    }

    #[Test]
    public function returnsFalseWhenCacheBackendUnavailableForFlushAll(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417116));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);
        $result = $monitoringCacheManager->flushAll();

        self::assertFalse($result, 'Should handle missing cache gracefully');
    }

    #[Test]
    public function getCacheThrowsExceptionWhenCacheBackendMissing(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417109));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);

        $this->expectException(NoSuchCacheException::class);
        $monitoringCacheManager->getCache();
    }
}
