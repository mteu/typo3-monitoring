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

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Context\Context;

/**
 * PSR-20 clock backed by the TYPO3 Context date aspect, so domain code never
 * calls time() or new \DateTime() directly and still honours TYPO3's simulated
 * time (workspaces/preview).
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 *
 * @codeCoverageIgnore
 */
#[AsAlias(ClockInterface::class)]
final readonly class Typo3Clock implements ClockInterface
{
    public function __construct(
        private Context $context,
    ) {}

    public function now(): \DateTimeImmutable
    {
        $timestamp = $this->context->getPropertyFromAspect('date', 'timestamp');

        return (new \DateTimeImmutable())->setTimestamp(is_int($timestamp) ? $timestamp : time());
    }
}
