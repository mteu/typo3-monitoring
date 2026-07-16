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
 * NotificationDecision.
 *
 * Outcome of the dispatcher's dedup state machine for a single run.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
enum NotificationDecision
{
    /** Nothing to send (healthy with no prior outage, or throttled within the reminder interval). */
    case None;

    /** Unhealthy and either a new outage or a changed failure set: notify now. */
    case Unhealthy;

    /** Same failure set, a reminder interval elapsed: send a reminder. */
    case Reminder;

    /** Status returned to healthy after an outage: send a one-off recovery notice. */
    case Recovered;

    public function shouldDispatch(): bool
    {
        return $this !== self::None;
    }
}
