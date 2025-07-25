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
final class ClassNameViewHelperTest extends UnitTestCase
{
    private ClassNameViewHelper $subject;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ClassNameViewHelper();
    }

    #[Test]
    #[DataProvider('provideFullyQualifiedClassNames')]
    public function viewHelperSuccessfullyConvertsFcqnToClassName(string $input, string $expectedClassName): void
    {
        $this->subject->setArguments(['fqcn' => $input]);

        self::assertEquals(
            $expectedClassName,
            $this->subject->render(),
        );
    }

    /**
     * @return \Generator<string[]>
     */
    public static function provideFullyQualifiedClassNames(): \Generator
    {
        yield ['mteu\Monitoring\Authorization\AdminUserAuthorizer', 'AdminUserAuthorizer'];
        yield ['\Generator', 'Generator'];
        yield [CacheManager::class, 'CacheManager'];
        yield ['Foo', 'Foo'];
        yield ['0/deded/0', '0/deded/0'];
        yield ['Weird string that couldn\'t possibly be a className', 'Weird string that couldn\'t possibly be a className'];
    }
}
