<div align="center">

[![CGL](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-monitoring/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-monitoring/actions/workflows/tests.yaml)
[![Coverage](https://coveralls.io/repos/github/mteu/typo3-monitoring/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-monitoring?branch=main)


<img src="Resources/Public/Icons/Extension.svg" width="64" height="64" alt="Extension Icon">

# TYPO3 Monitoring


![TYPO3 Support](https://badgen.net/badge/TYPO3/v13/FF8700?icon=typo3)
![TYPO3 Support](https://badgen.net/badge/TYPO3/v14/FF8700?icon=typo3)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-monitoring/require/php)](https://packagist.org/packages/mteu/typo3-monitoring)
![Stability](https://typo3-badges.dev/badge/monitoring/stability/badgen.svg)
![Latest version](https://typo3-badges.dev/badge/monitoring/version/badgen.svg)
![Total downloads](https://typo3-badges.dev/badge/monitoring/downloads/badgen.svg)
<!-- Generated with 🧡 at typo3-badges.dev -->

</div>

This package provides the TYPO3 CMS extension `EXT:monitoring`, which equips TYPO3 with a highly extensible monitoring system
for tracking the health state of custom components. It delivers health reports via an API endpoint, a CLI command, and a
backend dashboard, with optional push notifications to external channels.

## 🚀 Features

- [Extensible monitoring system](Documentation/README.md) for custom authorization, monitoring checks, and push notifications.
- Delivers health reports in several ways:
    - Structured JSON responses for the overall health status
    - Command-line interface for running monitoring checks
    - Backend Dashboard
    - Optional [Push notification](Documentation/Reporters.md) to external channels (e.g. the built-in [EmailReporter](Documentation/Reporter/EmailReporter.md))
- Built-in providers this package ships:
    - [Scheduler Provider](Documentation/Providers/Scheduler.md) (Monitors TYPO3 Scheduler tasks.)
- Caching for expensive monitoring operations

## 🔥 Quick Start

### Installation

```bash
composer require mteu/typo3-monitoring
```

### Configuration

```php
# config/system/settings.php
return [
    'EXTENSIONS' => [
        'monitoring' => [
            'api' => [
                'endpoint' => '/monitor/health',
                'enforceHttps' => true,
            ],
            'authorizer' => [
                'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                    'enabled' => true,
                    'secret' => 'your-secure-secret',
                    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                ],
            ],
        ],
    ],
];
```

See [Documentation/Configuration.md](Documentation/Configuration.md) for all available settings.

### Endpoint

```
GET https://<your-site>/monitor/health
```

Access requires authentication — either a TYPO3 backend administrator session or a token header. See [Documentation/Authorization.md](Documentation/Authorization.md) for details, including the security properties of the token.

The endpoint returns a JSON health report. See the [API Reference](Documentation/Api.md) for the full response schema and HTTP status codes.

## 🧑‍💻 Development
For guides on creating custom providers and authorizers, see the [Documentation](Documentation/README.md).

## 📙 Documentation
Please have a look at the extension [documentation](Documentation/README.md). It provides a detailed look into
the possibilities you have in extending and customizing this extension for your specific TYPO3 components.

## 🤝 Contributing
Contributions are very welcome! Please have a look at the [Contribution Guide](CONTRIBUTING.md). It lays out the
workflow of submitting new features or bugfixes.

## 🔒 Security
Please refer to the [Security Policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is a private project.

## 💛 Acknowledgements
This extension is inspired by [`cpsit/monitoring`](https://github.com/CPS-IT/monitoring) and its generic approach to offer an extensible provider
interface. I've transformed and extended the underlying concept into a TYPO3 specific implementation.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.txt) license.

## 💬 Support
For issues and feature requests, please use the [GitHub issue tracker](https://github.com/mteu/typo3-monitoring/issues).
