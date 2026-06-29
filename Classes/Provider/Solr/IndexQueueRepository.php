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
 * Data access for the EXT:solr index queue. The count drives health; the sample
 * is best-effort reason decoration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface IndexQueueRepository
{
    /**
     * Number of index queue items that recorded an indexing error.
     */
    public function countIndexingErrors(): int;

    /**
     * @param positive-int $limit
     * @return list<string>
     */
    public function getIndexingErrorSample(int $limit): array;
}
