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

namespace mteu\Monitoring\Provider;

/**
 * MonitoringProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface CacheableMonitoringProvider extends MonitoringProvider
{
    /**
     * Make sure to use a valid cache identifier. Also take care to choose a cache key that is accurate enough to
     * distinguish different versions of the rendered content while being generic enough to stay efficient.
     *
     * @see: https://docs.typo3.org/m/typo3/reference-typoscript/main/en-us/Functions/Cache.html#key
     */
    public function getCacheKey(): string;
    public function getCacheLifetime(): int;
}
