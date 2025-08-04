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
use mteu\Monitoring\Configuration\Provider\MiddlewareStatusProviderConfiguration;
use mteu\Monitoring\Middleware\MonitoringMiddleware;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

final class MonitoringMiddlewareTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private LoggerInterface&MockObject $logger;
    private RequestHandlerInterface&MockObject $handler;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    #[Test]
    public function middlewareReturnsHealthStatusWhenEndpointMatches(): void
    {
        $configuration = $this->createConfiguration('/health');
        $provider = $this->createHealthyProvider();
        $authorizer = $this->createAuthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpsRequest('/health');

        $this->handler->expects(self::never())->method('handle');

        $result = $middleware->process($request, $this->handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function middlewareReturns401WhenNotAuthorized(): void
    {
        $configuration = $this->createConfiguration('/health');
        $provider = $this->createHealthyProvider();
        $authorizer = $this->createUnauthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpsRequest('/health');

        $this->handler->expects(self::never())->method('handle');

        $result = $middleware->process($request, $this->handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(401, $result->getStatusCode());
    }

    #[Test]
    public function middlewareReturns403WhenNotHttps(): void
    {
        $configuration = $this->createConfiguration('/health');
        $provider = $this->createHealthyProvider();
        $authorizer = $this->createAuthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpRequest('/health');

        $this->handler->expects(self::never())->method('handle');

        $result = $middleware->process($request, $this->handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(403, $result->getStatusCode());
    }

    #[Test]
    public function middlewarePassesToHandlerWhenEndpointDoesNotMatch(): void
    {
        $configuration = $this->createConfiguration('/health');
        $provider = $this->createHealthyProvider();
        $authorizer = $this->createAuthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpsRequest('/different-path');
        $expectedResponse = $this->createMock(ResponseInterface::class);

        $this->handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $result = $middleware->process($request, $this->handler);

        self::assertSame($expectedResponse, $result);
    }

    #[Test]
    public function middlewarePassesToHandlerWhenEndpointIsEmpty(): void
    {
        $configuration = $this->createConfiguration('');
        $provider = $this->createHealthyProvider();
        $authorizer = $this->createAuthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpsRequest('/health');
        $expectedResponse = $this->createMock(ResponseInterface::class);

        $this->handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $result = $middleware->process($request, $this->handler);

        self::assertSame($expectedResponse, $result);
    }

    #[Test]
    public function middlewareReturns503WhenServiceIsUnhealthy(): void
    {
        $configuration = $this->createConfiguration('/health');
        $provider = $this->createUnhealthyProvider();
        $authorizer = $this->createAuthorizedAuthorizer();

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createHttpsRequest('/health');

        $this->handler->expects(self::never())->method('handle');

        $result = $middleware->process($request, $this->handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(503, $result->getStatusCode());
    }

    private function createConfiguration(string $endpoint): MonitoringConfiguration
    {
        $tokenAuthorizerConfig = new TokenAuthorizerConfiguration();
        $adminUserAuthorizerConfig = new AdminUserAuthorizerConfiguration();
        $providerConfig = new MiddlewareStatusProviderConfiguration();

        return new MonitoringConfiguration(
            $tokenAuthorizerConfig,
            $adminUserAuthorizerConfig,
            $providerConfig,
            $endpoint
        );
    }

    private function createHealthyProvider(): MonitoringProvider
    {
        return new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'test-provider';
            }

            public function getDescription(): string
            {
                return 'Test provider for functional tests';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function execute(): Result
            {
                return new MonitoringResult('test-provider', true);
            }
        };
    }

    private function createUnhealthyProvider(): MonitoringProvider
    {
        return new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'unhealthy-test-provider';
            }

            public function getDescription(): string
            {
                return 'Unhealthy test provider for functional tests';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function execute(): Result
            {
                return new MonitoringResult('unhealthy-test-provider', false);
            }
        };
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

            public static function getPriority(): int
            {
                return 10;
            }
        };
    }

    private function createUnauthorizedAuthorizer(): Authorizer
    {
        return new class () implements Authorizer {
            public function isActive(): bool
            {
                return true;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                return false;
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };
    }

    private function createHttpsRequest(string $path): ServerRequestInterface
    {
        $uri = new Uri('https://example.com' . $path);
        return new ServerRequest($uri, 'GET');
    }

    private function createHttpRequest(string $path): ServerRequestInterface
    {
        $uri = new Uri('http://example.com' . $path);
        return new ServerRequest($uri, 'GET');
    }

}
