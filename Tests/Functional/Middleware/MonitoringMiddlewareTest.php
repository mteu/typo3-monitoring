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
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\ConfigurableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\HtmlReasonProvider;
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
                'status' => 'healthy',
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
                'status' => 'unhealthy',
                'services' => ['test-provider' => ['status' => 'unhealthy']],
            ],
            json_decode((string)$response->getBody(), true),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function endpointJsonNeverExposesProviderReasons(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->process(new HtmlReasonProvider(), $handler);

        self::assertSame(503, $response->getStatusCode());

        $body = (string)$response->getBody();

        // Providers may put HTML in their reasons for the backend module (the shipped
        // SchedulerProvider does). The public endpoint only summarizes status, so neither the
        // reason text nor any markup it carries may ever appear in the payload.
        self::assertStringNotContainsString('<script>', $body);
        self::assertStringNotContainsString('<a href', $body);
        self::assertStringNotContainsString('top-level reason', $body);
        self::assertStringNotContainsString('sub reason', $body);

        /** @var array{status: string, services: array<string, array<string, mixed>>} $decoded */
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unhealthy', $decoded['status']);
        self::assertNodeExposesStatusOnly($decoded['services']['html-reason-provider']);
    }

    /**
     * Asserts a summarized service node exposes only `status` (and optionally nested `subResults`),
     * never a `reason`/`description`, recursively.
     *
     * @param array<string, mixed> $node
     */
    private static function assertNodeExposesStatusOnly(array $node): void
    {
        self::assertArrayNotHasKey('reason', $node);
        self::assertArrayNotHasKey('description', $node);
        self::assertSame(
            [],
            array_diff(array_keys($node), ['status', 'subResults']),
            'Only "status" and "subResults" may be exposed on the endpoint.',
        );

        if (array_key_exists('subResults', $node)) {
            /** @var array<string, array<string, mixed>> $subResults */
            $subResults = $node['subResults'];
            foreach ($subResults as $subResult) {
                self::assertNodeExposesStatusOnly($subResult);
            }
        }
    }

    private function process(
        MonitoringProvider $provider,
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
