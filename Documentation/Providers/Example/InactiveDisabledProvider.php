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

namespace ExampleProvider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;

/**
 * InactiveDisabledProvider.
 *
 * @author Martin Adler <martin.adler@init.de>
 * @license GPL-2.0-or-later
 */
final class InactiveDisabledProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'InactiveDisabledProvider';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return false;
    }

    public function execute(): Result
    {
        return new MonitoringResult(
            $this->getName(),
            Status::Healthy,
        );
    }
}
