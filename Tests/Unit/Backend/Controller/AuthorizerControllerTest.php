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

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Backend\Controller\AuthorizerController;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AuthorizerControllerTest.
 *
 * @todo: Tests own logic but bypass constructors. Fixing properly needs refactoring the controllers.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AuthorizerController::class)]
final class AuthorizerControllerTest extends Framework\TestCase
{
    #[Test]
    public function collectAuthorizerStatusesReturnsEmptyArrayWhenNoAuthorizersAreRegistered(): void
    {
        self::assertSame([], $this->collectAuthorizerStatuses([]));
    }

    #[Test]
    public function collectAuthorizerStatusesReportsEachAuthorizersActiveStateAndPriority(): void
    {
        // Two distinct implementations: anonymous classes declared separately
        // are separate types, so they keep distinct ::class keys and static priorities.
        $activeAuthorizer = new class () implements Authorizer {
            public function isActive(): bool
            {
                return true;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                return true;
            }

            public function getDescription(): string
            {
                return 'Description text.';
            }

            public static function getPriority(): int
            {
                return 30;
            }
        };

        $inactiveAuthorizer = new class () implements Authorizer {
            public function isActive(): bool
            {
                return false;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                return false;
            }

            public function getDescription(): string
            {
                return 'Description text';
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };

        $statuses = $this->collectAuthorizerStatuses([$activeAuthorizer, $inactiveAuthorizer]);

        self::assertTrue($statuses[$activeAuthorizer::class]['isActive']);
        self::assertSame(30, $statuses[$activeAuthorizer::class]['priority']);
        self::assertFalse($statuses[$inactiveAuthorizer::class]['isActive']);
        self::assertSame(10, $statuses[$inactiveAuthorizer::class]['priority']);
    }

    /**
     * Invokes the private collectAuthorizerStatuses() on a controller created
     * without its constructor, injecting only the authorizers it reads.
     *
     * @param list<Authorizer> $authorizers
     * @return array<class-string, array{isActive: bool, priority: int}>
     */
    private function collectAuthorizerStatuses(array $authorizers): array
    {
        $reflection = new \ReflectionClass(AuthorizerController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('authorizers')->setValue($controller, $authorizers);

        $result = $reflection->getMethod('collectAuthorizerStatuses')->invoke($controller);
        self::assertIsArray($result);

        /** @var array<class-string, array{isActive: bool, priority: int}> $result */
        return $result;
    }
}
