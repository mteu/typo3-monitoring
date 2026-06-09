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

use mteu\Monitoring\Reporter\NotificationState;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;

/**
 * NotificationStateStore.
 *
 * Persists the dedup state in a dedicated cache (`typo3_monitoring_notification`)
 * which is intentionally NOT in the "system" cache group, so routine
 * "flush system caches" / provider-cache-flush actions cannot make the
 * notifier forget that it already alerted.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class NotificationStateCacheManager
{
    private const string CACHE_IDENTIFIER = 'typo3_monitoring_notification';
    private const string STATE_KEY = 'notification_state';

    public function __construct(
        private CacheManager $cacheManager,
    ) {}

    public function load(): ?NotificationState
    {
        try {
            $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

            if (!$cache->has(self::STATE_KEY)) {
                return null;
            }

            $state = $cache->get(self::STATE_KEY);

            return $state instanceof NotificationState ? $state : null;
        } catch (NoSuchCacheException) {
            return null;
        }
    }

    public function save(NotificationState $state): bool
    {
        try {
            $this->cacheManager
                ->getCache(self::CACHE_IDENTIFIER)
                ->set(self::STATE_KEY, $state);

            return true;
        } catch (NoSuchCacheException) {
            return false;
        }
    }
}
