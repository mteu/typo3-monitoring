<div align="center">

[![CGL](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml)
[![Coverage](https://coveralls.io/repos/github/mteu/typo3-monitoring/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-monitoring?branch=main)
[![Maintainability](https://qlty.sh/gh/mteu/projects/typo3-monitoring/maintainability.svg)](https://qlty.sh/gh/mteu/projects/typo3-monitoring)

<img src="Resources/Public/Icons/Extension.svg" width="64" height="64" alt="Extension Icon">

# TYPO3 Monitoring

![TYPO3 versions](https://typo3-badges.dev/badge/monitoring/typo3/shields.svg)
![Latest version](https://typo3-badges.dev/badge/monitoring/version/shields.svg)
![Stability](https://typo3-badges.dev/badge/monitoring/stability/shields.svg)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-monitoring/require/php)](https://packagist.org/packages/mteu/typo3-monitoring)

</div>

This packages provides the TYPO3 CMS Extension `EXT:monitoring` which extends the CMS with a monitoring system that
gives an insight into the health state of custom TYPO3 components through an API endpoint and a CLI command, e.g. for
post-deployment checks.

> [!WARNING]
> This package is still in early development and must be considered unfit for production use. Bear with me.
> We'll get there.

## 🚀 Features

- [Extensible monitoring system](Documentation/architecture.md) with automatic service discovery (using DI) for custom
  authorization and monitoring checks.
- Built-in **SelfCareProvider** for meta-level monitoring of the monitoring system itself
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
   - Configure authorizer settings for token-based and admin user authentication

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
                ],
                'authorizer' => [
                    'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                        'enabled' => true,
                        'secret' => 'your-secure-secret',
                        'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                        'priority' => 10,
                    ],
                    'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
                        'enabled' => true,
                        'priority' => -10,
                    ],
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
Add the configured auth header (default: `X-TYPO3-MONITORING-AUTH`) with an HMAC signature:

```bash
curl -s -H "X-TYPO3-MONITORING-AUTH: <auth-token>" \
     https://<your-site>/monitor/health | jq '.'
```

**Token Generation:**
The HMAC token is generated using TYPO3's HashService with the endpoint path and your configured secret:

```php
$hashService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\HashService::class);
$token = $hashService->hmac('/monitor/health', 'your-secure-secret');
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

## 🧑‍💻 Development

### Creating Custom Providers

Implement the `MonitoringProvider` interface:

```php
<?php

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(tag: 'monitoring.provider')]
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

#[AutoconfigureTag(tag: 'monitoring.authorizer')]
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
Please have a look at the official extension [documentation](Documentation/README.md). It provides a detailed look into
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
