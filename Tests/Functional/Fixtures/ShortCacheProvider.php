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

namespace mteu\Monitoring\Tests\Functional\Fixtures;

use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;

/**
 * ShortCacheProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ShortCacheProvider implements CacheableMonitoringProvider
{
    private int $executionCount = 0;

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return 'test-short-cache-provider';
    }

    public function getDescription(): string
    {
        return 'Test provider with short cache lifetime';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        $this->executionCount++;
        return new MonitoringResult('test-short-cache-provider', true);
    }

    public function getCacheKey(): string
    {
        return 'short_cache_key';
    }

    public function getCacheLifetime(): int
    {
        return 1; // 1 second
    }

    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }
}
