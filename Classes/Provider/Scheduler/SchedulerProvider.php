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

use mteu\Monitoring\Clock\Clock;
use mteu\Monitoring\Configuration\Provider\SchedulerProviderConfiguration;
use mteu\Monitoring\Extension\ExtensionState;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use Psr\Log\LoggerInterface;

/**
 * SchedulerMonitoringProvider.
 *
 * Monitors the health of the TYPO3 scheduler with three sub-results:
 *
 *   1. heartbeat: Is cron still invoking the scheduler at all?
 *   2. failed tasks: Did any enabled task record an execution failure?
 *   3. overdue tasks: Is a scheduled task long past its next execution time?
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SchedulerProvider implements MonitoringProvider
{
    /**
     * Number of failing/overdue task descriptions included in a reason to keep
     * the output readable on installations with many tasks.
     */
    private const int SAMPLE_SIZE = 5;

    public function __construct(
        private SchedulerProviderConfiguration $configuration,
        private SchedulerTaskRepository $taskGateway,
        private SchedulerHeartbeat $heartbeat,
        private Clock $clock,
        private SchedulerTaskLinkBuilder $linkBuilder,
        private ExtensionState $extensionState,
        private LoggerInterface $logger,
    ) {}

    public function getName(): string
    {
        return 'Scheduler';
    }

    public function getDescription(): string
    {
        return 'Monitors the TYPO3 scheduler: whether cron still triggers it (heartbeat), '
            . 'whether any enabled task recorded an execution failure, and whether any '
            . 'scheduled task is overdue (manually-triggered tasks are excluded). '
            . 'Inactive when the scheduler extension is not installed.';
    }

    public function isActive(): bool
    {
        if (!$this->configuration->isEnabled()) {
            return false;
        }

        return $this->extensionState->isLoaded('scheduler');
    }

    public function execute(): Result
    {
        $result = new MonitoringResult($this->getName(), true);

        $result->addSubResult($this->checkHeartbeat());
        $result->addSubResult($this->checkOverdueTasks());
        $result->addSubResult($this->checkFailedTasks());

        if (!$result->isHealthy()) {
            $result->setReason('One or more scheduler health checks failed. See sub-results for details.');
        }

        return $result;
    }

    private function checkHeartbeat(): Result
    {
        if (!$this->configuration->isHeartbeatCheckEnabled()) {
            return new MonitoringResult('Scheduler Execution', true, 'Scheduler Execution check is disabled. Set threshold in Settings to active.');
        }

        $lastRun = $this->heartbeat->getLastRunEndTime();

        if ($lastRun === null) {
            return new MonitoringResult(
                'Scheduler Execution',
                false,
                'No scheduler run has been recorded yet. Verify the cron job is set up.',
            );
        }

        $age = $this->clock->now() - $lastRun;

        if ($age > $this->configuration->heartbeatThreshold) {
            return new MonitoringResult(
                'Scheduler Execution',
                false,
                sprintf(
                    'Scheduler last ran %s ago (at %s), exceeding the threshold of %d seconds.',
                    $this->formatAge($age),
                    date(\DateTimeInterface::ATOM, $lastRun),
                    $this->configuration->heartbeatThreshold,
                ),
            );
        }

        return new MonitoringResult(
            'Scheduler',
            true,
            sprintf('Scheduler last ran at %s.', date(\DateTimeInterface::ATOM, $lastRun)),
        );
    }

    private function checkFailedTasks(): Result
    {
        try {
            $count = $this->taskGateway->countFailedTasks();
        } catch (\Throwable $exception) {
            return $this->reportQueryFailure('Failed Tasks', $exception);
        }

        if ($count === 0) {
            return new MonitoringResult('Failed Tasks', true, 'No task reported an execution failure.');
        }

        return new MonitoringResult(
            'Failed Tasks',
            false,
            sprintf(
                '%d task%s reported an execution failure%s',
                $count,
                $count > 1 ? 's' : '',
                $this->renderSampleList($this->safeFailedSample()),
            ),
        );
    }

    private function checkOverdueTasks(): Result
    {
        if (!$this->configuration->isOverdueCheckEnabled()) {
            return new MonitoringResult('Overdue Tasks', true, 'Overdue tasks is check disabled. Set threshold in Settings to active.');
        }

        $overdueBefore = $this->clock->now() - $this->configuration->overdueThreshold;

        try {
            $count = $this->taskGateway->countOverdueTasks($overdueBefore);
        } catch (\Throwable $exception) {
            return $this->reportQueryFailure('Overdue Tasks', $exception);
        }

        if ($count === 0) {
            return new MonitoringResult('Overdue Tasks', true, 'No task is overdue.');
        }

        return new MonitoringResult(
            'Overdue Tasks',
            false,
            sprintf(
                '%d task%s are overdue by more than %d seconds%s',
                $count,
                $count > 1 ? 's' : '',
                $this->configuration->overdueThreshold,
                $this->renderSampleList($this->safeOverdueSample($overdueBefore)),
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function safeFailedSample(): array
    {
        try {
            return $this->renderSample($this->taskGateway->getFailedTaskSample(self::SAMPLE_SIZE));
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not fetch failed scheduler task sample.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function safeOverdueSample(int $overdueBefore): array
    {
        try {
            return $this->renderSample($this->taskGateway->getOverdueTaskSample($overdueBefore, self::SAMPLE_SIZE));
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not fetch overdue scheduler task sample.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<SchedulerTask> $tasks
     * @return list<string>
     */
    private function renderSample(array $tasks): array
    {
        $labels = [];

        foreach ($tasks as $task) {
            $labels[] = $this->linkBuilder->renderEditLink(
                $task->uid,
                $task->getLabel()
            ) ?? $task->getLabel();
        }

        return $labels;
    }

    /**
     * @param list<string> $sample
     */
    private function renderSampleList(array $sample): string
    {
        if ($sample === []) {
            return '.';
        }

        $items = array_map(
            static fn(string $item): string => '<li>' . $item . '</li>',
            $sample,
        );

        return ':<ul>' . implode('', $items) . '</ul>';
    }

    private function reportQueryFailure(string $name, \Throwable $exception): Result
    {
        $this->logger->error('Scheduler monitoring query failed.', [
            'check' => $name,
            'exception' => $exception->getMessage(),
        ]);

        return new MonitoringResult(
            $name,
            false,
            'Could not query scheduler tasks: ' . $exception->getMessage(),
        );
    }

    private function formatAge(int $seconds): string
    {
        $minutes = (int)($seconds / 60);
        $hours = (int)($minutes / 60);
        $days = (int)($hours / 24);

        $remMinutes = $minutes % 60;
        $remHours = $hours % 24;

        return match (true) {
            $days > 0 => $days . ' ' . ($days === 1 ? 'day' : 'days')
                . ($remHours > 0 ? ' and ' . $remHours . ' ' . ($remHours === 1 ? 'hour' : 'hours') : ''),
            $hours > 0 => $hours . ' ' . ($hours === 1 ? 'hour' : 'hours')
                . ($remMinutes > 0 ? ' and ' . $remMinutes . ' ' . ($remMinutes === 1 ? 'minute' : 'minutes') : ''),
            $minutes > 0 => $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes'),
            default => $seconds . ' ' . ($seconds === 1 ? 'second' : 'seconds'),
        };
    }
}
