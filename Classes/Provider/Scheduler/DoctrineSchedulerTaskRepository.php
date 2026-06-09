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

namespace mteu\Monitoring\Provider\Scheduler;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Doctrine-backed scheduler task gateway.
 *
 * Restrictions are set explicitly and the default restriction container is
 * cleared, so the SQL behaves identically on TYPO3 v13 (no TCA on the table)
 * and v14 (TCA present, default restrictions would otherwise apply).
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(SchedulerTaskRepository::class)]
final readonly class DoctrineSchedulerTaskRepository implements SchedulerTaskRepository
{
    private const string TABLE = 'tx_scheduler_task';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function countFailedTasks(): int
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->neq(
                'lastexecution_failure',
                $queryBuilder->createNamedParameter(''),
            ),
        );

        return $this->countFrom($queryBuilder);
    }

    public function countOverdueTasks(int $overdueBefore): int
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $this->applyOverdueConstraint($queryBuilder, $overdueBefore);

        return $this->countFrom($queryBuilder);
    }

    public function hasRunningTask(): bool
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->isNotNull('serialized_executions'),
            $queryBuilder->expr()->neq(
                'serialized_executions',
                $queryBuilder->createNamedParameter(''),
            ),
        );

        return $this->countFrom($queryBuilder) > 0;
    }

    public function getFailedTaskSample(int $limit): array
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $queryBuilder
            ->select('uid', 'description', 'tasktype')
            ->andWhere(
                $queryBuilder->expr()->neq(
                    'lastexecution_failure',
                    $queryBuilder->createNamedParameter(''),
                ),
            )
            ->setMaxResults($limit)
        ;

        return $this->mapTasks($queryBuilder);
    }

    public function getOverdueTaskSample(int $overdueBefore, int $limit): array
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $queryBuilder->select('uid', 'description', 'tasktype');
        $this->applyOverdueConstraint($queryBuilder, $overdueBefore);
        $queryBuilder->setMaxResults($limit);

        return $this->mapTasks($queryBuilder);
    }

    /**
     * Restricts to enabled, non-deleted tasks. Default restrictions are
     * removed so the result is identical across TYPO3 versions.
     */
    private function createRestrictedQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
        ;

        return $queryBuilder;
    }

    private function applyOverdueConstraint(QueryBuilder $queryBuilder, int $overdueBefore): void
    {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->gt('nextexecution', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            $queryBuilder->expr()->lt('nextexecution', $queryBuilder->createNamedParameter($overdueBefore, ParameterType::INTEGER)),
        );
    }

    private function countFrom(QueryBuilder $queryBuilder): int
    {
        $result = $queryBuilder->count('uid')->executeQuery()->fetchOne();

        return is_numeric($result) ? (int)$result : 0;
    }

    /**
     * @return list<SchedulerTask>
     */
    private function mapTasks(QueryBuilder $queryBuilder): array
    {
        $tasks = [];

        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $uid = $row['uid'] ?? null;

            if (!is_scalar($uid)) {
                continue;
            }

            $description = is_string($row['description'] ?? null) ? $row['description'] : '';
            $taskType = is_string($row['tasktype'] ?? null) ? $row['tasktype'] : '';

            $tasks[] = new SchedulerTask((int)$uid, $description !== '' ? $description : $taskType);
        }

        return $tasks;
    }
}
