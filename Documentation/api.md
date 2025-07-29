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

### Success (200 OK)
```json
{
    "isHealthy": true,
    "services": {
        "ServiceName": "healthy",
        "AnotherService": "healthy"
    }
}
```

### Unhealthy (503 Service Unavailable)
```json
{
    "isHealthy": false,
    "services": {
        "ServiceName": "healthy",
        "FailingService": "unhealthy"
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

**403 Forbidden (HTTP instead of HTTPS)**
```json
{
    "code": 403,
    "error": "unsupported-protocol"
}
```

## Status Codes

| Code | Meaning             | Description                        |
|------|---------------------|------------------------------------|
| 200  | OK                  | All services healthy               |
| 401  | Unauthorized        | Authentication failed              |
| 403  | Forbidden           | HTTPS required                     |
| 404  | Not Found           | Endpoint not configured            |
| 503  | Service Unavailable | One or more services unhealthy     |

