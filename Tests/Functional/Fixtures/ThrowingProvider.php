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
use mteu\Monitoring\Result\Result;

/**
 * ThrowingProvider.
 *
 * Always blows up on execution so tests can assert that provider execution is
 * isolated: a single misbehaving provider must be recorded as unhealthy rather
 * than 500ing the health endpoint or aborting the report dispatcher.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ThrowingProvider implements MonitoringProvider
{
    public function __construct(
        /** @var non-empty-string $identifier */
        private readonly string $identifier = 'throwing-provider',
    ) {}

    public function getName(): string
    {
        return $this->identifier;
    }

    public function getDescription(): string
    {
        return 'Provider that always throws for exception-isolation tests';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        throw new \RuntimeException('Provider exploded during execution', 1751407200);
    }
}
