# Documentation

`EXT:monitoring` allows external monitoring systems to check the health of your TYPO3 instance through a configurable
HTTP endpoint. The extension follows a provider-based architecture that makes it easy to extend with custom monitoring
checks.

The extension defines these main interfaces:

- `MonitoringProvider`: For implementing monitoring checks, see [Provider Development](Providers.md).
- `Authorizer`: For implementing authorization strategies, see [Authorization](Authorization.md).
- `Reporter`: For pushing status to external channels, see [Reporter Development](Reporters.md).

All interfaces support automatic service discovery through PHP attributes.

## Further reading

1. [Configuration](Configuration.md)
2. [Provider Development](Providers.md)
3. [Authorization](Authorization.md)
4. [Reporter Development](Reporters.md)
5. [API Reference](Api.md)
6. [Command-Line Interface](Cli.md)
