# API Reference

The monitoring extension provides a single HTTP endpoint that returns the
health status of your TYPO3 instance in JSON format. The endpoint is designed
to be consumed by external monitoring systems.

## ⚙️ Endpoint Configuration

The monitoring endpoint is configurable through Extension Configuration:

```
Default: /monitor/health
Configuration: api.endpoint
```

Access the endpoint at: `https://yoursite.com/monitor/health`

## 🔐 Authentication

### Token Authentication

Include the authentication token in the request header:

```http
GET /monitor/health HTTP/1.1
Host: yoursite.com
X-TYPO3-MONITORING-AUTH: your-auth-token
```
> [!TIP]
> [Learn here](Documentation/authorization.md#token-generation) how the
> Authentication token is generated.

### Admin User Authentication

For backend administrators, no additional authentication is required when
logged in to the TYPO3 backend.

## 📝 API Responses

### Success Response

**Status Code**: `200 OK`

```json
{
    "isHealthy": true,
    "services": {
        "Scheduler": "healthy",
        "SolrCores": "healthy",
        "FancyExternalApiService": "healthy"
    }
}
```

**Note**: The built-in `SelfCareProvider` is never included in the `services` object when accessed via HTTP. The middleware skips it entirely since a successful HTTP response already proves the monitoring system is working.

### Unhealthy Response

**Status Code**: `503 Service Unavailable`

```json
{
    "isHealthy": false,
    "services": {
        "Scheduler": "healthy",
        "SolrCores": "healthy",
        "FancyExternalApiService": "unhealthy"
    }
}
```

### Error Responses

#### 401 Unauthorized

```json
{
    "code": 401,
    "error": "unauthorized"
}
```

**Causes:**
- Invalid or missing authentication token
- Expired authentication credentials
- No valid authorizer grants access

#### 403 Forbidden

```json
{
    "code": 403,
    "error": "unsupported-protocol"
}
```

**Causes:**
- Request made over HTTP instead of HTTPS
- Protocol security requirements not met

#### 404 Not Found

Standard HTTP 404 response when endpoint path doesn't match configuration.

**Causes:**
- Incorrect endpoint path
- Extension not properly configured
- URL rewriting issues

## 🔅 Response Fields

### Root Level

| Field       | Type    | Description                      |
|-------------|---------|----------------------------------|
| `isHealthy` | boolean | Overall system health status     |
| `services`  | object  | Individual service health status |

### Services Object

Each service in the `services` object represents a monitoring provider result:

| Key          | Type   | Description                     |
|--------------|--------|---------------------------------|
| Service Name | string | Either "healthy" or "unhealthy" |


## 🔢 HTTP Status Codes

| Status Code | Meaning             | Description                        |
|-------------|---------------------|------------------------------------|
| 200         | OK                  | All services are healthy           |
| 401         | Unauthorized        | Authentication failed              |
| 403         | Forbidden           | Protocol requirements not met      |
| 404         | Not Found           | Endpoint not found                 |
| 503         | Service Unavailable | One or more services are unhealthy |

## 💻 Request Examples

### cURL Examples

**Basic Request:**
```bash
curl -H "X-TYPO3-MONITORING-AUTH: your-token" \
     https://yoursite.com/monitor/health
```

**With Verbose Output:**
```bash
curl -v -H "X-TYPO3-MONITORING-AUTH: your-token" \
     https://yoursite.com/monitor/health
```

**Health Check Script:**
```bash
#!/bin/bash
ENDPOINT="/monitor/health"
TOKEN="your-auth-token"
BASE_URL="https://yoursite.com"

# Make request
RESPONSE=$(curl -s -H "X-TYPO3-MONITORING-AUTH: $TOKEN" \
  "$BASE_URL$ENDPOINT")
IS_HEALTHY=$(echo "$RESPONSE" | jq -r '.isHealthy')

if [ "$IS_HEALTHY" = "true" ]; then
    echo "✅ System is healthy"
    exit 0
else
    echo "🚨 System is unhealthy"
    echo "$RESPONSE" | jq '.services'
    exit 1
fi
```
## 🔄 Monitoring System Integration

### Nagios/Icinga

**Check Command:**
```bash
#!/bin/bash
# check_typo3_health.sh

ENDPOINT="/monitor/health"
TOKEN="$1"
BASE_URL="$2"

if [ -z "$TOKEN" ] || [ -z "$BASE_URL" ]; then
    echo "UNKNOWN - Missing arguments"
    exit 3
fi

RESPONSE=$(curl -s -H "X-TYPO3-MONITORING-AUTH: $TOKEN" \
  "$BASE_URL$ENDPOINT")
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    echo "CRITICAL - curl failed with exit code $EXIT_CODE"
    exit 2
fi

IS_HEALTHY=$(echo "$RESPONSE" | jq -r '.isHealthy')

if [ "$IS_HEALTHY" = "true" ]; then
    echo "OK - All services healthy"
    exit 0
elif [ "$IS_HEALTHY" = "false" ]; then
    UNHEALTHY=$(echo "$RESPONSE" | jq -r '.services | to_entries[] |
      select(.value == "unhealthy") | .key' | tr '\n' ' ')
    echo "CRITICAL - Unhealthy services: $UNHEALTHY"
    exit 2
else
    echo "UNKNOWN - Invalid response format"
    exit 3
fi
```

**Nagios Configuration:**
```
define command {
    command_name    check_typo3_health
    command_line    /usr/local/nagios/libexec/check_typo3_health.sh \
                    "$ARG1$" "$ARG2$"
}

define service {
    host_name               typo3-server
    service_description     TYPO3 Health
    check_command           check_typo3_health!your-secret!https://yoursite.com
    check_interval          5
    retry_interval          1
    max_check_attempts      3
}
```

### Prometheus

**Prometheus Configuration:**
```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'typo3-monitoring'
    static_configs:
      - targets: ['yoursite.com']
    metrics_path: '/monitor/health'
    scheme: https
    scrape_interval: 30s
    honor_labels: true
    params:
      format: ['prometheus']  # If you implement Prometheus format
```

## Next Steps

- [Set up authentication](authorization.md)
- [Use the backend module](backend.md)
- [Configure CLI commands](command-line.md)
- [Create custom providers](providers.md)
