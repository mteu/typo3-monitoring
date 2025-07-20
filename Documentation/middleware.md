# Middleware

The `MonitoringMiddleware` handles HTTP requests to the monitoring endpoint and
orchestrates the monitoring workflow.

## Request Processing

The middleware processes requests in the following order:

1. **Endpoint Validation** - Checks if the request path matches the configured
endpoint
2. **HTTPS Enforcement** - Ensures the request uses HTTPS protocol
3. **Authorization** - Validates the request using registered authorizers
4. **Provider Execution** - Executes active monitoring providers
5. **Response Generation** - Returns JSON response with health status

## Configuration

The middleware is automatically registered and configured via:

```php
# Configuration/RequestMiddlewares.php
return [
    'frontend' => [
        'mteu/typo3-monitoring' => [
            'target' => \mteu\Monitoring\Middleware\MonitoringMiddleware::class,
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
        ],
    ],
];
```

## Implementation Details

### Endpoint Matching

The middleware only processes requests that match the configured endpoint path:

```php
private function isValid(ServerRequestInterface $request): bool
{
    return rtrim($request->getUri()->getPath(), '/') === $this->endpoint;
}
```

### HTTPS Enforcement

All requests must use HTTPS. HTTP requests receive a 403 Forbidden response:

```php
private function isHttps(ServerRequestInterface $request): bool
{
    return $request->getUri()->getScheme() === 'https';
}
```

### Authorization Flow

The middleware evaluates all registered authorizers in priority order. The first
authorizer that grants access allows the request to proceed:

```php
private function isAuthorized(ServerRequestInterface $request): bool
{
    foreach ($this->authorizers as $authorizer) {
        if ($authorizer->isAuthorized($request)) {
            return true;
        }
    }
    return false;
}
```

### Provider Execution

Active providers are executed and their results aggregated:

```php
private function getHealthStatus(): array
{
    $status = [];
    foreach ($this->monitoringProviders as $provider) {
        if ($provider->isActive()) {
            $status[$provider->getName()] = $provider->execute()->isHealthy();
        }
    }
    return $status;
}
```

## Response Formats

### Success Response (200 OK)

```json
{
    "isHealthy": true,
    "services": {
        "ServiceName": "healthy"
    }
}
```

### Unhealthy Response (503 Service Unavailable)

```json
{
    "isHealthy": false,
    "services": {
        "ServiceName": "unhealthy"
    }
}
```

### Error Responses

**401 Unauthorized**
```json
{
    "code": 401,
    "error": "unauthorized"
}
```

**403 Forbidden**
```json
{
    "code": 403,
    "error": "unsupported-protocol"
}
```

## Provider Execution

The middleware executes all active providers and collects their health status. Special handling is applied to certain providers:

### SelfCareProvider Handling

The built-in `SelfCareProvider` is completely excluded from middleware execution for logical and performance reasons:

- **Redundancy**: If the middleware can respond, the monitoring system is already working
- **Performance**: Avoids unnecessary HTTP requests to itself during monitoring requests
- **Logic**: The middleware's successful response IS the self-check

```php
// SelfCareProvider is completely skipped in middleware execution
if ($provider instanceof SelfCareProvider) {
    continue; // Skip execution entirely
}
```

## Error Handling

The middleware handles errors gracefully:

- JSON encoding errors are logged and fall back to the next middleware
- Invalid configurations result in passing requests to the next middleware
- Authorization failures return structured error responses
- Provider exceptions are caught and reported as unhealthy status
