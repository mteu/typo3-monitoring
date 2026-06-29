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
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * SolrProvider.
 *
 * Monitors the Apache Solr connections configured for EXT:solr in the TYPO3 site
 * configuration. It reports three concerns:
 *
 *   1. host: Is each configured Solr node reachable at all?
 *   2. cores: Does every configured core answer a ping?
 *   3. indexing errors: Did the index queue record indexing failures?
 *
 * The provider is disabled by default and only active when EXT:solr is loaded.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrProvider implements MonitoringProvider
{
    /**
     * Number of failing item descriptions included in a reason to keep the
     * output readable on installations with a large index queue.
     */
    private const int SAMPLE_SIZE = 5;

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        private SolrProviderConfiguration $configuration,
        private SolrConnectionProvider $connectionProvider,
        private SolrClient $client,
        private IndexQueueRepository $indexQueue,
        private LoggerInterface $logger,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function getName(): string
    {
        return 'Solr';
    }

    public function getDescription(): string
    {
        return 'Monitors the Apache Solr connections configured for EXT:solr in the site '
            . 'configuration: whether each Solr node is reachable, whether every configured '
            . 'core answers a ping, and whether the index queue recorded indexing errors. '
            . 'Inactive when the solr extension is not installed.';
    }

    public function isEnabled(): bool
    {
        return $this->configuration->isEnabled();
    }

    public function isActive(): bool
    {
        return ExtensionManagementUtility::isLoaded('solr');
    }

    public function execute(): Result
    {
        $result = new MonitoringResult($this->getName(), Status::Healthy);

        $connections = $this->connectionProvider->getConnections();

        if ($connections === []) {
            $result->addSubResult(
                new MonitoringResult('Connections', Status::Healthy, $this->translate('provider.solr.connections.none')),
            );
        } else {
            $hostReachability = $this->probeHosts($connections);

            $result->addSubResult($this->buildHostResult($hostReachability));
            $result->addSubResult($this->buildCoreResult($connections, $hostReachability));
        }

        $result->addSubResult($this->checkIndexingErrors());

        if (!$result->isHealthy()) {
            $result->setReason($this->translate('provider.solr.unhealthy'));
        }

        return $result;
    }

    /**
     * Probes each distinct Solr node once and returns a rootUri => reachable map.
     *
     * @param list<SolrConnection> $connections
     * @return array<string, bool>
     */
    private function probeHosts(array $connections): array
    {
        $reachability = [];

        foreach ($connections as $connection) {
            if (!array_key_exists($connection->rootUri, $reachability)) {
                $reachability[$connection->rootUri] = $this->client->isReachable($connection->hostProbeUri());
            }
        }

        return $reachability;
    }

    /**
     * @param array<string, bool> $hostReachability
     */
    private function buildHostResult(array $hostReachability): Result
    {
        $result = new MonitoringResult('Host', Status::Healthy);

        foreach ($hostReachability as $rootUri => $reachable) {
            $result->addSubResult(
                new MonitoringResult(
                    $rootUri,
                    $reachable ? Status::Healthy : Status::Unhealthy,
                    $this->translate(
                        $reachable ? 'provider.solr.host.reachable' : 'provider.solr.host.unreachable',
                        $rootUri,
                    ),
                ),
            );
        }

        return $result;
    }

    /**
     * @param list<SolrConnection> $connections
     * @param array<string, bool> $hostReachability
     */
    private function buildCoreResult(array $connections, array $hostReachability): Result
    {
        $result = new MonitoringResult('Cores', Status::Healthy);
        $checked = false;

        foreach ($connections as $connection) {
            $coreProbeUri = $connection->coreProbeUri();

            // No core configured, or the node is already down — the host
            // sub-result covers that outage, so do not double-report it here.
            if ($coreProbeUri === null || ($hostReachability[$connection->rootUri] ?? false) === false) {
                continue;
            }

            $checked = true;
            $reachable = $this->client->isReachable($coreProbeUri);

            $result->addSubResult(
                new MonitoringResult(
                    $connection->label,
                    $reachable ? Status::Healthy : Status::Unhealthy,
                    $this->translate(
                        $reachable ? 'provider.solr.core.reachable' : 'provider.solr.core.unreachable',
                        $connection->core,
                    ),
                ),
            );
        }

        if (!$checked) {
            $result->setReason($this->translate('provider.solr.cores.none'));
        }

        return $result;
    }

    private function checkIndexingErrors(): Result
    {
        try {
            $count = $this->indexQueue->countIndexingErrors();
        } catch (\Throwable $exception) {
            $this->logger->error('Solr index queue query failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return new MonitoringResult(
                'Indexing Errors',
                Status::Unhealthy,
                $this->translate('provider.solr.indexing.queryFailure', $exception->getMessage()),
            );
        }

        if ($count === 0) {
            return new MonitoringResult('Indexing Errors', Status::Healthy, $this->translate('provider.solr.indexing.none'));
        }

        return new MonitoringResult(
            'Indexing Errors',
            $this->configuration->indexingErrorSeverity,
            $this->translate(
                $count > 1 ? 'provider.solr.indexing.plural' : 'provider.solr.indexing.singular',
                $count,
                $this->renderSampleList($this->safeIndexingErrorSample()),
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function safeIndexingErrorSample(): array
    {
        try {
            return $this->indexQueue->getIndexingErrorSample(self::SAMPLE_SIZE);
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not fetch Solr indexing error sample.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<string> $sample
     */
    private function renderSampleList(array $sample): string
    {
        if ($sample === []) {
            return '.';
        }

        $items = array_map(
            static fn(string $item): string => '<li>' . htmlspecialchars($item) . '</li>',
            $sample,
        );

        return ':<ul>' . implode('', $items) . '</ul>';
    }

    private function translate(string $key, int|float|string ...$args): string
    {
        $label = $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':' . $key);

        return $args === [] ? $label : sprintf($label, ...$args);
    }

    private function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
