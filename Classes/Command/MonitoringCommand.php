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

use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Result;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * ExecuteMonitorCommand.
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
    ) {
        parent::__construct(name: 'monitoring:run');
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('This command runs monitoring.');

        // todo: ./vendor/bin typo3 monitoring:run all --no-cache
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activeProvidersResult = $this->getActiveProvidersResult();

        if (count($activeProvidersResult) === 0) {
            $output->writeln('No active providers available. Skipping.');

            return Command::INVALID;
        }

        $output->writeln('Checking Monitoring status');
        foreach ($activeProvidersResult as $providerClass => $result) {
            $output->writeln(sprintf(
                '%s %s%s',
                $result->isHealthy() ? ' ✅' : '🚨',
                $result->isHealthy() ? '<info>' . $result->getName() . '</info>' : '<error>' . $result->getName() . '</error>',
                $this->isCacheableProvider($providerClass) ? ' (cached)' : '',
            ));
        }

        $isHealthy = $this->areAllResultsHealthy($activeProvidersResult);
        $output->writeln('Monitoring status: ' . ($isHealthy ? 'OK' : 'FAILED'));

        return $isHealthy ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<class-string<MonitoringProvider>, Result>
     */
    private function getActiveProvidersResult(): array
    {
        $status = [];

        foreach ($this->monitoringProviders as $provider) {
            if ($provider->isActive()) {
                $status[$provider::class] = $provider->execute();
            }
        }

        return $status;
    }

    /**
    * @param array<class-string<MonitoringProvider>, Result> $results
    */
    private function areAllResultsHealthy(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param class-string<MonitoringProvider> $providerClass
     */
    private function isCacheableProvider(string $providerClass): bool
    {
        return is_subclass_of($providerClass, CacheableMonitoringProvider::class);
    }
}
