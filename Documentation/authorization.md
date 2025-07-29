# Authorization Guide

Authorizers are evaluated in priority order. The first one that grants access allows the request.

## Built-in Authorizers

### Token Authorization
HMAC-based authentication using TYPO3's HashService.

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
$hashService = GeneralUtility::makeInstance(HashService::class);
$token = $hashService->hmac('/monitor/health', 'your-secure-secret');
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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monitoring.authorizer')]
final class CustomAuthorizer implements Authorizer
{
    public function isAuthorized(ServerRequestInterface $request): bool
    {
        // Your authorization logic
        return $request->getHeaderLine('X-Custom-Auth') === 'valid-token';
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
