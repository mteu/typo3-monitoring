<div align="center">

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)]
(LICENSE)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4+-orange.svg)]
(https://typo3.org)
[![PHP](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)

# TYPO3 Monitoring

![](Resources/Public/Icons/Extension.svg)


</div>

This TYPO3 CMS extension provides . External monitoring systems can check the
health state of custom TYPO3 components through a secure JSON API and a CLI command
for post-deployment checks.

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
   - Select `typo3_monitoring`
   - Set the monitoring endpoint path (default: `/monitor/health`)
   - Configure a secret for HMAC authentication
 or better yet programmatically:
    ```php
    # config/system/settings.php

    <?php

    return [
        // ..
        'EXTENSIONS' => [
            'typo3_monitoring' => [
                'monitoring' => [
                    'endpoint' => '/monitor/health',
                    'secret' => 'foobarsecret',
                ],
            ],
        ],
        // ..
   ];
    ```

2. Access your monitoring endpoint while authenticated as backend user:
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

## 📝 Requirements

- PHP 8.4+
- TYPO3 13.4+
- HTTPS enabled (for production use)

## 🤝 Contributing

Contributions! Yes, please!

1. Fork the repository
2. Create a feature branch
3. Follow the existing code style
4. Add tests for new functionality
5. Submit a pull request

## 📙 Documentation
Please have a look at the official extension [documentation](Documentation/index.md).

## 🔒 Security
Please refer to our [security policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is private project.

## 💛 Acknowledgements
This extension is inspired by [`cpsit/monitoring`](https://github.com/CPS-IT/monitoring)
and its generic approach to offer an extensible .
I've transformed the underlying concept and transformed in to a TYPO3 specific
implementation.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.md) license.

## 💬 Support
For issues and feature requests, please use the
[GitHub issue tracker](https://github.com/mteu/typo3-monitoring/issues).
