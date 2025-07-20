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
            'provider' => [
                'mteu\Monitoring\Provider\SelfCareProvider' => [
                    'enabled' => true,
                ],
            ],
        ],
    ],
];
```

### API Configuration

- **`api.endpoint`**: URL path for monitoring endpoint
- (default: `/monitor/health`)

### Authorizer Configuration

#### Token Authorizer
- **`enabled`**: Enable/disable token-based authentication (default: `false`)
- **`secret`**: Secret key for HMAC authentication (default: `''`)
- **`authHeaderName`**: HTTP header name for auth token (default: `''`)
- **`priority`**: Authorization priority, higher = checked first
(default: `10`)

#### Admin User Authorizer
- **`enabled`**: Enable/disable admin user authentication (default: `false`)
- **`priority`**: Authorization priority, higher = checked first
- (default: `-10`)

## Provider Configuration

Providers are auto-discovered via dependency injection and can be configured via extension configuration.

### Built-in Provider Configuration

#### SelfCareProvider
- **`enabled`**: Enable/disable the SelfCareProvider (default: `true`)

```php
'provider' => [
    'mteu\Monitoring\Provider\SelfCareProvider' => [
        'enabled' => true,
    ],
],
```

### Custom Provider Configuration

To disable a custom provider via service configuration:

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

### Built-in Authorizers

#### Token Authorizer
Provides HMAC-based authentication using a shared secret:

```php
'mteu\Monitoring\Authorization\TokenAuthorizer' => [
    'enabled' => true,
    'secret' => 'your-secure-secret',
    'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
    'priority' => 10,
],
```

#### Admin User Authorizer
Allows access for logged-in TYPO3 backend administrators:

```php
'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
    'enabled' => true,
    'priority' => -10,
],
```

### Custom Authorizers

For custom authorizers, implement the `Authorizer` interface and set priority:

```php
public function getPriority(): int
{
    return 1000; // Higher = checked first
}
```

### Configuration Structure

The configuration uses a nested structure with type-safe defaults:

```php
// Configuration factory creates strongly-typed objects
$config = $factory->create();

// Access endpoint
$endpoint = $config->endpoint;

// Access token authorizer settings
$tokenConfig = $config->tokenAuthorizerConfiguration;
$isEnabled = $tokenConfig->isEnabled();
$secret = $tokenConfig->secret;
$headerName = $tokenConfig->authHeaderName;
$priority = $tokenConfig->getPriority();

// Access admin user authorizer settings
$adminConfig = $config->adminUserAuthorizerConfiguration;
$isEnabled = $adminConfig->isEnabled();
$priority = $adminConfig->getPriority();
```
