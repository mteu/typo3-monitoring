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

namespace mteu\Monitoring\Extension;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Extension state backed by ExtensionManagementUtility.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 *
 * @codeCoverageIgnore
 */
#[AsAlias(ExtensionState::class)]
final readonly class Typo3ExtensionState implements ExtensionState
{
    public function isLoaded(string $extensionKey): bool
    {
        return ExtensionManagementUtility::isLoaded($extensionKey);
    }
}
