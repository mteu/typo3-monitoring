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
 * TokenAuthorizerConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Authorizer\TokenAuthorizerConfiguration::class)]
final class TokenAuthorizerConfigurationTest extends Framework\TestCase
{
    #[Test]
    #[Framework\Attributes\DataProvider('configurationDataProvider')]
    public function configurationWorksCorrectly(
        bool $enabled,
        int $priority,
        string $secret,
        string $authHeaderName
    ): void {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
            enabled: $enabled,
            priority: $priority,
            secret: $secret,
            authHeaderName: $authHeaderName,
        );

        self::assertSame($enabled, $subject->isEnabled());
        self::assertSame($priority, $subject->getPriority());
        self::assertSame($secret, $subject->secret);
        self::assertSame($authHeaderName, $subject->authHeaderName);
        self::assertSame($enabled, $subject->enabled);
        self::assertSame($priority, $subject->priority);
        self::assertInstanceOf(Src\Configuration\Authorizer\AuthorizerConfiguration::class, $subject);
    }

    #[Test]
    public function defaultValuesAreCorrect(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration();

        self::assertFalse($subject->isEnabled());
        self::assertSame(10, $subject->getPriority());
        self::assertSame('', $subject->secret);
        self::assertSame('', $subject->authHeaderName);
    }

    public static function configurationDataProvider(): \Generator
    {
        yield 'enabled with values' => [true, 50, 'secure-token-123', 'X-API-Token'];
        yield 'disabled configuration' => [false, -25, 'another-secret', 'Authorization'];
        yield 'zero priority' => [true, 0, '', 'Bearer'];
        yield 'complex configuration' => [true, 999, 'complex-secret-!@#$%', 'X-Custom-Auth'];
    }
}
