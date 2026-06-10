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

namespace mteu\Monitoring\Backend;

/**
 * NavigationItem.
 *
 * @author Martin Adler <martin.adler@init.de>
 * @license GPL-2.0-or-later
 */
final readonly class NavigationItem
{
    public function __construct(
        public string $title,
        public string $href,
        public bool $isActive = false,
    ) {}
}
