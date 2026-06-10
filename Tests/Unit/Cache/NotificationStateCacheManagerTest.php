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

use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Reporter\NotificationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * NotificationStateCacheManagerTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(NotificationStateCacheManager::class)]
final class NotificationStateCacheManagerTest extends TestCase
{
    #[Test]
    public function loadReturnsPersistedState(): void
    {
        $state = new NotificationState('fingerprint', 1_000, false);

        $frontend = self::createStub(FrontendInterface::class);
        $frontend->method('has')->willReturn(true);
        $frontend->method('get')->willReturn($state);

        self::assertSame($state, $this->manager($frontend)->load());
    }

    #[Test]
    public function loadReturnsNullWhenNoStateWasPersisted(): void
    {
        $frontend = self::createStub(FrontendInterface::class);
        $frontend->method('has')->willReturn(false);

        self::assertNull($this->manager($frontend)->load());
    }

    #[Test]
    public function loadReturnsNullForForeignCachePayload(): void
    {
        $frontend = self::createStub(FrontendInterface::class);
        $frontend->method('has')->willReturn(true);
        $frontend->method('get')->willReturn(['not' => 'a state object']);

        self::assertNull($this->manager($frontend)->load());
    }

    #[Test]
    public function loadReturnsNullWhenCacheBackendIsUnavailable(): void
    {
        self::assertNull($this->managerWithoutCache()->load());
    }

    #[Test]
    public function saveStoresStateAndReportsSuccess(): void
    {
        $state = new NotificationState(null, 2_000, true);

        $frontend = $this->createMock(FrontendInterface::class);
        $frontend
            ->expects(self::once())
            ->method('set')
            ->with('notification_state', $state);

        self::assertTrue($this->manager($frontend)->save($state));
    }

    #[Test]
    public function saveReportsFailureWhenCacheBackendIsUnavailable(): void
    {
        self::assertFalse(
            $this->managerWithoutCache()->save(new NotificationState(null, 0, true)),
        );
    }

    private function manager(FrontendInterface $frontend): NotificationStateCacheManager
    {
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')->willReturn($frontend);

        return new NotificationStateCacheManager($cacheManager);
    }

    private function managerWithoutCache(): NotificationStateCacheManager
    {
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')
            ->willThrowException(new NoSuchCacheException('Cache not found', 1234567890));

        return new NotificationStateCacheManager($cacheManager);
    }
}
