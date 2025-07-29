# 📋 Documentation

1. [Configuration](configuration.md)
2. [Provider Development](providers.md)
3. [Authorization](authorization.md)
4. [API Reference](api.md)
5. [Command-Line Interface](command-line.md)
6. [Architecture](architecture.md)

## 🔍 Overview

`EXT:monitoring` allows external monitoring systems to check
the health of your TYPO3 instance through a configurable HTTP endpoint. The
extension follows a provider-based architecture that makes it easy to extend
with custom monitoring checks.

### Key Features

- **Provider-based Architecture**: Extensible system with automatic service
  discovery
- **Multiple Authorization Strategies**: Token-based HMAC and admin user
  authentication plus open to custom implementations
- **Secure by Default**: HTTPS-only access with multiple security layers
- **Caching Support**: Optional caching for expensive monitoring operations
- **Backend Integration**: TYPO3 backend module for management
- **CLI Support**: Command-line interface for monitoring checks
- **JSON API**: RESTful responses with summary status information

### Core Interfaces

The extension defines two main interfaces:

- `MonitoringProvider`: For implementing monitoring checks
- `Authorizer`: For implementing authorization strategies

Both interfaces support automatic service discovery through PHP attributes.

## 🚀 Getting Started

1. Install: `composer require mteu/typo3-monitoring`
2. [Configure the monitoring endpoint](configuration.md)
3. [Set up authentication](authorization.md)
4. [Create custom providers](providers.md)
5. [Test your monitoring endpoint](api.md)

## 🔒 Security Considerations

The monitoring endpoint is designed with security in mind:

- **HTTPS Only**: All requests must use HTTPS
- **Authentication Required**: Multiple authentication methods available
- **Rate Limiting**: Can be combined with external rate limiting
- **No Sensitive Data**: Monitoring responses contain no sensitive information
- **Configurable Endpoints**: Endpoint paths can be customized
