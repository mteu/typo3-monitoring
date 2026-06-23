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

use mteu\Monitoring\Configuration\Provider\SchedulerProviderConfiguration;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

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

    private const string SCHEDULER_TABLENAME = 'tx_scheduler_task';

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        private SchedulerProviderConfiguration $configuration,
        private SchedulerTaskRepository $taskGateway,
        private SchedulerHeartbeat $heartbeat,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private Router $router,
        private BackendUriBuilder $backendUriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
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

    public function isEnabled(): bool
    {
        return $this->configuration->isEnabled();
    }

    public function isActive(): bool
    {
        return ExtensionManagementUtility::isLoaded('scheduler');
    }

    public function execute(): Result
    {
        $result = new MonitoringResult($this->getName(), Status::Healthy);

        $result->addSubResult($this->checkHeartbeat());
        $result->addSubResult($this->checkOverdueTasks());
        $result->addSubResult($this->checkFailedTasks());

        if (!$result->isHealthy()) {
            $result->setReason($this->translate('provider.scheduler.unhealthy'));
        }

        return $result;
    }

    private function checkHeartbeat(): Result
    {
        if (!$this->configuration->isHeartbeatCheckEnabled()) {
            return new MonitoringResult('Scheduler', Status::Healthy, $this->translate('provider.scheduler.heartbeat.disabled'));
        }

        if ($this->taskGateway->hasRunningTask()) {
            return new MonitoringResult('Scheduler', Status::Healthy, $this->translate('provider.scheduler.heartbeat.running'));
        }

        $lastRun = $this->heartbeat->getLastRunEndTime();

        if ($lastRun === null) {
            return new MonitoringResult(
                'Scheduler',
                Status::Unhealthy,
                $this->translate('provider.scheduler.heartbeat.noRun'),
            );
        }

        $age = $this->clock->now()->getTimestamp() - $lastRun;

        if ($age > $this->configuration->heartbeatThreshold) {
            return new MonitoringResult(
                'Scheduler',
                Status::Unhealthy,
                $this->translate(
                    'provider.scheduler.heartbeat.stale',
                    $this->formatAge($age),
                    date(\DateTimeInterface::ATOM, $lastRun),
                    $this->configuration->heartbeatThreshold,
                ),
            );
        }

        return new MonitoringResult(
            'Scheduler',
            Status::Healthy,
            $this->translate('provider.scheduler.heartbeat.ok', date(\DateTimeInterface::ATOM, $lastRun)),
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
            return new MonitoringResult('Failed Tasks', Status::Healthy, $this->translate('provider.scheduler.failed.none'));
        }

        return new MonitoringResult(
            'Failed Tasks',
            Status::Unhealthy,
            $this->translate(
                $count > 1 ? 'provider.scheduler.failed.plural' : 'provider.scheduler.failed.singular',
                $count,
                $this->renderSampleList($this->safeFailedSample()),
            ),
        );
    }

    private function checkOverdueTasks(): Result
    {
        if (!$this->configuration->isOverdueCheckEnabled()) {
            return new MonitoringResult('Overdue Tasks', Status::Healthy, $this->translate('provider.scheduler.overdue.disabled'));
        }

        $overdueBefore = $this->clock->now()->getTimestamp() - $this->configuration->overdueThreshold;

        try {
            $count = $this->taskGateway->countOverdueTasks($overdueBefore);
        } catch (\Throwable $exception) {
            return $this->reportQueryFailure('Overdue Tasks', $exception);
        }

        if ($count === 0) {
            return new MonitoringResult('Overdue Tasks', Status::Healthy, $this->translate('provider.scheduler.overdue.none'));
        }

        return new MonitoringResult(
            'Overdue Tasks',
            Status::Unhealthy,
            $this->translate(
                $count > 1 ? 'provider.scheduler.overdue.plural' : 'provider.scheduler.overdue.singular',
                $count,
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
            $labels[] = $this->renderEditLink(
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
            Status::Unhealthy,
            $this->translate('provider.scheduler.queryFailure', $exception->getMessage()),
        );
    }

    /**
     * Resolves an operator-facing reason from the backend language file,
     * filling sprintf placeholders with the given arguments.
     */
    private function translate(string $key, int|float|string ...$args): string
    {
        $label = $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':' . $key);

        return $args === [] ? $label : sprintf($label, ...$args);
    }

    private function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }

    private function formatAge(int $seconds): string
    {
        $now = $this->clock->now();
        $past = $now->sub(new \DateInterval('PT' . max(0, $seconds) . 'S'));

        return DateFormatter::formatDateInterval(
            $past->diff($now),
            $this->getLanguageService()->sL(
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.minutesHoursDaysYears',
            ),
        );
    }

    public function renderEditLink(int $uid, string $label): ?string
    {
        $uri = $this->buildEditLink($uid);

        if ($uri === null) {
            return null;
        }

        return sprintf(
            '<a href="%s">%s</a>',
            $uri,
            $label,
        );
    }

    public function buildEditLink(int $uid): ?string
    {
        try {
            if (!$this->router->hasRoute('scheduler_manage')) {
                // v14+: scheduler tasks are real DB records edited via record_edit.
                return (string)$this->backendUriBuilder->buildUriFromRoute('record_edit', [
                    'edit' => [self::SCHEDULER_TABLENAME => [$uid => 'edit']],
                ]);
            }

            // v13: dedicated scheduler module route.
            return (string)$this->backendUriBuilder->buildUriFromRoute('scheduler_manage', [
                'action' => 'edit',
                'uid' => $uid,
            ]);
        } catch (RouteNotFoundException $exception) {
            $this->logger->warning('Could not build scheduler task edit link.', [
                'uid' => $uid,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
