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

namespace mteu\Monitoring\Middleware;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MiddlewareStatusProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * MonitoringMiddleware.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringMiddleware implements MiddlewareInterface
{
    /**
     * @var list<non-empty-string>
     */
    private const array ALLOWED_METHODS = ['GET', 'HEAD'];

    public function __construct(
        /** @var iterable<MonitoringProvider> $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        /** @var iterable<Authorizer> $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        private MonitoringConfiguration $monitoringConfiguration,
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
        private MonitoringExecutionHandler $executionHandler,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->monitoringConfiguration->endpoint === '') {
            return $handler->handle($request);
        }

        if (!$this->isValid($request)) {
            return $handler->handle($request);
        }

        $writeBody = $request->getMethod() !== 'HEAD';

        if (!in_array($request->getMethod(), self::ALLOWED_METHODS, true)) {
            try {
                return $this->jsonResponse(
                    [
                        'code' => 405,
                        'error' => 'method-not-allowed',
                    ],
                    405,
                    $writeBody,
                )->withHeader('Allow', implode(', ', self::ALLOWED_METHODS));
            } catch (\JsonException $e) {
                $this->logger->error($e->getMessage());
                return $handler->handle($request);
            }
        }

        if ($this->monitoringConfiguration->enforceHttps && !$this->isHttps($request)) {
            try {
                return $this->jsonResponse(
                    [
                        'code' => 403,
                        'error' => 'unsupported-protocol',
                    ],
                    403,
                    $writeBody,
                );
            } catch (\JsonException $e) {
                $this->logger->error($e->getMessage());
                return $handler->handle($request);
            }
        }

        if (!$this->isAuthorized($request)) {
            try {
                return $this->jsonResponse(
                    [
                        'code' => 401,
                        'error' => 'unauthorized',
                    ],
                    401,
                    $writeBody,
                );
            } catch (\JsonException $e) {
                $this->logger->error($e->getMessage());
                return $handler->handle($request);
            }
        }

        try {
            $status = $this->getHealthStatus();
            $isHealthy = !in_array(false, $status, true);

            return $this->jsonResponse(
                [
                    'isHealthy' => $isHealthy,
                    'services' => array_map(
                        static fn(bool $serviceStatus): string => $serviceStatus ? 'healthy' : 'unhealthy',
                        $status
                    ),
                ],
                $isHealthy ? 200 : 503,
                $writeBody,
            );
        } catch (\JsonException $e) {
            $this->logger->error($e->getMessage());
            return $handler->handle($request);
        }
    }

    private function isValid(ServerRequestInterface $request): bool
    {
        return rtrim($request->getUri()->getPath(), '/') === $this->monitoringConfiguration->endpoint;
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');

        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->isHttps();
        }

        return $request->getUri()->getScheme() === 'https';
    }

    /**
     * Checks if request is authorized by any of the registered authorizers. First one take the win.
     */
    private function isAuthorized(ServerRequestInterface $request): bool
    {
        foreach ($this->authorizers as $authorizer) {
            if (!$authorizer->isActive()) {
                continue;
            }

            if ($authorizer->isAuthorized($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<non-empty-string, bool>
     */
    private function getHealthStatus(): array
    {
        $status = [];

        foreach ($this->monitoringProviders as $provider) {
            if ($provider->isActive()) {

                if ($provider instanceof MiddlewareStatusProvider) {
                    continue;
                }

                $status[$provider->getName()] = $this->executionHandler->executeProvider($provider)->isHealthy();
            }
        }

        return $status;
    }

    /**
     * @param array{code: int, error: string}|array{isHealthy: bool, services: array<non-empty-string, 'healthy'|'unhealthy'>} $data
     * @throws \JsonException
     */
    private function jsonResponse(array $data, int $statusCode = 200, bool $writeBody = true): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        if ($writeBody) {
            $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        }

        return $response;
    }
}
