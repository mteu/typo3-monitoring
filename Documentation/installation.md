# Installation Guide

## Requirements

- **PHP**: 8.3+
- **TYPO3**: 13.4+
- **HTTPS**: Required for production use

## Installation

```bash
composer require mteu/typo3-monitoring
```

## Configuration

Configure the extension settings:

- **Endpoint**: URL path for monitoring (default: `/monitor/health`)
- **Secret**: Secure secret for HMAC authentication

Set via Extension Configuration or programmatically:

```php
# config/system/settings.php
return [
    'EXTENSIONS' => [
        'monitoring' => [
            'api' => [
                'endpoint' => '/monitor/health',
                'secret' => 'your-secure-secret',
            ],
        ],
    ],
];
```

## Verification

Test the endpoint:

```bash
curl -H "X-TYPO3-MONITORING-AUTH: your-hmac-token" \
     https://yoursite.com/monitor/health
```

Expected response:
```json
{
  "isHealthy": true,
  "services": {}
}
```

## Next Steps

1. [Configure authentication](authorization.md)
2. [Create custom providers](providers.md)
3. [Test the API](api.md)
