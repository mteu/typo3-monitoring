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

namespace mteu\Monitoring\Tests\Unit\Provider;

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Provider\MiddlewareStatusProviderConfiguration;
use mteu\Monitoring\Provider\MiddlewareStatusProvider;
use mteu\Monitoring\Result\MonitoringResult;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * MiddlewareStatusProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(MiddlewareStatusProvider::class)]
final class MiddlewareStatusProviderTest extends Framework\TestCase
{
    private ClientInterface&MockObject $httpClient;
    private RequestFactoryInterface&MockObject $requestFactory;
    private SiteFinder&MockObject $siteFinder;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        // Initialize TYPO3 configuration for HashService
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'test-encryption-key-for-unit-tests';

        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->siteFinder = $this->createMock(SiteFinder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->setupSiteFinder();
    }

    #[Test]
    public function getName(): void
    {
        $provider = $this->createProvider();
        self::assertSame('MiddlewareStatus', $provider->getName());
    }

    #[Test]
    public function getDescription(): void
    {
        $provider = $this->createProvider();
        $description = $provider->getDescription();
        self::assertStringContainsString('meta-provider', $description);
        self::assertStringContainsString('monitoring middleware', $description);
    }

    #[Test]
    #[DataProvider('inactiveConditions')]
    public function isActiveReturnsFalseWhenConditionsNotMet(
        string $endpoint,
        bool $providerEnabled,
        bool $hasRecursionHeader
    ): void {
        if ($hasRecursionHeader) {
            $_SERVER['HTTP_X_MIDDLEWARE_STATUS_REQUEST'] = '1';
        }

        try {
            $provider = $this->createProvider($endpoint, $providerEnabled);
            self::assertFalse($provider->isActive());
        } finally {
            unset($_SERVER['HTTP_X_MIDDLEWARE_STATUS_REQUEST']);
        }
    }

    #[Test]
    public function isActiveReturnsTrueWhenConditionsMet(): void
    {
        $provider = $this->createProvider();
        self::assertTrue($provider->isActive());
    }

    #[Test]
    #[DataProvider('httpStatusCodes')]
    public function executeHandlesHttpStatusCodes(int $statusCode, bool $expectedHealthy): void
    {
        $provider = $this->createProvider();
        $this->setupHttpResponse($statusCode);

        $result = $provider->execute();

        self::assertInstanceOf(MonitoringResult::class, $result);
        self::assertSame($expectedHealthy, $result->isHealthy());
        self::assertSame('MiddlewareStatus', $result->getName());

        if (!$expectedHealthy) {
            $reason = $result->getReason();
            self::assertIsString($reason);
            self::assertStringContainsString((string)$statusCode, $reason);
        }
    }

    /**
     * @param 'warning'|'error' $expectedLogMethod
     */
    #[Test]
    #[DataProvider('exceptionTypes')]
    public function executeHandlesExceptions(
        \Exception $exception,
        string $expectedLogMethod,
        string $expectedReasonSubstring
    ): void {
        $provider = $this->createProvider();
        $this->setupHttpException($exception);

        $this->logger
            ->expects(self::once())
            ->method($expectedLogMethod);

        $result = $provider->execute();

        self::assertInstanceOf(MonitoringResult::class, $result);
        self::assertFalse($result->isHealthy());
        $reason = $result->getReason();
        self::assertIsString($reason);
        self::assertStringContainsString($expectedReasonSubstring, $reason);
    }

    private function createProvider(
        string $endpoint = '/monitor/health',
        bool $providerEnabled = true
    ): MiddlewareStatusProvider {
        $configuration = new MonitoringConfiguration(
            tokenAuthorizerConfiguration: new TokenAuthorizerConfiguration(
                enabled: true,
                priority: 10,
                secret: 'test-secret',
                authHeaderName: 'X-AUTH'
            ),
            adminUserAuthorizerConfiguration: new AdminUserAuthorizerConfiguration(
                enabled: false,
                priority: -10
            ),
            providerConfiguration: new MiddlewareStatusProviderConfiguration($providerEnabled),
            endpoint: $endpoint
        );

        return new MiddlewareStatusProvider(
            $configuration,
            $this->httpClient,
            $this->requestFactory,
            $this->siteFinder,
            $this->logger,
        );
    }

    private function setupSiteFinder(): void
    {
        $site = $this->createMock(Site::class);
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);

        $uri->method('getScheme')->willReturn('https');
        $uri->method('withScheme')->willReturn($uri);
        $uri->method('__toString')->willReturn('https://example.com');

        $site->method('getBase')->willReturn($uri);
        $this->siteFinder->method('getAllSites')->willReturn([$site]);
    }

    private function setupHttpResponse(int $statusCode): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);
    }

    private function setupHttpException(\Exception $exception): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willThrowException($exception);
    }

    public static function inactiveConditions(): \Generator
    {
        yield 'empty endpoint' => ['', true, false];
        yield 'disabled provider' => ['/monitor/health', false, false];
        yield 'recursion header present' => ['/monitor/health', true, true];
    }

    public static function httpStatusCodes(): \Generator
    {
        yield 'HTTP 200 OK' => [200, true];
        yield 'HTTP 503 Service Unavailable' => [503, true];
        yield 'HTTP 401 Unauthorized' => [401, false];
        yield 'HTTP 403 Forbidden' => [403, false];
        yield 'HTTP 404 Not Found' => [404, false];
        yield 'HTTP 500 Internal Server Error' => [500, false];
    }

    public static function exceptionTypes(): \Generator
    {
        $clientException = new class ('Connection failed') extends \Exception implements ClientExceptionInterface {};

        yield 'client exception' => [$clientException, 'warning', 'HTTP request failed'];
        yield 'unexpected exception' => [new \RuntimeException('Unexpected error'), 'error', 'Unexpected error'];
    }
}
