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
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

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
final class MonitoringCacheManagerTest extends MonitoringFunctionalTestCase
{
    private MonitoringCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = $this->get(MonitoringCacheManager::class);
        $this->cacheManager->flushAll();
    }

    #[Test]
    public function cacheManagerHandlesStoreAndRetrieveOperations(): void
    {
        $result = new MonitoringResult('test-service', Status::Healthy, 'Service is healthy');
        $cacheKey = 'test-cache-key';

        $stored = $this->cacheManager->setCachedResult($cacheKey, $result);
        self::assertTrue($stored, 'Should store results successfully');

        $retrieved = $this->cacheManager->getCachedResult($cacheKey);
        self::assertNotNull($retrieved, 'Should retrieve stored results');
        self::assertSame($result->getName(), $retrieved->getName());
        self::assertSame($result->isHealthy(), $retrieved->isHealthy());
        self::assertSame($result->getReason(), $retrieved->getReason());
    }

    #[Test]
    public function returnsNullForNonExistentCacheKeys(): void
    {
        $result = $this->cacheManager->getCachedResult('non-existent-key');
        self::assertNull($result, 'Should return null for non-existent keys');
    }

    #[Test]
    public function appliesDefaultLifetimeWhenZeroProvided(): void
    {
        self::assertSame(900, $this->cacheManager->getCacheLifetime(), 'Default lifetime should be 15 minutes (900 seconds)');

        $stored = $this->cacheManager->setCachedResult('default-test', new MonitoringResult('test-service', Status::Healthy), [], 0);
        self::assertTrue($stored, 'Should store with default lifetime when 0 provided');

        // A lifetime of 0 must resolve to the 900s default, not "no expiry": the
        // stored entry's expiration has to sit ~900s out, not at "now".
        $expiresAt = $this->cacheManager->getCacheExpirationTime('default-test');
        self::assertNotNull($expiresAt);
        $secondsUntilExpiry = $expiresAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        self::assertEqualsWithDelta(900, $secondsUntilExpiry, 10, 'Zero lifetime must fall back to the 900s default');
    }

    #[Test]
    public function tracksExpirationTimeAccuratelyForCustomLifetime(): void
    {
        $result = new MonitoringResult('test-service', Status::Healthy);
        $stored = $this->cacheManager->setCachedResult('expiration-test', $result, [], 3600);
        self::assertTrue($stored, 'Should store with custom lifetime');

        $expirationTime = $this->cacheManager->getCacheExpirationTime('expiration-test');
        self::assertNotNull($expirationTime, 'Should set expiration time');

        $expectedTime = new \DateTimeImmutable('+3600 seconds');
        $timeDiff = abs($expirationTime->getTimestamp() - $expectedTime->getTimestamp());
        self::assertLessThan(10, $timeDiff, 'Expiration should be approximately correct');
    }

    #[Test]
    public function flushProviderCacheInvalidatesOnlyTheTargetedProvidersEntries(): void
    {
        $providerClass = 'mteu\\Monitoring\\Provider\\TestProvider';
        // flushProviderCache() flushes by the provider class turned into a cache tag.
        $providerTag = str_replace('\\', '_', $providerClass);

        $this->cacheManager->setCachedResult('target', new MonitoringResult('target', Status::Healthy), [$providerTag]);
        $this->cacheManager->setCachedResult('bystander', new MonitoringResult('bystander', Status::Healthy), ['other_provider_tag']);

        self::assertTrue($this->cacheManager->flushProviderCache($providerClass), 'Provider cache flush should succeed');

        self::assertNull($this->cacheManager->getCachedResult('target'), 'The targeted provider entry must be evicted');
        self::assertNotNull($this->cacheManager->getCachedResult('bystander'), 'Entries tagged for other providers must survive');
    }
}
