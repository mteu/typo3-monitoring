# Configuration

Configure the extension via Extension Configuration or programmatically:

```php
# config/system/settings.php
return [
    'EXTENSIONS' => [
        'monitoring' => [
            'api' => [
                'endpoint' => '/monitor/health',
                'enforceHttps' => false,
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
];
```

## Settings

### API
- **`endpoint`**: URL path for monitoring endpoint (default: `/monitor/health`)
- **`enforceHttps`**: Reject monitoring requests that are not HTTPS (default: `false`).
  TLS termination should happen at the web server / ingress, not in PHP — leave
  this off unless you have a specific reason to enable it. When enabled, the
  middleware uses TYPO3's `NormalizedParams::isHttps()`, which honors the
  trusted-proxy configuration in `$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']`
  and `reverseProxySSL`. Make sure those are configured correctly first;
  otherwise legitimate requests behind a reverse proxy will be rejected with a
  `403 unsupported-protocol` response.

### Token Authorizer
- **`enabled`**: Enable token authentication (default: `false`)
- **`secret`**: HMAC secret key (default: `''`)
- **`authHeaderName`**: HTTP header name (default: `''`)
- **`priority`**: Authorization priority (default: `10`)

### Admin User Authorizer
- **`enabled`**: Enable admin user authentication (default: `false`)
- **`priority`**: Authorization priority (default: `-10`)

### Providers
- **`enabled`**: Enable/disable specific providers.

  A provider's on/off state is exposed through its `isEnabled()` method, which
  acts as an authoritative kill-switch (a disabled provider is never executed).
  How that value is sourced is up to each provider; the recommended approach is a
  typed extension-configuration object, as the shipped `SchedulerProvider` does.
  See [migration.md](migration_v1.md) and
  [providers.md](providers.md#enablement-and-activation) for details.

## Configuration Access

The extension uses type-safe configuration objects:

```php
// Access endpoint
$endpoint = $config->endpoint;

// Access token settings
$tokenConfig = $config->tokenAuthorizerConfiguration;
$isEnabled = $tokenConfig->isEnabled();
$secret = $tokenConfig->secret;
```

