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

namespace mteu\Monitoring\Reporter;

use mteu\Monitoring\Cache\NotificationStateCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Result;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Context\Context;

/**
 * ReportDispatcher.
 *
 * Shared "brain" used by both the CLI command and the (optional) scheduler
 * task: evaluates active providers, runs the dedup state machine, and fans
 * out to active reporters according to each reporter's threshold.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Autoconfigure(public: true)]
final readonly class ReportDispatcher
{
    public function __construct(
        /** @var iterable<MonitoringProvider> $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        /** @var iterable<Reporter> $reporters */
        #[AutowireIterator(tag: 'monitoring.reporter', defaultPriorityMethod: 'getPriority')]
        private iterable $reporters,
        private MonitoringExecutionHandler $executionHandler,
        private NotificationStateCacheManager $stateCacheManager,
        private MonitoringConfiguration $configuration,
        private Context $context,
        private LoggerInterface $logger,
    ) {}

    public function dispatch(): NotificationDecision
    {
        $results = $this->collectResults();

        $unhealthyNames = [];
        foreach ($results as $name => $result) {
            if (!$result->isHealthy()) {
                $unhealthyNames[] = $name;
            }
        }
        sort($unhealthyNames);

        $healthyNow = $unhealthyNames === [];
        $fingerprint = $healthyNow ? null : sha1(implode("\n", $unhealthyNames));

        $now = $this->now();

        $state = $this->stateCacheManager->load();

        $dispatcherConfiguration = $this->configuration->reporterDispatcherConfiguration;

        $decision = $this->decide(
            $healthyNow,
            $fingerprint,
            $state,
            $now,
            $dispatcherConfiguration->reminderInterval,
            $dispatcherConfiguration->notifyOnRecovery,
            $dispatcherConfiguration->failOpenOnMissingState,
        );

        $reportContext = new ReportContext($decision, $results, $unhealthyNames, $fingerprint);

        $anySent = $decision->shouldDispatch() && $this->fanOut($reportContext);

        $this->persist($healthyNow, $fingerprint, $decision, $anySent, $state, $now);

        return $decision;
    }

    private function decide(
        bool $healthyNow,
        ?string $fingerprint,
        ?NotificationState $state,
        int $now,
        int $reminderInterval,
        bool $notifyOnRecovery,
        bool $failOpenOnMissingState,
    ): NotificationDecision {
        if ($healthyNow) {
            if ($state !== null && !$state->wasHealthy && $notifyOnRecovery) {
                return NotificationDecision::Recovered;
            }

            return NotificationDecision::None;
        }

        if ($state === null) {
            return $failOpenOnMissingState ? NotificationDecision::Unhealthy : NotificationDecision::None;
        }

        if ($state->fingerprint !== $fingerprint) {
            return NotificationDecision::Unhealthy;
        }

        if ($now - $state->lastNotifiedAt >= $reminderInterval) {
            return NotificationDecision::Reminder;
        }

        return NotificationDecision::None;
    }

    private function persist(
        bool $healthyNow,
        ?string $fingerprint,
        NotificationDecision $decision,
        bool $anySent,
        ?NotificationState $state,
        int $now,
    ): void {
        $previousLastNotified = $state !== null ? $state->lastNotifiedAt : 0;

        if ($healthyNow) {
            $lastNotifiedAt = $decision === NotificationDecision::Recovered ? $now : $previousLastNotified;
            $this->stateCacheManager->save(new NotificationState(null, $lastNotifiedAt, true));

            return;
        }

        // Only advance the reminder clock when a notification was actually
        // delivered, so a failed send is retried on the next run.
        $lastNotifiedAt = $anySent ? $now : $previousLastNotified;
        $this->stateCacheManager->save(new NotificationState($fingerprint, $lastNotifiedAt, false));
    }

    private function fanOut(ReportContext $context): bool
    {
        $anySent = false;

        foreach ($this->reporters as $reporter) {
            if (!$reporter->isActive()) {
                continue;
            }

            if (!$this->reporterAccepts($reporter, $context->decision)) {
                continue;
            }

            try {
                if ($reporter->report($context)) {
                    $anySent = true;
                } else {
                    $this->logger->warning('Reporter did not deliver the monitoring report', [
                        'reporter' => $reporter->getName(),
                        'decision' => $context->decision->name,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Reporter threw while delivering the monitoring report', [
                    'reporter' => $reporter->getName(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $anySent;
    }

    private function reporterAccepts(Reporter $reporter, NotificationDecision $decision): bool
    {
        return match ($reporter->getThreshold()) {
            ReportThreshold::Always => true,
            ReportThreshold::Unhealthy => $decision === NotificationDecision::Unhealthy
                || $decision === NotificationDecision::Reminder,
            ReportThreshold::StateChange => $decision === NotificationDecision::Unhealthy
                || $decision === NotificationDecision::Recovered,
        };
    }

    /**
     * Keyed by the provider's name (matching the middleware's status map) so
     * two instances of the same provider class do not collide.
     *
     * @return array<non-empty-string, Result>
     */
    private function collectResults(): array
    {
        $results = [];

        foreach ($this->monitoringProviders as $provider) {
            if ($provider->isActive()) {
                $results[$provider->getName()] = $this->executionHandler->executeProvider($provider);
            }
        }

        return $results;
    }

    private function now(): int
    {
        $timestamp = $this->context->getPropertyFromAspect('date', 'timestamp');

        return is_int($timestamp) ? $timestamp : time();
    }
}
