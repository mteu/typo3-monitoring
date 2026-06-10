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

namespace mteu\Monitoring\Tests\Functional\Middleware;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Reporter\EmailReporterConfiguration;
use mteu\Monitoring\Configuration\Reporter\ReportDispatcherConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Middleware\MonitoringMiddleware;
use mteu\Monitoring\Tests\Functional\Fixtures\ConfigurableProvider;
use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

/**
 * MonitoringMiddlewareTest.
 *
 * End-to-end smoke tests through the real DI-wired execution handler and the
 * real PSR-17 response factory, asserting the actual JSON payload the
 * endpoint serves. All routing, method, authorization and HTTPS edge cases
 * are covered by the unit test suite.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class MonitoringMiddlewareTest extends MonitoringFunctionalTestCase
{
    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function servesHealthyJsonStatusForMatchingEndpoint(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->process(
            new ConfigurableProvider('test-provider', healthy: true),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'isHealthy' => true,
                'services' => ['test-provider' => ['status' => 'healthy']],
            ],
            json_decode((string)$response->getBody(), true),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function servesUnhealthyJsonStatusWith503WhenAProviderFails(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->process(
            new ConfigurableProvider('test-provider', healthy: false),
            $handler,
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(
            [
                'isHealthy' => false,
                'services' => ['test-provider' => ['status' => 'unhealthy']],
            ],
            json_decode((string)$response->getBody(), true),
        );
    }

    private function process(
        ConfigurableProvider $provider,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $middleware = new MonitoringMiddleware(
            [$provider],
            [$this->createAuthorizedAuthorizer()],
            new MonitoringConfiguration(
                new TokenAuthorizerConfiguration(),
                new AdminUserAuthorizerConfiguration(),
                new EmailReporterConfiguration(),
                new ReportDispatcherConfiguration(),
                '/health',
            ),
            new ResponseFactory(),
            new NullLogger(),
            $this->get(MonitoringExecutionHandler::class),
        );

        return $middleware->process(
            new ServerRequest(new Uri('https://example.com/health'), 'GET'),
            $handler,
        );
    }

    private function createAuthorizedAuthorizer(): Authorizer
    {
        return new class () implements Authorizer {
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
                return 'Authorized stub';
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };
    }
}
