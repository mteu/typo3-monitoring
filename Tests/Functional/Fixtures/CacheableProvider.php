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
 * CacheableProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class CacheableProvider implements CacheableMonitoringProvider
{
    private int $executionCount = 0;

    public function __construct(
        /** @var non-empty-string $identifier */
        private string $identifier = 'test-cacheable-provider',
    ) {}

    public function getName(): string
    {
        return $this->identifier;
    }

    public function getDescription(): string
    {
        return 'Test cacheable provider for functional tests';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        $this->executionCount++;
        return new MonitoringResult($this->identifier, true);
    }

    public function getCacheKey(): string
    {
        return $this->identifier . '_cache_key';
    }

    public function getCacheLifetime(): int
    {
        return 900; // 15 minutes
    }

    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }
}
