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

namespace mteu\Monitoring\Tests\Unit\Backend\Controller;

use mteu\Monitoring\Backend\Controller\ProviderController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ProviderControllerTest.
 *
 * @todo: Tests own logic but bypass constructors. Fixing properly needs refactoring the controllers.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ProviderController::class)]
final class ProviderControllerTest extends TestCase
{
    /**
     * The list view orders providers alphabetically by display name so their
     * position stays stable across health transitions.
     *
     * @param array{name: string} $a
     * @param array{name: string} $b
     */
    #[Test]
    #[DataProvider('nameComparisonProvider')]
    public function providersAreOrderedAlphabeticallyByName(array $a, array $b, int $expectedSign): void
    {
        $reflection = new \ReflectionClass(ProviderController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $result = $reflection->getMethod('compareByName')->invoke($controller, $a, $b);

        self::assertSame($expectedSign, $result <=> 0);
    }

    /**
     * @return \Generator<string, array{array{name: string}, array{name: string}, int}>
     */
    public static function nameComparisonProvider(): \Generator
    {
        yield 'earlier name sorts first' => [['name' => 'AlphaProvider'], ['name' => 'BetaProvider'], -1];
        yield 'later name sorts after' => [['name' => 'BetaProvider'], ['name' => 'AlphaProvider'], 1];
        yield 'identical names are equal' => [['name' => 'HealthyProvider'], ['name' => 'HealthyProvider'], 0];
        yield 'comparison is case-insensitive' => [['name' => 'alphaProvider'], ['name' => 'AlphaProvider'], 0];
    }
}
