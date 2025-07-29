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

namespace mteu\Monitoring\Provider;

use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * MiddlewareStatusProvider.
 *
 * Monitors the health of the monitoring middleware itself by making an HTTP request
 * to its own health endpoint. This provides a meta-level health check to ensure
 * the monitoring system is accessible and responding correctly.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 *
 * @internal
 */
final readonly class MiddlewareStatusProvider implements MonitoringProvider
{
    public function __construct(
        private MonitoringConfiguration $monitoringConfiguration,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private SiteFinder $siteFinder,
        private LoggerInterface $logger,
        private HashService $hashService,
    ) {}

    public function getName(): string
    {
        return 'MiddlewareStatus';
    }

    public function getDescription(): string
    {
        return 'This meta-provider monitors the monitoring middleware itself by making HTTP requests to its own health endpoint, providing meta-level health checking to ensure the monitoring system is accessible and responding correctly.';
    }

    public function isActive(): bool
    {
        if ($this->monitoringConfiguration->endpoint === '') {
            return false;
        }

        // Prevent execution during self-requests by checking for the recursion protection header
        if (array_key_exists('HTTP_X_MIDDLEWARE_STATUS_REQUEST', $_SERVER)) {
            return false;
        }

        return $this->monitoringConfiguration->providerConfiguration->isEnabled();
    }

    public function execute(): Result
    {
        try {
            $baseUrl = $this->getBaseUrl();
            $monitoringUrl = rtrim($baseUrl, '/') . $this->monitoringConfiguration->endpoint;

            $request = $this->requestFactory->createRequest('GET', $monitoringUrl);

            // Add recursion protection header to prevent infinite loops
            $request = $request->withHeader('X-MIDDLEWARE-STATUS-REQUEST', '1');

            if (
                $this->monitoringConfiguration->tokenAuthorizerConfiguration->isEnabled()
                && $this->monitoringConfiguration->tokenAuthorizerConfiguration->secret !== ''
            ) {
                $token = $this->generateAuthToken();
                $headerName = $this->monitoringConfiguration->tokenAuthorizerConfiguration->authHeaderName;
                $request = $request->withHeader($headerName, $token);
            }

            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 503) {
                return new MonitoringResult(
                    name: $this->getName(),
                    isHealthy: true,
                    reason: null
                );
            }

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: sprintf('Monitoring endpoint returned HTTP %d', $response->getStatusCode())
            );

        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('MiddlewareStatus monitoring failed with HTTP client exception', [
                'exception' => $e->getMessage(),
                'endpoint' => $this->monitoringConfiguration->endpoint,
            ]);

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: 'HTTP request failed: ' . $e->getMessage()
            );

        } catch (\Exception $e) {
            $this->logger->error('MiddlewareStatus monitoring failed with unexpected exception', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: 'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get the base URL for the current TYPO3 site.
     *
     * Attempts to determine the correct base URL by checking:
     * 1. First available site configuration
     * 2. Fallback to HTTP_HOST from global server variables
     * 3. Ultimate fallback to localhost
     */
    private function getBaseUrl(): string
    {
        try {
            // Try to get base URL from site configuration
            $sites = $this->siteFinder->getAllSites();
            if (count($sites) > 0) {
                $firstSite = reset($sites);
                $base = $firstSite->getBase();

                // Ensure HTTPS for monitoring endpoint access
                if ($base->getScheme() === 'http') {
                    $base = $base->withScheme('https');
                }

                return (string)$base;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not determine base URL from site configuration', [
                'exception' => $e->getMessage(),
            ]);
        }

        // Fallback to HTTP_HOST
        $httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!is_string($httpHost)) {
            $httpHost = 'localhost';
        }
        $scheme = 'https'; // Always use HTTPS for monitoring endpoints

        return sprintf('%s://%s', $scheme, $httpHost);
    }

    private function generateAuthToken(): string
    {
        /** @var non-empty-string $additionalSecret */
        $additionalSecret = $this->monitoringConfiguration->tokenAuthorizerConfiguration->secret;

        return $this->hashService->hmac(
            $this->monitoringConfiguration->endpoint,
            $additionalSecret,
        );
    }
}
