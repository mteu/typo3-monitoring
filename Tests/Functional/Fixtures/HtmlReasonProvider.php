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
 * HtmlReasonProvider.
 *
 * Emits HTML-laden reasons at both the top level and in a sub-result. The
 * shipped SchedulerProvider legitimately puts HTML (edit links, `<ul>` samples)
 * into its reasons for the backend module, so this fixture asserts the
 * invariant that such markup never reaches the public JSON endpoint, where only
 * the status is summarized.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class HtmlReasonProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'html-reason-provider';
    }

    public function getDescription(): string
    {
        return 'Emits HTML reasons to assert they never reach the JSON endpoint';
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
        $result = new MonitoringResult(
            $this->getName(),
            Status::Unhealthy,
            '<script>alert(1)</script> top-level reason',
        );

        $result->addSubResult(
            new MonitoringResult(
                'sub-check',
                Status::Unhealthy,
                '<a href="https://evil.example/">click</a> sub reason',
            ),
        );

        return $result;
    }
}
