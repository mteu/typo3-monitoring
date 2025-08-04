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
use mteu\Monitoring\Result\Result;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

#[Framework\Attributes\CoversClass(MonitoringMiddleware::class)]
final class MonitoringMiddlewareTest extends Framework\TestCase
{
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private ResponseFactoryInterface $responseFactory;
    private LoggerInterface&MockObject $logger;
    private RequestHandlerInterface&MockObject $handler;
    private MonitoringConfiguration $configuration;

    protected function setUp(): void
    {
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->responseFactory = new ResponseFactory();
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

        $provider = $this->createHealthyProvider();

        $authorizer = $this->createAuthorizedAuthorizer();

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

        $highPriorityAuthorizer = $this->createCallbackAuthorizer('high', false, $callOrder);
        $lowPriorityAuthorizer = $this->createCallbackAuthorizer('low', false, $callOrder);

        // Pass authorizers in priority order (high priority first)
        $middleware = new MonitoringMiddleware(
            [],
            [$highPriorityAuthorizer, $lowPriorityAuthorizer], // High priority should come first in DI
            $this->configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createRequestMock('/monitor/health', 'https');

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

    private function createRequestMock(string $path, string $scheme = 'https'): ServerRequestInterface
    {
        $uri = new Uri($scheme . '://example.com' . $path);
        return new ServerRequest($uri, 'GET');
    }

    private function createHealthyProvider(): MonitoringProvider
    {
        return new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'database';
            }

            public function getDescription(): string
            {
                return 'Test database provider for unit tests';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function execute(): Result
            {
                return new MonitoringResult('database', true);
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

    /**
     * @param array<string> $callOrder
     */
    private function createCallbackAuthorizer(string $identifier, bool $returnValue, array &$callOrder): Authorizer
    {
        return new class ($identifier, $returnValue, $callOrder) implements Authorizer {
            /**
             * @param array<string> $callOrder
             */
            public function __construct(
                private string $identifier,
                private bool $returnValue,
                private array &$callOrder
            ) {}

            public function isActive(): bool
            {
                return true;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                $this->callOrder[] = $this->identifier;
                return $this->returnValue;
            }

            public static function getPriority(): int
            {
                return 10;
            }
        };
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
