<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-monitoring".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace mteu\Monitoring\Middleware;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Configuration\Extension;
use mteu\Monitoring\Provider\MonitoringProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * MonitoringMiddleware.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class MonitoringMiddleware implements MiddlewareInterface
{
    private string $endpoint;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        private Extension $extensionConfiguration,

        /** @var iterable<MonitoringProvider> $monitoringProviders */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $monitoringProviders,

        /** @var iterable<Authorizer> $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
    ) {
        $this->endpoint = $this->extensionConfiguration->getEndpointFromConfiguration();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->endpoint === '') {
            return $handler->handle($request);
        }

        if (!$this->isValid($request)) {
            return $handler->handle($request);
        }

        if (!$this->isHttps($request)) {
            return new JsonResponse(
                [
                    'code' => 403,
                    'error' => 'unsupported-protocol',
                ],
                403,
            );
        }

        if (!$this->isAuthorized($request)) {
            return new JsonResponse(
                [
                    'code' => 401,
                    'error' => 'unauthorized',
                ],
                401,
            );
        }

        return new JsonResponse(
            [
                'isHealthy' => $this->isHealthy(),
                'services' => array_map(
                    static fn(bool $serviceStatus): string => $serviceStatus ? 'healthy' : 'unhealthy',
                    $this->getHealthStatus()
                ),
            ],
            $this->isHealthy() ? 200 : 503,
        );
    }

    private function isValid(ServerRequestInterface $request): bool
    {
        return rtrim($request->getUri()->getPath(), '/') === $this->endpoint;
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        return $request->getUri()->getScheme() === 'https';
    }

    /**
     * Checks if request is authorized by any of the registered authorizers. First one take the win.
     */
    private function isAuthorized(ServerRequestInterface $request): bool
    {
        // array_any cannot act on iterable here
        foreach ($this->authorizers as $authorizer) {
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
                $status[$provider->getName()] = $provider->isHealthy();
            }
        }

        return $status;
    }

    private function isHealthy(): bool
    {
        return !in_array(false, $this->getHealthStatus(), true);
    }
}
