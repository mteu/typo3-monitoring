<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Middleware;

use CuyZ\Valinor\MapperBuilder;
use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Middleware\MonitoringMiddleware;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\TypedExtConf\Mapper\MapperFactory;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use PHPUnit\Framework;
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * MonitoringMiddlewareTest.
 *
 * Tests the middleware with real configuration mapping to identify issues.
 */
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
     * @param mixed[] $configurationData
     */
    private function createConfigurationFromData(array $configurationData): MonitoringConfiguration
    {
        $this->extensionConfiguration->expects(self::atLeastOnce())
            ->method('get')
            ->with('monitoring')
            ->willReturn($configurationData);

        $provider = new TypedExtensionConfigurationProvider(
            $this->extensionConfiguration,
            GeneralUtility::makeInstance(TreeMapperFactory::class),
        );
        return $provider->get(MonitoringConfiguration::class);
    }

    #[Test]
    public function testMiddlewareWithRealConfigurationFromSettings(): void
    {
        // This is the actual configuration structure from settings.php
        $configurationData = [
            'api' => [
                'endpoint' => '/monitor/health',
            ],
            'authorizer' => [
                'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                    'enabled' => '1',
                    'priority' => '-10',
                ],
                'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                    'enabled' => '1',
                    'priority' => '10',
                    'secret' => 'superSecretSalt',
                ],
            ],
        ];

        $this->configuration = $this->createConfigurationFromData($configurationData);

        $provider = $this->createMock(MonitoringProvider::class);
        $provider->method('isActive')->willReturn(true);
        $provider->method('getName')->willReturn('test_provider');
        $provider->method('execute')->willReturn(new MonitoringResult('test_provider', true));

        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->method('isAuthorized')->willReturn(true);

        // Test middleware creation and configuration loading
        try {
            $middleware = new MonitoringMiddleware(
                [$provider],
                [$authorizer],
                $this->configuration,
                $this->responseFactory,
                $this->logger
            );

            self::assertSame('/monitor/health', $this->configuration->endpoint, 'Endpoint should be correctly mapped from api.endpoint');
            self::assertTrue($this->configuration->tokenAuthorizerConfiguration->isEnabled(), 'Token authorizer should be enabled');
            self::assertSame('superSecretSalt', $this->configuration->tokenAuthorizerConfiguration->secret, 'Secret should be correctly mapped');
            self::assertSame('X-TYPO3-MONITORING-AUTH', $this->configuration->tokenAuthorizerConfiguration->authHeaderName, 'Auth header should be correctly mapped');
            self::assertTrue($this->configuration->adminUserAuthorizerConfiguration->isEnabled(), 'Admin user authorizer should be enabled');

        } catch (\Throwable $e) {
            self::fail(
                sprintf(
                    'Failed to create middleware with real configuration: %s',
                    $e->getMessage()
                )
            );
        }
    }

    #[Test]
    public function testMiddlewareProcessWithValidRequest(): void
    {
        // Configuration that should work
        $configurationData = [
            'api' => [
                'endpoint' => '/monitor/health',
            ],
            'authorizer' => [
                'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                    'enabled' => '0',  // disabled
                    'priority' => '-10',
                ],
                'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                    'enabled' => '1',  // enabled
                    'priority' => '10',
                    'secret' => 'test-secret',
                ],
            ],
        ];

        $this->configuration = $this->createConfigurationFromData($configurationData);

        // Create mock provider that reports healthy
        $provider = $this->createMock(MonitoringProvider::class);
        $provider->method('isActive')->willReturn(true);
        $provider->method('getName')->willReturn('database');
        $provider->method('execute')->willReturn(new MonitoringResult('database', true));

        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->method('isAuthorized')->willReturn(true);

        $middleware = new MonitoringMiddleware(
            [$provider],
            [$authorizer],
            $this->configuration,
            $this->responseFactory,
            $this->logger
        );

        // Create mock request that matches the monitoring endpoint
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/monitor/health');
        $uri->method('getScheme')->willReturn('https');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $responseBody = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withStatus')->willReturnSelf();
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($responseBody);

        $this->responseFactory->expects(self::once())
            ->method('createResponse')
            ->willReturn($response);

        $result = $middleware->process($request, $this->handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function testMiddlewareWithEmptyEndpointPassesThrough(): void
    {
        // Configuration with empty endpoint should pass through to next handler
        $configurationData = [
            'api' => [
                'endpoint' => '',
            ],
            'authorizer' => [
                'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                    'enabled' => '0',
                    'priority' => '10',
                    'secret' => '',
                    'authHeaderName' => '',
                ],
            ],
        ];

        $this->configuration = $this->createConfigurationFromData($configurationData);

        $middleware = new MonitoringMiddleware(
            [],
            [],
            $this->configuration,
            $this->responseFactory,
            $this->logger
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $expectedResponse = $this->createMock(ResponseInterface::class);

        $this->handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $result = $middleware->process($request, $this->handler);

        self::assertSame($expectedResponse, $result);
    }
}
