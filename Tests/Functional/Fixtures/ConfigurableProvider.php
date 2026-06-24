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
use mteu\Monitoring\Result\Status;

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
    private ?Status $status = null;

    public function __construct(
        /** @var non-empty-string $identifier */
        private readonly string $identifier,
        private bool $healthy = true,
        private bool $active = true,
    ) {}

    public function setHealthy(bool $healthy): void
    {
        $this->healthy = $healthy;
        $this->status = null;
    }

    /**
     * Pins an explicit status, overriding the healthy/unhealthy toggle. Lets a
     * test emit a `degraded` result to exercise the notify-from severity floor.
     */
    public function setStatus(Status $status): void
    {
        $this->status = $status;
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
        $status = $this->status ?? ($this->healthy ? Status::Healthy : Status::Unhealthy);

        return new MonitoringResult($this->identifier, $status);
    }
}
