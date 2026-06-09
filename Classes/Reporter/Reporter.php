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

namespace mteu\Monitoring\Reporter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reporter.
 *
 * A push channel that delivers monitoring status (email, Slack, Teams, …).
 * Register a custom reporter by implementing this interface — the
 * {@see \Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag}
 * makes it discoverable automatically.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AutoconfigureTag(name: 'monitoring.reporter')]
interface Reporter
{
    public function isActive(): bool;

    /**
     * Which dispatch decisions this reporter wants to be notified about.
     */
    public function getThreshold(): ReportThreshold;

    /**
     * Delivers the report. Returns true only if the notification was sent
     * successfully; returning false makes the dispatcher retry on the next run.
     */
    public function report(ReportContext $context): bool;

    /**
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Static ::getPriority() used in the DI autowiring for sorting.
     */
    public static function getPriority(): int;
}
