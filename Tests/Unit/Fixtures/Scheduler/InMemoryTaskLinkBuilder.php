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

namespace mteu\Monitoring\Tests\Unit\Fixtures\Scheduler;

use mteu\Monitoring\Provider\Scheduler\SchedulerTaskLinkBuilder;

/**
 * InMemoryTaskLinkBuilder.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class InMemoryTaskLinkBuilder implements SchedulerTaskLinkBuilder
{
    public function __construct(
        private ?string $baseUri = null,
    ) {}

    public function buildEditLink(int $uid): ?string
    {
        if ($this->baseUri === null) {
            return null;
        }

        return $this->baseUri . (str_contains($this->baseUri, '?') ? '&' : '?') . 'uid=' . $uid;
    }

    public function renderEditLink(int $uid, string $label): ?string
    {
        $href = $this->buildEditLink($uid);

        if ($href === null) {
            return null;
        }

        return sprintf(
            '<a href="%s">%s</a>',
            $this->buildEditLink($uid),
            $label,
        );
    }
}
