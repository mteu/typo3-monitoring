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

/**
 * A single Solr read connection derived from the TYPO3 site configuration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrConnection
{
    /**
     * @param non-empty-string $label Human-readable origin, e.g. "main / English (core_en)".
     * @param non-empty-string $rootUri Base URI of the Solr node without a trailing slash and without the core segment, e.g. "http://localhost:8983/solr".
     * @param string $core Core name, e.g. "core_en". May be empty when none is configured.
     */
    public function __construct(
        public string $label,
        public string $rootUri,
        public string $core,
    ) {}

    /**
     * URI whose reachability indicates whether the Solr node itself is up.
     */
    public function hostProbeUri(): string
    {
        return $this->rootUri . '/admin/info/system';
    }

    /**
     * URI whose reachability indicates whether the core answers, or null when
     * no core is configured for this connection.
     */
    public function coreProbeUri(): ?string
    {
        if ($this->core === '') {
            return null;
        }

        return $this->rootUri . '/' . rawurlencode($this->core) . '/admin/ping';
    }
}
