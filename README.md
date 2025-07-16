<div align="center">

[![TYPO3](https://img.shields.io/badge/TYPO3-13.4+-orange.svg)](https://typo3.org)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-monitoring/require/php)](https://packagist.org/packages/mteu/typo3-monitoring)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

[![CGL](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml)
[![Coverage Status](https://coveralls.io/repos/github/mteu/typo3-monitoring/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-monitoring?branch=main)
[![Maintainability](https://api.codeclimate.com/v1/badges/edd606b0c4de053a2762/maintainability)](https://codeclimate.com/github/mteu/typo3-monitoring/maintainability)

<hr />

![](Resources/Public/Icons/Extension.svg)
# TYPO3 Monitoring
</div>

This packages provides the TYPO3 CMS Extension `EXT:monitoring` which extends the CMS with a monitoring system that
gives an insight into the health state of custom TYPO3 components through an API endpoint and a CLI command for
post-deployment checks.

> [!WARNING]
> This package is still in early development and must be considered unfit for production use. Bear with me.
> We'll get there.

## 🚀 Features

- Highly [extensible monitoring system](Documentation/architecture.md) with automatic service discovery for custom
  authorization and monitoring checks.
- Supports caching for expensive monitoring operations
- Delivers health reports in three ways:
  - **JSON response**: Returns structured responses for the overall health status
  - **CLI command**: Command-line interface for running monitoring checks
  - **Backend Module**: TYPO3 backend module


## 🔥 Quick Start

### Installation

Install via Composer:

```bash
composer require mteu/typo3-monitoring
```

### Configuration

1. Configure the extension in the TYPO3 backend:
   - Go to **Admin Tools → Settings → Extension Configuration**
   - Select `monitoring`
   - Set the monitoring endpoint path (default: `/monitor/health`)
   - Configure a secret for HMAC authentication

2. Or better yet configure the settings programmatically:
    ```php
    # config/system/settings.php

    <?php

    return [
        // ..
        'EXTENSIONS' => [
            'monitoring' => [
                'api' => [
                    'endpoint' => '/monitor/health',
                    'secret' => 'foobarsecret',
                ],
            ],
        ],
        // ..
   ];
    ```

3. Access your monitoring endpoint while authenticated as backend user with the role of Admin or System Maintainer:
   ```
   https://<your-site>/monitor/health
   ```

### Authentication

This extension ships two authentication methods natively:

#### Admin User Authentication
Access the endpoint while logged in as a TYPO3 backend administrator.

#### Token-based Authentication
Add the `X-TYPO3-MONITORING-AUTH` header with an HMAC signature:

```bash
curl -s -H "X-TYPO3-MONITORING-AUTH: <auth-token>" \
     https://<your-site>/monitor/health | jq '.'
```

## 📝 Response Format

The monitoring endpoint returns JSON with the following structure:

```json
{
  "isHealthy": true,
  "services": {
    "service_one": "healthy",
    "service_two": "healthy",
    "service_three": "healthy"
  }
}
```

- `isHealthy`: Overall health status (boolean)
- `services`: Object with individual service statuses ("healthy" or "unhealthy")

HTTP status codes:
- `200` All services healthy
- `401` Unauthorized access
- `403` Unsupported protocol
- `503` One or more services unhealthy

## 🛠️ Development

### Creating Custom Providers

Implement the `MonitoringProvider` interface:

```php
<?php

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monitoring.provider')]
final class MyMonitoringProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'MyService';
    }

    public function getDescription(): string
    {
        return 'Monitors my custom service';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function isHealthy(): bool
    {
        return $this->execute()->isHealthy();
    }

    public function execute(): MonitoringResult
    {
        // Your monitoring logic here
        return new MonitoringResult(
            $this->getName(),
            true,
        );
    }
}
```

### Creating Custom Authorizers

Implement the `Authorizer` interface:

```php
<?php

use mteu\Monitoring\Authorization\Authorizer;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monitoring.authorizer')]
final class MyAuthorizer implements Authorizer
{
    public function isAuthorized(ServerRequestInterface $request): bool
    {
        // Your authorization logic here
        return true;
    }

    public function getPriority(): int
    {
        return 100; // Higher priority = checked first
    }
}
```

## 🤝 Contributing
Contributions are very welcome! Please have a look at the [Contribution Guide](CONTRIBUTING.md). It lays out the
workflow of submitting new features or bugfixes.

## 📙 Documentation
Please have a look at the official extension [documentation](Documentation/index.md). It provides a detailed look into
the possibilities you have in extending and customizing this extension for your specific TYPO3 components.

## 🔒 Security
Please refer to our [security policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is private project.

## 💛 Acknowledgements
This extension is inspired by [`cpsit/monitoring`](https://github.com/CPS-IT/monitoring) and its generic approach to offer an extensible provider
interface. I've transformed and extended the underlying concept into a TYPO3 specific implementation.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.md) license.

## 💬 Support
For issues and feature requests, please use the [GitHub issue tracker](https://github.com/mteu/typo3-monitoring/issues).
