# Authorization Guide

Authorizers are evaluated in priority order. The first one that grants access allows the request.

Authorizers are auto-discovered via `#[AutoconfigureTag('monitoring.authorizer')]` on the `Authorizer`
interface. Implementations just need to implement the interface.

## Built-in Authorizers

### Token Authorization
HMAC-based authentication using TYPO3's HashService.

> [!WARNING]
> The generated token is a long-lived shared secret. It contains no nonce, timestamp, or expiry, so it is valid
> indefinitely and can be replayed by anyone who observes it. Rotating the `secret` value is the only way to revoke
> access if you don't want to rotate the TYPO3 encryption key. Treat the token with the same care as a password.

**Configuration:**
```php
'EXTENSIONS' => [
    'monitoring' => [
        'authorizer' => [
            'mteu\Monitoring\Authorization\TokenAuthorizer' => [
                'enabled' => true,
                'secret' => 'your-secure-secret',
                'authHeaderName' => 'X-TYPO3-MONITORING-AUTH',
                'priority' => 10,
            ],
        ],
    ],
];
```

**Token Generation:**
```php
use TYPO3\CMS\Core\Crypto\HashService;

$token = (new HashService())->hmac('/monitor/health', 'your-secure-secret');
```

**Usage:**
```bash
curl -H "X-TYPO3-MONITORING-AUTH: your-token" https://site.com/monitor/health
```

### Admin User Authorization
Allows access for logged-in TYPO3 backend administrators.

**Configuration:**
```php
'mteu\Monitoring\Authorization\AdminUserAuthorizer' => [
    'enabled' => true,
    'priority' => -10, // Lower priority, fallback
],
```

## Custom Authorizers

```php
<?php
declare(strict_types=1);

namespace My\Extension\Authorization;

use mteu\Monitoring\Authorization\Authorizer;
use Psr\Http\Message\ServerRequestInterface;

final class CustomAuthorizer implements Authorizer
{
    public function isActive(): bool
    {
        // Whether this authorizer participates in the chain at all.
        return true;
    }

    public function isAuthorized(ServerRequestInterface $request): bool
    {
        // Your authorization logic
        return $request->getHeaderLine('X-Custom-Auth') === 'valid-token';
    }

    public function getDescription(): string
    {
        // Shown in the backend module.
        return 'Custom header authorizer';
    }

    public static function getPriority(): int
    {
        return 100; // Higher = checked first
    }
}
```

## Priority System

**Default Priorities:**
- `TokenAuthorizer`: 10
- `AdminUserAuthorizer`: -10
