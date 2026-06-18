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

/**
 * DispatchResult.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class DispatchResult
{
    public function __construct(
        public NotificationDecision $decision,
        public bool $delivered,
    ) {}

    /**
     * True when a notification was due but no active reporter delivered it.
     */
    public function isUndelivered(): bool
    {
        return $this->decision->shouldDispatch() && !$this->delivered;
    }
}
