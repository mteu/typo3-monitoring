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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Reads Solr read connections from the TYPO3 site configuration.
 *
 * EXT:solr stores one read connection per site language using the `solr_*_read`
 * keys (scheme, host, port, path, core). Sourcing the probe targets from there
 * means the monitoring check always reflects the connections actually used in
 * production, with no separate list to keep in sync.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(SolrConnectionProvider::class)]
final readonly class SiteConfigurationSolrConnectionProvider implements SolrConnectionProvider
{
    public function __construct(
        private SiteFinder $siteFinder,
        private LoggerInterface $logger,
    ) {}

    public function getConnections(): array
    {
        $connections = [];

        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $language) {
                $connection = $this->buildConnection($site->getIdentifier(), $language);

                if ($connection !== null) {
                    $connections[] = $connection;
                }
            }
        }

        return $connections;
    }

    private function buildConnection(string $siteIdentifier, SiteLanguage $language): ?SolrConnection
    {
        $configuration = $language->toArray();

        if (!filter_var($configuration['solr_enabled_read'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $host = trim($this->readString($configuration, 'solr_host_read', ''));

        if ($host === '') {
            $this->logger->warning('Skipping Solr connection with empty host.', [
                'site' => $siteIdentifier,
                'language' => $language->getLanguageId(),
            ]);

            return null;
        }

        $scheme = trim($this->readString($configuration, 'solr_scheme_read', 'http'));
        $scheme = $scheme !== '' ? $scheme : 'http';
        $port = $this->readInt($configuration, 'solr_port_read', 8983);
        $path = trim($this->readString($configuration, 'solr_path_read', '/solr/'), '/');
        $core = trim($this->readString($configuration, 'solr_core_read', ''));

        $rootUri = sprintf(
            '%s://%s%s%s',
            $scheme,
            $host,
            $port > 0 ? ':' . $port : '',
            $path !== '' ? '/' . $path : '',
        );

        return new SolrConnection(
            sprintf(
                '%s / %s%s',
                $siteIdentifier,
                $language->getTitle() !== '' ? $language->getTitle() : 'language ' . $language->getLanguageId(),
                $core !== '' ? ' (' . $core . ')' : '',
            ),
            $rootUri,
            $core,
        );
    }

    /**
     * @param array<array-key, mixed> $configuration
     */
    private function readString(array $configuration, string $key, string $default): string
    {
        $value = $configuration[$key] ?? null;

        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @param array<array-key, mixed> $configuration
     */
    private function readInt(array $configuration, string $key, int $default): int
    {
        $value = $configuration[$key] ?? null;

        return is_numeric($value) ? (int)$value : $default;
    }
}
