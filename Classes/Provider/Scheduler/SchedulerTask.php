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

namespace mteu\Monitoring\Provider\Scheduler;

/**
 * A scheduler task identified for a health report.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SchedulerTask
{
    /**
     * @param positive-int $uid
     */
    public function __construct(
        public int $uid,
        public string $description,
    ) {
        if ($uid < 1) {
            throw new \InvalidArgumentException(
                sprintf('Scheduler task uid must be a positive integer, got %d.', $uid),
                1717400000,
            );
        }
    }

    public function label(): string
    {
        $description = $this->description !== '' ? $this->description : 'no description';

        return sprintf('#%d (%s)', $this->uid, $description);
    }
}
