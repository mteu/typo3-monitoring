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

namespace mteu\Monitoring\Clock;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Context\Context;

/**
 * Time source backed by the TYPO3 Context date aspect.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(Clock::class)]
final readonly class Typo3Clock implements Clock
{
    public function __construct(
        private Context $context,
    ) {}

    public function now(): int
    {
        $timestamp = $this->context->getPropertyFromAspect('date', 'timestamp');

        return is_int($timestamp) ? $timestamp : time();
    }
}
