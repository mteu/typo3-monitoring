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
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
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
            $results = $this->collectResults();
            $overallStatus = $this->overallStatus($results);

            $responseArray = [
                'status' => $overallStatus->value,
            ];

            if ($this->monitoringConfiguration->includeServicesHealth) {
                $responseArray['services'] = $this->summarizeResults($results);
            }

            return $this->jsonResponse(
                $responseArray,
                $overallStatus === Status::Unhealthy ? 503 : 200,
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
     * @return array<non-empty-string, Result>
     */
    private function collectResults(): array
    {
        $results = [];

        foreach ($this->monitoringProviders as $provider) {
            if ($provider->isEnabled() && $provider->isActive()) {
                $results[$provider->getName()] = $this->executionHandler->executeProvider($provider);
            }
        }

        return $results;
    }

    /**
     * @param array<non-empty-string, Result> $results
     */
    private function overallStatus(array $results): Status
    {
        return Status::worst(
            ...array_map(static fn(Result $result): Status => $result->getStatus(), array_values($results)),
        );
    }

    /**
     * Reduces each provider result to its status, recursively listing the
     * status of every sub-result. Reasons and deeper detail stay in the
     * backend module.
     *
     * @param array<non-empty-string, Result> $results
     * @return array<non-empty-string, array{status: string, subResults?: array<string, mixed>}>
     */
    private function summarizeResults(array $results): array
    {
        $summary = [];

        foreach ($results as $name => $result) {
            $summary[$name] = $this->summarizeResult($result);
        }

        return $summary;
    }

    /**
     * @return array{status: string, subResults?: array<string, mixed>}
     */
    private function summarizeResult(Result $result): array
    {
        $entry = ['status' => $result->getStatus()->value];

        $subResults = $result->getSubResults();
        if ($subResults !== []) {
            $subStatus = [];
            foreach ($subResults as $subResult) {
                $subStatus[$subResult->getName()] = $this->summarizeResult($subResult);
            }
            $entry['subResults'] = $subStatus;
        }

        return $entry;
    }

    /**
     * @param array{code: int, error: string}|array{
     *     status: string,
     *     services?: array<non-empty-string, array{status: string, subResults?: array<string, mixed>}>
     * } $data
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
