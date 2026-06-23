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

namespace mteu\Monitoring\Command;

use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Handler\ProviderExecutionOutcome;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Status;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * MonitoringCommand.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsCommand(name: 'monitoring:run')]
final class MonitoringCommand extends Command
{
    public function __construct(
        /** @var iterable<MonitoringProvider> $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private readonly iterable $monitoringProviders,
        private readonly MonitoringExecutionHandler $executionHandler,
    ) {
        parent::__construct(name: 'monitoring:run');
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('This command runs monitoring.');
        $this->addOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Bypass the monitoring cache and force a fresh execution of every provider.',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $useCache = $input->getOption('no-cache') === false;
        $outcomes = $this->executeActiveProviders($useCache);

        if (count($outcomes) === 0) {
            $output->writeln('No active providers available. Skipping.');

            return Command::INVALID;
        }

        $output->writeln('Checking Monitoring status');
        foreach ($outcomes as $outcome) {
            $result = $outcome->result;
            $status = $result->getStatus();
            $output->writeln(sprintf(
                '%s %s%s',
                $this->glyph($status),
                $this->decorateName($status, $result->getName()),
                $outcome->fromCache ? ' (cached)' : '',
            ));
        }

        $overallStatus = $this->overallStatus($outcomes);
        $output->writeln('Monitoring status: ' . $this->overallLabel($overallStatus));

        // Degraded is operational: only a genuine outage fails the command.
        return $overallStatus === Status::Unhealthy ? Command::FAILURE : Command::SUCCESS;
    }

    private function glyph(Status $status): string
    {
        return match ($status) {
            Status::Healthy => ' ✅ ',
            Status::Degraded => ' ⚠️ ',
            Status::Unhealthy => '🚨 ',
        };
    }

    private function decorateName(Status $status, string $name): string
    {
        return match ($status) {
            Status::Healthy => '<info>' . $name . '</info>',
            Status::Degraded => '<comment>' . $name . '</comment>',
            Status::Unhealthy => '<error>' . $name . '</error>',
        };
    }

    private function overallLabel(Status $status): string
    {
        return match ($status) {
            Status::Healthy => 'OK',
            Status::Degraded => 'DEGRADED',
            Status::Unhealthy => 'FAILED',
        };
    }

    /**
     * @return array<class-string<MonitoringProvider>, ProviderExecutionOutcome>
     */
    private function executeActiveProviders(bool $useCache): array
    {
        $outcomes = [];

        foreach ($this->monitoringProviders as $provider) {
            if ($provider->isEnabled() && $provider->isActive()) {
                $outcomes[$provider::class] = $this->executionHandler->executeProviderWithMetadata(
                    $provider,
                    $useCache,
                );
            }
        }

        return $outcomes;
    }

    /**
    * @param array<class-string<MonitoringProvider>, ProviderExecutionOutcome> $outcomes
    */
    private function overallStatus(array $outcomes): Status
    {
        return Status::worst(
            ...array_map(
                static fn(ProviderExecutionOutcome $outcome): Status => $outcome->result->getStatus(),
                array_values($outcomes),
            ),
        );
    }
}
