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
 * ConfigurableProvider.
 *
 * Test provider whose health/active state can be toggled at runtime.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ConfigurableProvider implements MonitoringProvider
{
    public function __construct(
        /** @var non-empty-string $identifier */
        private readonly string $identifier,
        private bool $healthy = true,
        private bool $active = true,
    ) {}

    public function setHealthy(bool $healthy): void
    {
        $this->healthy = $healthy;
    }

    public function getName(): string
    {
        return $this->identifier;
    }

    public function getDescription(): string
    {
        return 'Configurable provider for reporter dispatcher tests';
    }

    public function isEnabled(): bool
    {
        return $this->active;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function execute(): Result
    {
        return new MonitoringResult($this->identifier, $this->healthy);
    }
}
