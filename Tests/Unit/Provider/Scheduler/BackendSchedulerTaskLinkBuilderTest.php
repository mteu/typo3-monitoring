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

namespace mteu\Monitoring\Tests\Unit\Provider\Scheduler;

use mteu\Monitoring\Provider\Scheduler\BackendSchedulerTaskLinkBuilder;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Http\Uri;

/**
 * BackendSchedulerTaskLinkBuilderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(BackendSchedulerTaskLinkBuilder::class)]
final class BackendSchedulerTaskLinkBuilderTest extends Framework\TestCase
{
    #[Test]
    public function usesSchedulerModuleRouteWhenItExists(): void
    {
        $uriBuilder = self::createMock(BackendUriBuilder::class);
        $uriBuilder
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('scheduler_manage', ['action' => 'edit', 'uid' => 42])
            ->willReturn(new Uri('/typo3/module/scheduler?action=edit&uid=42'))
        ;

        $link = $this->createLinkBuilder(hasSchedulerRoute: true, uriBuilder: $uriBuilder)->buildEditLink(42);

        self::assertSame('/typo3/module/scheduler?action=edit&uid=42', $link);
    }

    #[Test]
    public function usesRecordEditRouteWhenSchedulerModuleRouteIsMissing(): void
    {
        $uriBuilder = self::createMock(BackendUriBuilder::class);
        $uriBuilder
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('record_edit', ['edit' => ['tx_scheduler_task' => [42 => 'edit']]])
            ->willReturn(new Uri('/typo3/record/edit?token=abc'))
        ;

        $link = $this->createLinkBuilder(hasSchedulerRoute: false, uriBuilder: $uriBuilder)->buildEditLink(42);

        self::assertSame('/typo3/record/edit?token=abc', $link);
    }

    #[Test]
    public function buildEditLinkReturnsNullWhenNoRouteCanBeResolved(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());

        self::assertNull($this->createLinkBuilder(hasSchedulerRoute: false, uriBuilder: $uriBuilder)->buildEditLink(42));
    }

    #[Test]
    public function renderEditLinkWrapsTheUriInAnAnchorTag(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturn(new Uri('/typo3/record/edit?uid=42'));

        $rendered = $this->createLinkBuilder(hasSchedulerRoute: false, uriBuilder: $uriBuilder)
            ->renderEditLink(42, '#42 (Import task)');

        self::assertSame('<a href="/typo3/record/edit?uid=42">#42 (Import task)</a>', $rendered);
    }

    #[Test]
    public function renderEditLinkReturnsNullInsteadOfAnAnchorWithEmptyHref(): void
    {
        $uriBuilder = self::createStub(BackendUriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willThrowException(new RouteNotFoundException());

        $rendered = $this->createLinkBuilder(hasSchedulerRoute: false, uriBuilder: $uriBuilder)
            ->renderEditLink(42, '#42 (Import task)');

        self::assertNull($rendered);
    }

    private function createLinkBuilder(
        bool $hasSchedulerRoute,
        BackendUriBuilder $uriBuilder,
    ): BackendSchedulerTaskLinkBuilder {
        $router = self::createStub(Router::class);
        $router->method('hasRoute')->willReturnMap([
            ['scheduler_manage', $hasSchedulerRoute],
        ]);

        return new BackendSchedulerTaskLinkBuilder($router, $uriBuilder, new NullLogger());
    }
}
