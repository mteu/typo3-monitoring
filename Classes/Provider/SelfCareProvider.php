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
 * SelfCareProvider.
 *
 * Monitors the health of the monitoring middleware itself by making an HTTP request
 * to its own health endpoint. This provides a meta-level health check to ensure
 * the monitoring system is accessible and responding correctly.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SelfCareProvider implements MonitoringProvider
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
        return 'Endpoint Self Check';
    }

    public function getDescription(): string
    {
        return 'The self-care provider monitors the monitoring middleware itself by making HTTP requests to its own health endpoint, providing meta-level health checking to ensure the monitoring system is accessible and responding correctly.';
    }

    public function isActive(): bool
    {
        // Only active if monitoring endpoint is configured and provider is enabled
        if ($this->monitoringConfiguration->endpoint === '') {
            return false;
        }

        // Prevent execution during self-requests by checking for the recursion protection header
        if (array_key_exists('HTTP_X_SELFCARE_REQUEST', $_SERVER)) {
            return false;
        }

        return $this->monitoringConfiguration->selfCareProviderConfiguration->isEnabled();
    }

    public function execute(): Result
    {
        try {
            $baseUrl = $this->getBaseUrl();
            $monitoringUrl = rtrim($baseUrl, '/') . $this->monitoringConfiguration->endpoint;

            // Create request with authorization header if token is configured
            $request = $this->requestFactory->createRequest('GET', $monitoringUrl);

            // Add recursion protection header to prevent infinite loops
            $request = $request->withHeader('X-SELFCARE-REQUEST', '1');

            // Add authorization header if token auth is enabled and configured
            if (
                $this->monitoringConfiguration->tokenAuthorizerConfiguration->isEnabled()
                && $this->monitoringConfiguration->tokenAuthorizerConfiguration->secret !== ''
            ) {
                $token = $this->generateAuthToken();
                $headerName = $this->monitoringConfiguration->tokenAuthorizerConfiguration->authHeaderName;
                $request = $request->withHeader($headerName, $token);
            }

            // Make the request with a reasonable timeout
            $response = $this->httpClient->sendRequest($request);

            // Check if response indicates healthy status
            if ($response->getStatusCode() === 200) {
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($data) && array_key_exists('isHealthy', $data)) {
                    $isHealthy = (bool)$data['isHealthy'];
                    return new MonitoringResult(
                        name: $this->getName(),
                        isHealthy: $isHealthy,
                        reason: $isHealthy ? null : 'Monitoring endpoint reported unhealthy state'
                    );
                }

                return new MonitoringResult(
                    name: $this->getName(),
                    isHealthy: false,
                    reason: 'Invalid response format from monitoring endpoint'
                );
            }

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: sprintf('Monitoring endpoint returned HTTP %d', $response->getStatusCode())
            );

        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('SelfCare monitoring failed with HTTP client exception', [
                'exception' => $e->getMessage(),
                'endpoint' => $this->monitoringConfiguration->endpoint,
            ]);

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: 'HTTP request failed: ' . $e->getMessage()
            );

        } catch (\JsonException) {
            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: 'Invalid JSON response from monitoring endpoint'
            );

        } catch (\Exception $e) {
            $this->logger->error('SelfCare monitoring failed with unexpected exception', [
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
