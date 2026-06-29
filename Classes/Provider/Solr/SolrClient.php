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

namespace mteu\Monitoring\Provider\Solr;

/**
 * Probes the reachability of a Solr endpoint. Implementations must never throw exceptions:
 * a failed probe (connection refused, timeout, error status) is reported as `false`.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface SolrClient
{
    /**
     * Whether the given Solr endpoint answers with a success status.
     */
    public function isReachable(string $url): bool;
}
