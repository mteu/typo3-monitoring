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

/**
 * MockTimeProvider for testable time-dependent logic.
 *
 * Allows tests to control time progression without sleep().
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class MockTimeProvider implements TimeProvider
{
    private \DateTimeImmutable $currentTime;

    public function __construct(?\DateTimeImmutable $initialTime = null)
    {
        $this->currentTime = $initialTime ?? new \DateTimeImmutable();
    }

    public function now(): \DateTimeImmutable
    {
        return $this->currentTime;
    }

    /**
     * Advances time by the specified number of seconds.
     */
    public function advanceBy(int $seconds): void
    {
        $this->currentTime = $this->currentTime->modify("+{$seconds} seconds");
    }

    /**
     * Sets the current time to a specific moment.
     */
    public function setTime(\DateTimeImmutable $time): void
    {
        $this->currentTime = $time;
    }

    /**
     * Resets time to the initial moment.
     */
    public function reset(?\DateTimeImmutable $time = null): void
    {
        $this->currentTime = $time ?? new \DateTimeImmutable();
    }
}
