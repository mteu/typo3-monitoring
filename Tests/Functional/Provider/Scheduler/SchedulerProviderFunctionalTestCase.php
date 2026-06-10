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

namespace mteu\Monitoring\Tests\Functional\Provider\Scheduler;

use mteu\Monitoring\Configuration\Provider\SchedulerProviderConfiguration;
use mteu\Monitoring\Provider\Scheduler\SchedulerProvider;
use mteu\Monitoring\Provider\Scheduler\SchedulerTaskLinkBuilder;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerHeartbeat;
use mteu\Monitoring\Tests\Unit\Fixtures\Scheduler\InMemorySchedulerTaskRepository;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Uri;

/**
 * ProviderFunctionalTestCase.
 *
 * Shared wiring for provider functional tests. Concrete classes decide which
 * extensions to load via $coreExtensionsToLoad — that selection is per class,
 * because the framework resolves it once during setUp().
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract class SchedulerProviderFunctionalTestCase extends MonitoringFunctionalTestCase
{
    protected const int NOW = 1_700_000_000;

    final protected function createSchedulerProvider(
        ?SchedulerProviderConfiguration $configuration = null,
    ): SchedulerProvider {
        return new SchedulerProvider(
            $configuration ?? new SchedulerProviderConfiguration(),
            new InMemorySchedulerTaskRepository(),
            new InMemorySchedulerHeartbeat(self::NOW - 60),
            new MockClock((new \DateTimeImmutable())->setTimestamp(self::NOW)),
            $this->createLinkBuilder(null),
            new NullLogger(),
        );
    }

    /**
     * Builds the real link builder with a stubbed routing stack: when no base
     * URI is given the UriBuilder fails to resolve a route, so no link is built.
     */
    private function createLinkBuilder(?string $baseUri): SchedulerTaskLinkBuilder
    {
        $router = self::createStub(Router::class);
        $router->method('hasRoute')->willReturn(false);

        $uriBuilder = self::createStub(UriBuilder::class);

        if ($baseUri === null) {
            $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());
        } else {
            $uriBuilder->method('buildUriFromRoute')->willReturn(new Uri($baseUri));
        }

        return new SchedulerTaskLinkBuilder($router, $uriBuilder, new NullLogger());
    }
}
