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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;

/**
 * Builds scheduler task edit links. Branches at runtime between the v13
 * scheduler module route and the v14 record_edit route.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(SchedulerTaskLinkBuilder::class)]
final readonly class BackendSchedulerTaskLinkBuilder implements SchedulerTaskLinkBuilder
{
    private const string TABLE = 'tx_scheduler_task';

    public function __construct(
        private Router $router,
        private BackendUriBuilder $backendUriBuilder,
        private LoggerInterface $logger,
    ) {}

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
                    'edit' => [self::TABLE => [$uid => 'edit']],
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
