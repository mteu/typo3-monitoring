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

use mteu\Monitoring\Reporter\NotificationDecision;
use mteu\Monitoring\Reporter\ReportDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ReportCommand.
 *
 * Cron-friendly trigger for the push reporters. Thin wrapper around
 * {@see ReportDispatcher} so it stays identical to the (optional)
 * scheduler task.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsCommand(name: 'monitoring:report')]
final class ReportCommand extends Command
{
    public function __construct(
        private readonly ReportDispatcher $dispatcher,
    ) {
        parent::__construct(name: 'monitoring:report');
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('Evaluates monitoring status and dispatches push notifications to active reporters.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $decision = $this->dispatcher->dispatch();

        $output->writeln(match ($decision) {
            NotificationDecision::None => 'No notification dispatched.',
            NotificationDecision::Unhealthy => 'Dispatched: unhealthy status.',
            NotificationDecision::Reminder => 'Dispatched: reminder for ongoing unhealthy status.',
            NotificationDecision::Recovered => 'Dispatched: recovery notice.',
        });

        return Command::SUCCESS;
    }
}
