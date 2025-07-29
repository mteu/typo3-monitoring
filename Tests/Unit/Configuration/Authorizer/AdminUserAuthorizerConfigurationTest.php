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

namespace mteu\Monitoring\Tests\Unit\Configuration\Authorizer;

use mteu\Monitoring as Src;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * AdminUserAuthorizerConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration::class)]
final class AdminUserAuthorizerConfigurationTest extends Framework\TestCase
{
    #[Test]
    #[Framework\Attributes\DataProvider('configurationDataProvider')]
    public function configurationWorksCorrectly(bool $enabled, int $priority): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(
            enabled: $enabled,
            priority: $priority,
        );

        self::assertSame($enabled, $subject->isEnabled());
        self::assertSame($priority, $subject->getPriority());
        self::assertSame($enabled, $subject->enabled);
        self::assertSame($priority, $subject->priority);
        self::assertInstanceOf(Src\Configuration\Authorizer\AuthorizerConfiguration::class, $subject);
    }

    #[Test]
    public function defaultValuesAreCorrect(): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration();

        self::assertFalse($subject->isEnabled());
        self::assertSame(-10, $subject->getPriority());
    }

    public static function configurationDataProvider(): \Generator
    {
        yield 'enabled with positive priority' => [true, 25];
        yield 'disabled with negative priority' => [false, -50];
        yield 'enabled with zero priority' => [true, 0];
        yield 'disabled with high priority' => [false, 999];
    }
}
