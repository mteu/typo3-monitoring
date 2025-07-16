# ⚙️ Extension Configuration

The extension is configured through TYPO3's Extension Configuration system.

## Accessing Configuration

1. Navigate to **Admin Tools → Settings → Extension Configuration**
2. Select `monitoring` from the list
3. Configure the available options

## Configuration Options

### Monitoring Endpoint
- **Path**: `api.endpoint`
- **Default**: `/monitor/health`
- **Description**: The URL path where the monitoring endpoint will be available

This makes the endpoint available at: `https://yoursite.com/monitor/health`

### Authentication Secret
- **Path**: `api.secret`
- **Default**: Empty
- **Description**: Secret key used for HMAC authentication

**Important**: Keep this secret secure and use a strong, random value.

## 📝 Configuration via `settings.php`

You might want to configure the extension programmatically:

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

## ➡️ Next Steps

After configuration:

1. [Set up authentication](authorization.md)
2. [Create custom providers](providers.md)
3. [Test the API](api.md)
4. [Configure monitoring systems](api.md#monitoring-system-integration)
