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

namespace mteu\Monitoring\Provider\Solr;

use mteu\Monitoring\Configuration\Provider\SolrProviderConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Probes Solr over HTTP using TYPO3's request factory.
 *
 * A 200 response counts as reachable; any other status, or a transport-level
 * failure, counts as unreachable and is logged rather than thrown.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(SolrClient::class)]
final readonly class HttpSolrClient implements SolrClient
{
    public function __construct(
        private SolrProviderConfiguration $configuration,
        private RequestFactory $requestFactory,
        private LoggerInterface $logger,
    ) {}

    public function isReachable(string $url): bool
    {
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => $this->configuration->timeout,
                'connect_timeout' => $this->configuration->timeout,
                // Treat 4xx/5xx as a regular response so the status code drives the result.
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Solr probe failed.', [
                'url' => $url,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        return $response->getStatusCode() === 200;
    }
}
