# Configuration Guide

This guide covers the configuration options for the TYPO3 Monitoring Extension.

## ⚙️ Extension Configuration

The extension is configured through TYPO3's Extension Configuration system.

### Accessing Configuration

1. Navigate to **Admin Tools → Settings → Extension Configuration**
2. Select `typo3_monitoring` from the list
3. Configure the available options

### Configuration Options

#### Monitoring Endpoint
- **Path**: `monitoring.endpoint`
- **Default**: `/monitor/health`
- **Description**: The URL path where the monitoring endpoint will be available

Example:
```
monitoring.endpoint = /monitor/health
```

This makes the endpoint available at: `https://yoursite.com/monitor/health`

#### Authentication Secret
- **Path**: `monitoring.secret`
- **Default**: Empty
- **Description**: Secret key used for HMAC authentication

Example:
```
monitoring.secret = your-secure-secret-key-here
```

**Important**: Keep this secret secure and use a strong, random value.

## 📝 Configuration via LocalConfiguration.php

You can also configure the extension programmatically:

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

## 🔒 Security Configuration

### HTTPS Enforcement

The extension enforces HTTPS by default. This cannot be disabled for security
reasons. (under review)

## 🔌 Provider Configuration

Providers are automatically discovered and registered through the dependency
injection container. No additional configuration is required for basic
providers.

### Disabling Providers

To disable a specific provider, you can override its `isActive()` method or
exclude it from service registration:

```yaml
# In Configuration/Services.yaml
services:
  MyProvider:
    class: 'My\Extension\Provider\MyProvider'
    tags:
      - { name: 'monitoring.provider', active: false }
```

## 🔐 Authorization Configuration

### Multiple Authorizers

The extension supports multiple authorization strategies simultaneously.
They are  evaluated in priority order (highest first).

### Custom Authorization Priority

```php
// In your custom authorizer
public function getPriority(): int
{
    return 1000; // Higher priority = checked first
}
```

## 💾 Cache Configuration

### Provider Caching

For providers that implement `CacheableMonitoringProvider`, you can configure
caching:

```php
// In your provider
public function getCacheLifetime(): int
{
    return 300; // 5 minutes
}

public function getCacheKey(): string
{
    return 'my_provider_' . $this->getSomeIdentifier();
}
```

### Cache Backend

The extension uses TYPO3's default cache backend. You can configure a specific
cache backend:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['monitoring']
    = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\RedisBackend::class,
    'options' => [
        'hostname' => 'localhost',
        'port' => 6379,
        'database' => 2
    ]
];
```

## ✅ Validation

### Configuration Validation

To validate your configuration:

1. Check the backend module at **System → Monitoring**
2. Test the endpoint directly
3. Review TYPO3 logs for any configuration errors

### Testing Configuration

```bash
# Test endpoint accessibility
curl -I https://yoursite.com/monitor/health

# Test authentication
curl -H "X-TYPO3-MONITORING-AUTH: your-hmac-token" \
     https://yoursite.com/monitor/health
```

## 🔧 Troubleshooting

### Common Configuration Issues

#### Empty Secret Error
- Ensure the secret is configured in Extension Configuration
- Check that the secret is not empty or null

#### Endpoint Not Found
- Verify the endpoint path configuration
- Clear all caches after changing configuration
- Check web server URL rewriting

#### Authentication Failures
- Verify HMAC generation matches the expected format
- Check that the secret matches between client and server
- Ensure the request is made over HTTPS

## 👆 Next Steps

After configuration:

1. [Set up authentication](authorization.md)
2. [Create custom providers](providers.md)
3. [Test the API](api.md)
4. [Configure monitoring systems](api.md#monitoring-system-integration)
