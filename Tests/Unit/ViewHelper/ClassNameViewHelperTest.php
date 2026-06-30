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

use mteu\Monitoring\ViewHelper\Backend\ClassNameViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * ClassNameViewHelperTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ClassNameViewHelper::class)]
final class ClassNameViewHelperTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private ClassNameViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ClassNameViewHelper();
    }

    #[Test]
    #[DataProvider('fullyQualifiedClassNameProvider')]
    public function fullyQualifiedClassNameIsConvertedToClassName(string $input, string $expectedClassName): void
    {
        $this->subject->setArguments(['fqcn' => $input]);

        self::assertSame($expectedClassName, $this->subject->render());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function fullyQualifiedClassNameProvider(): \Generator
    {
        yield 'namespaced fqcn keeps only the last segment' => ['mteu\Monitoring\Authorization\AdminUserAuthorizer', 'AdminUserAuthorizer'];
        yield 'leading backslash is stripped before extracting' => ['\Generator', 'Generator'];
        yield 'class constant fqcn' => [CacheManager::class, 'CacheManager'];
        yield 'bare class name is returned unchanged' => ['Foo', 'Foo'];
        yield 'forward slashes are not namespace separators' => ['0/deded/0', '0/deded/0'];
        yield 'arbitrary string without backslash is returned unchanged' => ['Weird string that couldn\'t possibly be a className', 'Weird string that couldn\'t possibly be a className'];
        // The segment after the final backslash is empty, so the helper falls
        // back to the (left-trimmed) input rather than emitting an empty string.
        yield 'trailing backslash falls back to the input' => ['mteu\Monitoring\Service\\', 'mteu\Monitoring\Service\\'];
        yield 'leading and trailing backslash falls back to the trimmed input' => ['\mteu\Service\\', 'mteu\Service\\'];
    }

    #[Test]
    #[DataProvider('blankInputProvider')]
    public function blankInputRendersAnEmptyString(string $input): void
    {
        $this->subject->setArguments(['fqcn' => $input]);

        self::assertSame('', $this->subject->render());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function blankInputProvider(): \Generator
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
    }

    #[Test]
    public function nonStringArgumentRendersAnEmptyString(): void
    {
        $this->subject->setArguments(['fqcn' => 42]);

        self::assertSame('', $this->subject->render());
    }

    #[Test]
    public function fallsBackToTaggedChildContentWhenNoArgumentIsGiven(): void
    {
        $this->subject->setArguments([]);
        $this->subject->setRenderChildrenClosure(
            static fn(): string => 'mteu\Monitoring\Provider\Scheduler\SchedulerProvider',
        );

        self::assertSame('SchedulerProvider', $this->subject->render());
    }
}
