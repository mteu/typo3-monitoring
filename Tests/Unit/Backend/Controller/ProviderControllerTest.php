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
     * The list view sorts providers by this rank (ascending), so operators see
     * actionable ones first: failing (0) before healthy (1) before inactive (2)
     * before disabled (3). isEnabled() outranks isActive(), and an active
     * provider without a recorded verdict is treated as not-yet-healthy.
     *
     * @param array{isEnabled: bool, isActive: bool, isHealthy?: bool} $provider
     */
    #[Test]
    #[DataProvider('sortRankProvider')]
    public function providerSortRankReflectsActionability(array $provider, int $expectedRank): void
    {
        $reflection = new \ReflectionClass(ProviderController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        self::assertSame($expectedRank, $reflection->getMethod('getProviderSortRank')->invoke($controller, $provider));
    }

    /**
     * @return \Generator<string, array{array{isEnabled: bool, isActive: bool, isHealthy?: bool}, int}>
     */
    public static function sortRankProvider(): \Generator
    {
        yield 'active unhealthy sorts first' => [['isEnabled' => true, 'isActive' => true, 'isHealthy' => false], 0];
        yield 'active without a verdict is treated as not healthy' => [['isEnabled' => true, 'isActive' => true], 0];
        yield 'active healthy' => [['isEnabled' => true, 'isActive' => true, 'isHealthy' => true], 1];
        yield 'enabled but inactive' => [['isEnabled' => true, 'isActive' => false], 2];
        yield 'disabled sorts last even when active' => [['isEnabled' => false, 'isActive' => true], 3];
    }
}
