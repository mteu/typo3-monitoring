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

namespace mteu\Monitoring\Crypto;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService as ExtbaseHashService;

/**
 * HashServiceFactory.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 *
 * @deprecated Will be removed once support for v12 is dropped
 * @internal
 */
final readonly class HashServiceFactory
{
    /** @phpstan-ignore return.internalClass  */
    public static function create(): HashService|ExtbaseHashService
    {
        $typo3 = new Typo3Version();

        if ($typo3->getMajorVersion() >= 13) {
            return new HashService();
        }

        /** @phpstan-ignore classConstant.internalClass */
        return GeneralUtility::makeInstance(ExtbaseHashService::class);
    }
}
