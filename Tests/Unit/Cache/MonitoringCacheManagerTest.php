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
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Result\CachedMonitoringResult;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * MonitoringCacheManagerTest.
 *
 * Focuses on testing MonitoringCacheManager's own logic, not TYPO3 cache
 * behavior: graceful degradation when the cache backend is missing, the
 * expired-entry eviction branch, and the configured lifetime fallback. The
 * real store/retrieve round trip lives in the functional test.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringCacheManager::class)]
final class MonitoringCacheManagerTest extends TestCase
{
    /**
     * @param callable(MonitoringCacheManager): mixed $operation
     */
    #[Test]
    #[DataProvider('degradingOperationsDataProvider')]
    public function operationsDegradeGracefullyWhenCacheBackendIsUnavailable(
        callable $operation,
        mixed $expected,
    ): void {
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1234567890));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);

        self::assertSame($expected, $operation($monitoringCacheManager));
    }

    /**
     * @return \Generator<string, array{callable(MonitoringCacheManager): mixed, mixed}>
     */
    public static function degradingOperationsDataProvider(): \Generator
    {
        yield 'getCachedResult returns null' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->getCachedResult('key'),
            null,
        ];
        yield 'getCacheExpirationTime returns null' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->getCacheExpirationTime('key'),
            null,
        ];
        yield 'flushByTags returns false' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->flushByTags(['tag1']),
            false,
        ];
        yield 'flushProviderCache returns false' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->flushProviderCache('App\\Provider\\TestProvider'),
            false,
        ];
        yield 'flushByCacheKey returns false' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->flushByCacheKey('key'),
            false,
        ];
        yield 'flushAll returns false' => [
            static fn(MonitoringCacheManager $manager): mixed => $manager->flushAll(),
            false,
        ];
    }

    #[Test]
    public function setCachedResultDegradesGracefullyWhenCacheBackendIsUnavailable(): void
    {
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1234567891));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);

        self::assertFalse($monitoringCacheManager->setCachedResult('key', new MonitoringResult('test', Status::Healthy)));
    }

    #[Test]
    public function getCachedResultEvictsExpiredEntryAndReturnsNull(): void
    {
        $expired = new CachedMonitoringResult(
            new MonitoringResult('test', Status::Healthy),
            new \DateTimeImmutable('-20 minutes'),
            900,
        );

        $frontend = $this->createMock(FrontendInterface::class);
        $frontend->method('has')->willReturn(true);
        $frontend->method('get')->willReturn($expired);
        $frontend->expects(self::once())->method('remove')->with('test-key');

        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')->willReturn($frontend);

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);

        self::assertNull($monitoringCacheManager->getCachedResult('test-key'));
    }

    #[Test]
    public function getCacheLifetimeHonorsConfiguredDefault(): void
    {
        $monitoringCacheManager = new MonitoringCacheManager(
            self::createStub(CacheManager::class),
            $this->createConfiguration(cacheDefaultLifetime: 60),
        );

        self::assertSame(60, $monitoringCacheManager->getCacheLifetime());
    }

    #[Test]
    public function getCacheLifetimeFallsBackToBuiltInDefaultWhenConfigurationOmitted(): void
    {
        $monitoringCacheManager = new MonitoringCacheManager(self::createStub(CacheManager::class));

        self::assertSame(900, $monitoringCacheManager->getCacheLifetime());
    }

    #[Test]
    public function getCacheThrowsExceptionWhenCacheBackendMissing(): void
    {
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1754417109));

        $monitoringCacheManager = new MonitoringCacheManager($cacheManager);

        $this->expectException(NoSuchCacheException::class);
        $monitoringCacheManager->getCache();
    }

    private function createConfiguration(int $cacheDefaultLifetime): MonitoringConfiguration
    {
        return new MonitoringConfiguration(
            new TokenAuthorizerConfiguration(true, 10, 'secret', 'X-TYPO3-MONITORING-AUTH'),
            new AdminUserAuthorizerConfiguration(),
            new EmailReporterConfiguration(),
            new ReportDispatcherConfiguration(),
            cacheDefaultLifetime: $cacheDefaultLifetime,
        );
    }
}
