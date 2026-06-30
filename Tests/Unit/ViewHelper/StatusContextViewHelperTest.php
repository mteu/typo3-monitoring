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

namespace mteu\Monitoring\Tests\Unit\ViewHelper;

use mteu\Monitoring\ViewHelper\Backend\StatusContextViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * StatusContextViewHelperTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(StatusContextViewHelper::class)]
final class StatusContextViewHelperTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private StatusContextViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new StatusContextViewHelper();
    }

    #[Test]
    #[DataProvider('statusToContextProvider')]
    public function statusValueMapsToItsBootstrapContext(string $status, string $expectedContext): void
    {
        $this->subject->setArguments(['status' => $status]);

        self::assertSame($expectedContext, $this->subject->render());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function statusToContextProvider(): \Generator
    {
        yield 'healthy maps to success' => ['healthy', 'success'];
        yield 'degraded maps to warning' => ['degraded', 'warning'];
        yield 'unhealthy maps to danger' => ['unhealthy', 'danger'];
        yield 'surrounding whitespace is trimmed before matching' => ['  healthy  ', 'success'];
        yield 'unknown status falls back to default' => ['flaky', 'default'];
        yield 'empty string falls back to default' => ['', 'default'];
    }

    #[Test]
    public function nonStringArgumentFallsBackToDefault(): void
    {
        $this->subject->setArguments(['status' => 42]);

        self::assertSame('default', $this->subject->render());
    }

    #[Test]
    public function fallsBackToTaggedChildContentWhenNoArgumentIsGiven(): void
    {
        $this->subject->setArguments([]);
        $this->subject->setRenderChildrenClosure(static fn(): string => 'unhealthy');

        self::assertSame('danger', $this->subject->render());
    }
}
