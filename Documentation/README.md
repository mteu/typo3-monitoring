# Documentation

`EXT:monitoring` allows external monitoring systems to check the health of your TYPO3 instance through a configurable
HTTP endpoint. The extension follows a provider-based architecture that makes it easy to extend with custom monitoring
checks.

The extension defines two main interfaces:

- `MonitoringProvider`: For implementing monitoring checks, see [Provider Development](providers.md).
- `Authorizer`: For implementing authorization strategies, see [Authorization](authorization.md).

Both interfaces support automatic service discovery through PHP attributes.

## Further reading

1. [Configuration](configuration.md)
2. [Provider Development](providers.md)
3. [Authorization](authorization.md)
4. [API Reference](api.md)
5. [Command-Line Interface](command-line.md)
