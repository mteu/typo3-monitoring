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

namespace mteu\Monitoring\Tests\Unit\Middleware;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Middleware\MonitoringMiddleware;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

#[Framework\Attributes\CoversClass(MonitoringMiddleware::class)]
final class MonitoringMiddlewareTest extends Framework\TestCase
{
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private LoggerInterface&MockObject $logger;
    private RequestHandlerInterface&MockObject $handler;
    private MonitoringConfiguration $configuration;

    protected function setUp(): void
    {
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    /**
     * @param array{
     * api: array{endpoint: string},
     * authorizer: array<string, array{enabled: string, secret: string, priority: string, authHeaderName: string}>
     * } $configurationData
     */
    #[Test]
    #[DataProvider('middlewareProcessDataProvider')]
    public function middlewareProcessesRequestCorrectly(
        array $configurationData,
        bool $shouldCallHandler,
        bool $shouldCreateResponse
    ): void {
        $this->configuration = $this->createConfigurationFromData($configurationData);

        $provider = $this->createMock(MonitoringProvider::class);
        $provider->method('isActive')->willReturn(true);
        $provider->method('getName')->willReturn('database');
        $provider->method('execute')->willReturn(new MonitoringResult('database', true));

        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->method('isAuthorized')->willReturn(true);

        $middleware = new MonitoringMiddleware(
            $shouldCreateResponse ? [$provider] : [],
            $shouldCreateResponse ? [$authorizer] : [],
            $this->configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createRequestMock('/monitor/health', 'https');

        if ($shouldCallHandler) {
            $expectedResponse = $this->createMock(ResponseInterface::class);
            $this->handler->expects(self::once())
                ->method('handle')
                ->with($request)
                ->willReturn($expectedResponse);
        } else {
            $this->handler->expects(self::never())->method('handle');
        }

        if ($shouldCreateResponse) {
            $response = $this->createResponseMock();
            $this->responseFactory->expects(self::once())
                ->method('createResponse')
                ->willReturn($response);
        } else {
            $this->responseFactory->expects(self::never())->method('createResponse');
        }

        $middleware->process($request, $this->handler);
    }

    #[Test]
    public function authorizersAreCalledInPriorityOrder(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $callOrder = [];

        $highPriorityAuthorizer = $this->createMock(Authorizer::class);
        $highPriorityAuthorizer->method('isAuthorized')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 'high';
            return false; // Don't authorize to let next authorizer be called
        });

        $lowPriorityAuthorizer = $this->createMock(Authorizer::class);
        $lowPriorityAuthorizer->method('isAuthorized')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 'low';
            return false; // Don't authorize
        });

        // Pass authorizers in priority order (high priority first)
        $middleware = new MonitoringMiddleware(
            [],
            [$highPriorityAuthorizer, $lowPriorityAuthorizer], // High priority should come first in DI
            $this->configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createRequestMock('/monitor/health', 'https');
        $response = $this->createResponseMock();

        $this->responseFactory->expects(self::once())
            ->method('createResponse')
            ->willReturn($response);

        $middleware->process($request, $this->handler);

        // Verify high priority authorizer was called before low priority
        self::assertSame(['high', 'low'], $callOrder);
    }

    /**
     * @param mixed[] $configurationData
     */
    private function createConfigurationFromData(array $configurationData): MonitoringConfiguration
    {
        $this->extensionConfiguration->expects(self::atLeastOnce())
            ->method('get')
            ->with('monitoring')
            ->willReturn($configurationData);

        $mapperFactory = new TreeMapperFactory();
        $provider = new TypedExtensionConfigurationProvider($this->extensionConfiguration, $mapperFactory);
        return $provider->get(MonitoringConfiguration::class);
    }

    private function createRequestMock(string $path, string $scheme = 'https'): ServerRequestInterface&MockObject
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getScheme')->willReturn($scheme);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function createResponseMock(): ResponseInterface&MockObject
    {
        $responseBody = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withStatus')->willReturnSelf();
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($responseBody);

        return $response;
    }

    public static function middlewareProcessDataProvider(): \Generator
    {
        yield 'valid endpoint returns json response' => [
            ['api' => ['endpoint' => '/monitor/health'], 'authorizer' => ['mteu\Monitoring\Authorization\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']]],
            false, // should not call handler
            true,  // should create response
        ];

        yield 'empty endpoint passes to next handler' => [
            ['api' => ['endpoint' => ''], 'authorizer' => ['mteu\Monitoring\Authorization\TokenAuthorizer' => ['enabled' => '0', 'secret' => '', 'priority' => '10', 'authHeaderName' => '']]],
            true,  // should call handler
            false, // should not create response
        ];
    }
}
