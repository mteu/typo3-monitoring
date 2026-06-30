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

use mteu\Monitoring\Result\Result;

/**
 * ReportContext.
 *
 * Immutable payload handed to every {@see Reporter} so it can render an accurate message
 * ("new failure" / "still failing" / "recovered") without re-deriving dispatcher state.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 *
 * @codeCoverageIgnore
 */
final readonly class ReportContext
{
    /**
     * @param array<non-empty-string, Result> $results full active-provider result set, keyed by provider name
     * @param list<string> $unhealthyProviderNames human-readable names of failing providers (sorted)
     */
    public function __construct(
        public NotificationDecision $decision,
        public array $results,
        public array $unhealthyProviderNames,
        public ?string $fingerprint,
    ) {}
}
