# Solr Provider

Monitors the [Apache Solr](https://github.com/TYPO3-Solr/ext-solr) connections
configured for your TYPO3 instance and reports their health on the monitoring
endpoint. A search backend can fail in several independent ways — a Solr node
can be down, a single core can be missing, or indexing can quietly start failing
while queries still work — so this provider reports these as distinct concerns.

The provider is **disabled by default** and **inactive** unless the
`apache-solr-for-typo3/solr` extension is installed *and* the provider is enabled
in the configuration.

## No connection configuration to maintain

The provider does **not** ask you to re-enter the Solr host or cores. It reads
the read connections straight from the **TYPO3 site configuration** — the same
`solr_*_read` settings (scheme, host, port, path, core) that EXT:solr itself uses
at runtime, configured per site language in the *Sites* backend module or in
`config/sites/<site>/config.yaml`.

Because the probe and EXT:solr read from the same source, the health check can
never drift away from the connections actually in use in production. Add a
language, point a site at a different Solr node, disable a read connection — the
monitoring check follows automatically.

## What it checks

`execute()` returns one aggregate result (`Solr`) composed of up to three
sub-results. The aggregate is healthy only when all checks pass.

| Sub-result        | Healthy when …                                                                          | Severity when failing |
|-------------------|-----------------------------------------------------------------------------------------|-----------------------|
| `Host`            | Every distinct Solr node answers its system-info request (one sub-result per node).     | `unhealthy`           |
| `Cores`           | Every configured core on a reachable node answers an admin ping (one sub-result/core).  | `unhealthy`           |
| `Indexing Errors` | No item in the EXT:solr index queue (`tx_solr_indexqueue_item`) recorded an error.       | `degraded` (default)  |

> [!NOTE]
> - Nodes are de-duplicated, so several cores sharing a node are probed against the same host once.
> - When a node is unreachable its cores are not probed separately — the `Host` sub-result already reports that outage.
> - The indexing-errors reason lists up to 5 affected items (`item_type:item_uid`).
> - Reachability is probed over HTTP via TYPO3's request factory; a connection failure, timeout, or non-200 status all count as unreachable.

## Configuration

Only the toggle, the probe timeout and the indexing-error severity are
configured here — the connections come from the site configuration. Set via
Extension Configuration (`monitoring`) or `config/system/settings.php`:

```php
return [
    'EXTENSIONS' => [
        'monitoring' => [
            'provider' => [
                'mteu\\Monitoring\\Provider\\SolrProvider' => [
                    'enabled' => true,
                    'timeout' => 5,
                    'indexingErrorSeverity' => 'degraded',
                ],
            ],
        ],
    ],
];
```

| Setting                 | Default    | Description                                                                                                                                                             |
|-------------------------|------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `enabled`               | `false`    | Enables the provider. When `false` the provider reports as inactive.                                                                                                   |
| `timeout`               | `5`        | Maximum time (seconds) to wait for a host or a core before it is treated as unreachable.                                                                               |
| `indexingErrorSeverity` | `degraded` | Status reported when the index queue contains indexing errors: `degraded` (visible on the endpoint at HTTP 200, no notification) or `unhealthy` (an outage, HTTP 503). |

Indexing errors default to `degraded` because the search backend keeps serving
queries while indexing is broken — it is attention-worthy but not, on its own, an
outage. Pair it with `reportDispatcher.notifyFrom = degraded` (see
[Reporters](../Reporters.md)) if you want degradation to page.

## Example output

```json
{
  "status": "unhealthy",
  "services": {
    "Solr": {
      "status": "unhealthy",
      "subResults": {
        "Host": {
          "status": "healthy",
          "subResults": {
            "http://localhost:8983/solr": { "status": "healthy" }
          }
        },
        "Cores": {
          "status": "unhealthy",
          "subResults": {
            "main / English (core_en)": { "status": "healthy" },
            "main / German (core_de)": { "status": "unhealthy" }
          }
        },
        "Indexing Errors": { "status": "degraded" }
      }
    }
  }
}
```

> [!NOTE]
> The full breakdown (reasons and the affected index queue items) is available in the TYPO3 backend module.
