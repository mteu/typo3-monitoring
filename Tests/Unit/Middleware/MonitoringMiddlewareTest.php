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
use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Middleware\MonitoringMiddleware;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\CachedMonitoringResult;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

/**
 * MonitoringMiddlewareTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
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
    #[AllowMockObjectsWithoutExpectations]
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
            $this->logger,
            $this->createExecutionHandler(),
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
    #[AllowMockObjectsWithoutExpectations]
    public function inactiveAuthorizerIsSkippedAndNeverConsulted(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '0', 'secret' => '', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $callOrder = [];
        $inactiveButWouldAuthorize = $this->createCallbackAuthorizer(
            identifier: 'inactive',
            returnValue: true,
            callOrder: $callOrder,
            isActive: false,
        );

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$inactiveButWouldAuthorize],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $this->handler->expects(self::never())->method('handle');

        $response = $middleware->process(
            $this->createRequestMock('/monitor/health', 'https'),
            $this->handler,
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame([], $callOrder, 'isAuthorized() must not be called on an inactive authorizer');
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function activeAuthorizerStillGrantsAccessWhenAnotherIsInactive(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $callOrder = [];
        $inactive = $this->createCallbackAuthorizer('inactive', false, $callOrder, isActive: false);
        $active = $this->createCallbackAuthorizer('active', true, $callOrder, isActive: true);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$inactive, $active],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $response = $middleware->process(
            $this->createRequestMock('/monitor/health', 'https'),
            $this->handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['active'], $callOrder);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
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
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = $this->createRequestMock('/monitor/health', 'https');

        $middleware->process($request, $this->handler);

        // Verify high priority authorizer was called before low priority
        self::assertSame(['high', 'low'], $callOrder);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function normalizedParamsHttpsAttributeIsHonoredForReverseProxyRequests(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health', 'enforceHttps' => '1'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = $this->createRequestMock('/monitor/health', 'http')
            ->withAttribute('normalizedParams', $this->createNormalizedParamsMock(true));

        $this->handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function normalizedParamsHttpsAttributeIsHonoredWhenSchemeIsHttpsButProxyReportsHttp(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health', 'enforceHttps' => '1'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = $this->createRequestMock('/monitor/health', 'https')
            ->withAttribute('normalizedParams', $this->createNormalizedParamsMock(false));

        $this->handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function plainHttpRequestIsAllowedWhenEnforceHttpsIsDisabled(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health', 'enforceHttps' => '0'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = $this->createRequestMock('/monitor/health', 'http');

        $this->handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(200, $response->getStatusCode());
    }

    private function createExecutionHandler(?MonitoringCacheManager $cacheManager = null): MonitoringExecutionHandler
    {
        if ($cacheManager === null) {
            $typo3CacheManager = $this->createMock(CacheManager::class);
            $typo3CacheManager->method('getCache')
                ->willThrowException(new NoSuchCacheException('no cache in unit tests', 1234567890));
            $cacheManager = new MonitoringCacheManager($typo3CacheManager);
        }

        return new MonitoringExecutionHandler($cacheManager);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function providerIsExecutedOnlyOncePerRequest(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $provider = new class () implements MonitoringProvider {
            private int $executionCount = 0;
            public function getName(): string
            {
                return 'counted';
            }
            public function isEnabled(): bool
            {
                return true;
            }
            public function getDescription(): string
            {
                return 'Counts how often execute() is called.';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): Result
            {
                $this->executionCount++;
                return new MonitoringResult('counted', Status::Healthy);
            }
            public function getExecutionCount(): int
            {
                return $this->executionCount;
            }
        };

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $middleware->process($this->createRequestMock('/monitor/health', 'https'), $this->handler);

        self::assertSame(1, $provider->getExecutionCount());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function cacheableProviderIsRoutedThroughCacheManager(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $cacheableProvider = new class () implements CacheableMonitoringProvider {
            private int $executionCount = 0;
            public function getName(): string
            {
                return 'cacheable';
            }
            public function isEnabled(): bool
            {
                return true;
            }
            public function getDescription(): string
            {
                return 'Cacheable provider used to verify cache routing.';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): Result
            {
                $this->executionCount++;
                return new MonitoringResult('cacheable', Status::Healthy);
            }
            public function getCacheKey(): string
            {
                return 'cacheable-key';
            }
            public function getCacheLifetime(): int
            {
                return 60;
            }
            public function getExecutionCount(): int
            {
                return $this->executionCount;
            }
        };

        $cachedResult = new CachedMonitoringResult(
            new MonitoringResult('cacheable', Status::Healthy),
            new \DateTimeImmutable('now'),
            60,
        );

        $frontend = $this->createMock(FrontendInterface::class);
        $frontend->method('has')->with('cacheable-key')->willReturn(true);
        $frontend->expects(self::once())
            ->method('get')
            ->with('cacheable-key')
            ->willReturn($cachedResult);
        $frontend->expects(self::never())->method('set');

        $typo3CacheManager = $this->createMock(CacheManager::class);
        $typo3CacheManager->method('getCache')->willReturn($frontend);

        $cacheManager = new MonitoringCacheManager($typo3CacheManager);

        $middleware = new MonitoringMiddleware(
            [$cacheableProvider],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler($cacheManager),
        );

        $response = $middleware->process(
            $this->createRequestMock('/monitor/health', 'https'),
            $this->handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $cacheableProvider->getExecutionCount(), 'Cached provider must not re-execute when cache is warm');
    }

    private function createNormalizedParamsMock(bool $isHttps): NormalizedParams
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('isHttps')->willReturn($isHttps);

        return $normalizedParams;
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function disallowedHttpMethodProvider(): \Generator
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('disallowedHttpMethodProvider')]
    public function rejectsDisallowedHttpMethodsWith405AndAllowHeader(string $method): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $callOrder = [];
        $authorizer = $this->createCallbackAuthorizer('only-active', true, $callOrder);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$authorizer],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = (new ServerRequest(new Uri('https://example.com/monitor/health'), $method));

        $this->handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
        self::assertSame([], $callOrder, 'Authorization must not run for a rejected method');

        $body = (string)$response->getBody();
        self::assertNotSame('', $body, '405 responses include a JSON body');
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['code' => 405, 'error' => 'method-not-allowed'], $decoded);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function getRequestStillReturnsHealthyJson(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', (string)$response->getBody(), 'GET keeps the JSON body');
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function disabledProviderIsExcludedFromHealthOutputEvenIfActive(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        // Contradicts itself: disabled yet active. isEnabled() is the
        // kill-switch, so it must never be executed or reported.
        $disabledButActive = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'DisabledButActive';
            }

            public function isEnabled(): bool
            {
                return false;
            }

            public function getDescription(): string
            {
                return '';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function execute(): Result
            {
                return new MonitoringResult('DisabledButActive', Status::Unhealthy);
            }
        };

        $middleware = new MonitoringMiddleware(
            [$disabledButActive, $this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $response = $middleware->process(new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET'), $this->handler);

        // The disabled provider never ran, so only the healthy one counts.
        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $services = $decoded['services'] ?? null;
        self::assertIsArray($services);
        self::assertArrayHasKey('database', $services);
        self::assertArrayNotHasKey('DisabledButActive', $services);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function responseListsSubResultStatusesPerService(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $provider = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'Scheduler';
            }
            public function isEnabled(): bool
            {
                return true;
            }
            public function getDescription(): string
            {
                return 'Provider with sub-results.';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): Result
            {
                return new MonitoringResult('Scheduler', Status::Unhealthy, null, [
                    new MonitoringResult('Scheduler Execution', Status::Healthy),
                    new MonitoringResult('Failed Tasks', Status::Unhealthy),
                ]);
            }
        };

        $middleware = new MonitoringMiddleware(
            [$provider, $this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $response = $middleware->process(new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET'), $this->handler);

        self::assertSame(503, $response->getStatusCode());

        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                'status' => 'unhealthy',
                'services' => [
                    'Scheduler' => [
                        'status' => 'unhealthy',
                        'subResults' => [
                            'Scheduler Execution' => ['status' => 'healthy'],
                            'Failed Tasks' => ['status' => 'unhealthy'],
                        ],
                    ],
                    'database' => [
                        'status' => 'healthy',
                    ],
                ],
            ],
            $decoded,
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function responseOmitsServicesBlockWhenIncludeSubResultsIsDisabled(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health', 'includeServicesHealth' => '0'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        // An unhealthy provider with sub-results: even though the per-service
        // breakdown is suppressed, the overall verdict (and the HTTP status
        // derived from it) must still reflect the providers' health.
        $provider = new class () implements MonitoringProvider {
            public function getName(): string
            {
                return 'Scheduler';
            }
            public function isEnabled(): bool
            {
                return true;
            }
            public function getDescription(): string
            {
                return 'Provider with sub-results.';
            }
            public function isActive(): bool
            {
                return true;
            }
            public function execute(): Result
            {
                return new MonitoringResult('Scheduler', Status::Unhealthy, null, [
                    new MonitoringResult('Scheduler Execution', Status::Healthy),
                    new MonitoringResult('Failed Tasks', Status::Unhealthy),
                ]);
            }
        };

        $middleware = new MonitoringMiddleware(
            [$provider, $this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $response = $middleware->process(new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET'), $this->handler);

        // Health still computed from the providers, only the breakdown is gone.
        self::assertSame(503, $response->getStatusCode());

        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'unhealthy'], $decoded);
        self::assertArrayNotHasKey('services', $decoded, 'services must be omitted when includeServicesHealth is disabled');
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function responseIncludesServicesBlockWhenIncludeSubResultsIsExplicitlyEnabled(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health', 'includeServicesHealth' => '1'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $response = $middleware->process(new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET'), $this->handler);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                'status' => 'healthy',
                'services' => [
                    'database' => ['status' => 'healthy'],
                ],
            ],
            $decoded,
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function headRequestReturnsSameStatusAndHeadersWithEmptyBody(): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => '/monitor/health'],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        $request = new ServerRequest(new Uri('https://example.com/monitor/health'), 'HEAD');

        $response = $middleware->process($request, $this->handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('', (string)$response->getBody(), 'HEAD responses must not contain a body');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function endpointInputFormProvider(): \Generator
    {
        yield 'canonical leading slash' => ['/monitor/health'];
        yield 'missing leading slash (DTO default regression)' => ['monitor/health'];
        yield 'trailing slash' => ['/monitor/health/'];
        yield 'redundant slashes' => ['//monitor/health//'];
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('endpointInputFormProvider')]
    public function endpointMatchesRegardlessOfConfiguredSlashForm(string $configuredEndpoint): void
    {
        $this->configuration = $this->createConfigurationFromData([
            'api' => ['endpoint' => $configuredEndpoint],
            'authorizer' => ['mteu\\Monitoring\\Authorization\\TokenAuthorizer' => ['enabled' => '1', 'secret' => 'test-secret', 'priority' => '10', 'authHeaderName' => 'X-Auth']],
        ]);

        $middleware = new MonitoringMiddleware(
            [$this->createHealthyProvider()],
            [$this->createAuthorizedAuthorizer()],
            $this->configuration,
            $this->responseFactory,
            $this->logger,
            $this->createExecutionHandler(),
        );

        // The request path always has a single leading slash (PSR-7). The
        // health endpoint must match no matter which slash form the operator
        // configured, otherwise the middleware silently passes everything
        // through and the endpoint effectively does not exist.
        $response = $middleware->process(
            new ServerRequest(new Uri('https://example.com/monitor/health'), 'GET'),
            $this->handler,
        );

        self::assertSame(200, $response->getStatusCode());
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
            public function isEnabled(): bool
            {
                return true;
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
                return new MonitoringResult('database', Status::Healthy);
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

            public function getDescription(): string
            {
                return 'Description text';
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
    private function createCallbackAuthorizer(
        string $identifier,
        bool $returnValue,
        array &$callOrder,
        bool $isActive = true,
    ): Authorizer {
        return new class ($identifier, $returnValue, $callOrder, $isActive) implements Authorizer {
            /**
             * @param array<string> $callOrder
             */
            public function __construct(
                private string $identifier,
                private bool $returnValue,
                private array &$callOrder,
                private bool $isActive,
            ) {}

            public function isActive(): bool
            {
                return $this->isActive;
            }

            public function isAuthorized(ServerRequestInterface $request): bool
            {
                $this->callOrder[] = $this->identifier;
                return $this->returnValue;
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
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, bool, bool}>
     */
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
