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
    public function isDisabledWhenSecretIsEmpty(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
            enabled: true,
            secret: '',
        );

        self::assertFalse($subject->isEnabled());
    }

    #[Test]
    public function isDisabledByDefault(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration();

        self::assertFalse($subject->isEnabled());
    }

    #[Test]
    public function isEnabledWhenSecretIsSetAndEnabledIsTrue(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
            enabled: true,
            secret: 'my-secret',
        );

        self::assertTrue($subject->isEnabled());
    }
}
