# API Reference

The monitoring extension provides a single HTTP endpoint that returns health status in JSON format.

## Endpoint

**Default**: `/monitor/health`
**Configuration**: `api.endpoint` in extension configuration

## Authentication

### Token Authentication
```http
GET /monitor/health HTTP/1.1
Host: yoursite.com
X-TYPO3-MONITORING-AUTH: your-auth-token
```

### Admin User Authentication
Access while logged in as TYPO3 backend administrator.

## Responses

The top level reports the overall `status`. Each service maps to an object with
its own `status`, and providers that report sub-results list each sub-result as
a nested object under `subResults` — the structure is uniform at every level.

`status` is one of `healthy`, `degraded`, or `unhealthy`:

| `status`    | HTTP | Meaning                                              |
|-------------|------|------------------------------------------------------|
| `healthy`   | 200  | Everything is operating normally.                    |
| `degraded`  | 200  | Operational, but something warrants attention.       |
| `unhealthy` | 503  | At least one service is failing.                     |

The overall `status` is the worst status across all reported services; a single
`unhealthy` service makes the whole response `unhealthy` (HTTP 503), while a
`degraded` service keeps the endpoint at HTTP 200.

### Success (200 OK)
```json
{
    "status": "healthy",
    "services": {
        "SuccessfulService": {
            "status": "healthy"
        },
        "AnotherService": {
            "status": "healthy",
            "subResults": {
                "SubCheck A": { "status": "healthy" },
                "SubCheck B": { "status": "healthy" }
            }
        }
    }
}
```

### Degraded (200 OK)
```json
{
    "status": "degraded",
    "services": {
        "SuccessfulService": {
            "status": "healthy"
        },
        "AttentionService": {
            "status": "degraded",
            "subResults": {
                "SubCheck A": { "status": "healthy" },
                "SubCheck B": { "status": "degraded" }
            }
        }
    }
}
```

### Unhealthy (503 Service Unavailable)
```json
{
    "status": "unhealthy",
    "services": {
        "SuccessfulService": {
            "status": "healthy"
        },
        "FailingService": {
            "status": "unhealthy",
            "subResults": {
                "SubCheck A": { "status": "healthy" },
                "SubCheck B": { "status": "unhealthy" }
            }
        }
    }
}
```

When `api.includeServicesHealth` is disabled, only the overall status is
returned:

```json
{ "status": "healthy" }
```

### Error Responses

**401 Unauthorized**
```json
{
    "code": 401,
    "error": "unauthorized"
}
```

**403 Forbidden (HTTP instead of HTTPS)**
```json
{
    "code": 403,
    "error": "unsupported-protocol"
}
```

> Only returned when `api.enforceHttps` is enabled. By default the extension
> does not enforce HTTPS in PHP — TLS termination is expected to happen at the
> web server / ingress. See [Configuration](configuration.md) for details and
> the trusted-proxy caveats.

**405 Method Not Allowed (non-GET/HEAD request)**
```json
{
    "code": 405,
    "error": "method-not-allowed"
}
```

> The response carries an `Allow: GET, HEAD` header. `HEAD` requests are
> answered with the same status code as `GET` but without a response body.

## Status Codes

| Code | Meaning             | Description                        |
|------|---------------------|------------------------------------|
| 200  | OK                  | All services healthy or degraded   |
| 401  | Unauthorized        | Authentication failed              |
| 403  | Forbidden           | HTTPS required                     |
| 404  | Not Found           | Endpoint not configured            |
| 405  | Method Not Allowed  | Only `GET` and `HEAD` are accepted |
| 503  | Service Unavailable | One or more services unhealthy     |
