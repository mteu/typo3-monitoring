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

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Doctrine-backed access to the EXT:solr index queue (`tx_solr_indexqueue_item`).
 *
 * An item carries a non-empty `errors` column when its last indexing attempt
 * failed. Default restrictions are cleared so the query behaves identically
 * regardless of the TCA EXT:solr ships for the table.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(IndexQueueRepository::class)]
final readonly class DoctrineIndexQueueRepository implements IndexQueueRepository
{
    private const string TABLE = 'tx_solr_indexqueue_item';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function countIndexingErrors(): int
    {
        $queryBuilder = $this->createErrorQueryBuilder();

        $result = $queryBuilder->count('uid')->executeQuery()->fetchOne();

        return is_numeric($result) ? (int)$result : 0;
    }

    public function getIndexingErrorSample(int $limit): array
    {
        $queryBuilder = $this->createErrorQueryBuilder();
        $queryBuilder
            ->select('item_type', 'item_uid')
            ->setMaxResults($limit)
        ;

        $labels = [];

        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $type = is_scalar($row['item_type'] ?? null) ? (string)$row['item_type'] : '';
            $uid = is_scalar($row['item_uid'] ?? null) ? (string)$row['item_uid'] : '';

            $label = trim($type . ':' . $uid, ':');

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private function createErrorQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->neq(
                    'errors',
                    $queryBuilder->createNamedParameter(''),
                ),
            )
        ;

        return $queryBuilder;
    }
}
