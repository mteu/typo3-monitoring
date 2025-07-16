# Configuration

## Extension Configuration

Configure via Extension Configuration or programmatically:

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

### Options

- **`api.endpoint`**: URL path for monitoring endpoint (default: `/monitor/health`)
- **`api.secret`**: Secret key for HMAC authentication

## Provider Configuration

Providers are auto-discovered via dependency injection. To disable a provider:

```yaml
# Configuration/Services.yaml
services:
  MyProvider:
    class: 'My\Extension\Provider\MyProvider'
    tags:
      - { name: 'monitoring.provider', active: false }
```

## Authorization Configuration

Multiple authorizers are supported, evaluated by priority (highest first).

Set custom priority:
```php
public static function getPriority(): int
{
    return 1000; // Higher = checked first
}
```

## Cache Configuration

Configure cache backend for provider caching:

```php
# config/system/additional.php or ext_localconf.php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['monitoring'] = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\RedisBackend::class,
    'options' => [
        'hostname' => 'localhost',
        'port' => 6379,
        'database' => 2,
    ],
];
```
