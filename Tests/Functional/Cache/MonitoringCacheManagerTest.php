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
    public function cacheManagerHandlesStoreAndRetrieveOperations(): void
    {
        $result = new MonitoringResult('test-service', true, 'Service is healthy');
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
        $defaultLifetime = $this->cacheManager->getCacheLifetime();
        self::assertSame(900, $defaultLifetime, 'Default lifetime should be 15 minutes (900 seconds)');

        $result = new MonitoringResult('test-service', true);
        $stored = $this->cacheManager->setCachedResult('default-test', $result, [], 0);
        self::assertTrue($stored, 'Should store with default lifetime when 0 provided');
    }

    #[Test]
    public function tracksExpirationTimeAccuratelyForCustomLifetime(): void
    {
        $result = new MonitoringResult('test-service', true);
        $stored = $this->cacheManager->setCachedResult('expiration-test', $result, [], 3600);
        self::assertTrue($stored, 'Should store with custom lifetime');

        $expirationTime = $this->cacheManager->getCacheExpirationTime('expiration-test');
        self::assertNotNull($expirationTime, 'Should set expiration time');

        $expectedTime = new \DateTimeImmutable('+3600 seconds');
        $timeDiff = abs($expirationTime->getTimestamp() - $expectedTime->getTimestamp());
        self::assertLessThan(10, $timeDiff, 'Expiration should be approximately correct');
    }

    #[Test]
    public function flushProviderCacheInvalidatesCorrectEntries(): void
    {
        $result = new MonitoringResult('provider-test', true);
        $providerClass = 'mteu\\Monitoring\\Provider\\TestProvider';

        $flushed = $this->cacheManager->flushProviderCache($providerClass);
        self::assertTrue($flushed, 'Provider cache flush should succeed');
    }
}
