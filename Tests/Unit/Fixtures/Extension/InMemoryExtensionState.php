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

namespace mteu\Monitoring\Tests\Unit\Fixtures\Extension;

use mteu\Monitoring\Extension\ExtensionState;

/**
 * InMemoryExtensionState.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class InMemoryExtensionState implements ExtensionState
{
    /**
     * @param array<string, bool> $loaded
     */
    public function __construct(
        private array $loaded = [],
    ) {}

    public function isLoaded(string $extensionKey): bool
    {
        return $this->loaded[$extensionKey] ?? false;
    }
}
