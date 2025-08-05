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

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;

/**
 * NonCacheableProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class NonCacheableProvider implements MonitoringProvider
{
    private int $executionCount = 0;

    public function getName(): string
    {
        return 'test-non-cacheable-provider';
    }

    public function getDescription(): string
    {
        return 'Test non-cacheable provider for functional tests';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        $this->executionCount++;
        return new MonitoringResult('test-non-cacheable-provider', true);
    }

    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }
}
