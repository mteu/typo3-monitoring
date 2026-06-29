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

namespace mteu\Monitoring\Configuration\Provider;

use mteu\Monitoring\Result\Status;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * SolrProviderConfiguration.
 *
 * The Solr connections themselves (host, port, core, …) are not configured here:
 * they are read from the TYPO3 site configuration, the very same source EXT:solr
 * uses at runtime.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class SolrProviderConfiguration implements ProviderConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SolrProvider.enabled')]
        private bool $enabled = false,

        /**
         * Maximum time (in seconds) to wait for a host or a core to respond
         * before it is treated as unreachable.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SolrProvider.timeout')]
        public int $timeout = 5,

        /**
         * Severity reported when the index queue contains indexing errors.
         * Defaults to degraded: indexing errors are attention-worthy but, unlike
         * an unreachable host or core, do not take the search backend offline.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\SolrProvider.indexingErrorSeverity')]
        public Status $indexingErrorSeverity = Status::Degraded,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
