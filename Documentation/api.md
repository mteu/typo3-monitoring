# API Reference

This guide covers the HTTP API of the TYPO3 Monitoring Extension, including
endpoint specifications, request/response formats, and integration examples.

## 🔍 API Overview

The monitoring extension provides a single HTTP endpoint that returns the
health status of your TYPO3 instance in JSON format. The endpoint is designed
to be consumed by external monitoring systems.

### Base Information

- **Protocol**: HTTPS only (enforced)
- **Method**: GET
- **Content-Type**: `application/json`
- **Authentication**: Multiple strategies supported (Token, Admin User)
- **Rate Limiting**: Can be implemented via external systems

## ⚙️ Endpoint Configuration

The monitoring endpoint is configurable through Extension Configuration:

```
Default: /monitor/health
Configuration: monitoring.endpoint
```

Access the endpoint at: `https://yoursite.com/monitor/health`

## 🔐 Authentication

### Token Authentication

Include the HMAC token in the request header:

```http
GET /monitor/health HTTP/1.1
Host: yoursite.com
X-TYPO3-MONITORING-AUTH: your-hmac-token
```

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

## ✨ Best Practices

### Performance

1. **Implement Client-Side Caching**: Cache responses for appropriate intervals
2. **Use Reasonable Timeouts**: Set appropriate connection and read timeouts
3. **Implement Retry Logic**: Handle temporary failures gracefully
4. **Monitor API Performance**: Track response times and error rates

### Security

1. **Use HTTPS Only**: Never make requests over HTTP
2. **Secure Secret Storage**: Store secrets securely (environment variables,
   key management)
3. **Validate SSL Certificates**: Always verify SSL certificates
4. **Implement Rate Limiting**: Prevent abuse with appropriate rate limits

### Error Handling

1. **Handle All HTTP Status Codes**: Implement appropriate responses for all
   status codes
2. **Parse JSON Safely**: Always validate JSON responses
3. **Log Errors Appropriately**: Log errors without exposing sensitive
   information
4. **Implement Circuit Breakers**: Prevent cascading failures

### Monitoring the Monitor

1. **Monitor API Availability**: Ensure the monitoring endpoint itself is
   monitored
2. **Track Response Times**: Monitor API response performance
3. **Alert on Failures**: Set up alerts for monitoring system failures
4. **Implement Health Checks**: Regular health checks for the monitoring
   system

## 🔧 Troubleshooting

### Common Issues

#### Connection Refused
- Check HTTPS configuration
- Verify endpoint configuration
- Check firewall settings

#### 🔐 Authentication Failures
- Verify secret configuration
- Check token generation logic
- Ensure proper header format

#### Timeout Issues
- Increase client timeout settings
- Check server performance
- Verify network connectivity

#### Invalid JSON Response
- Check server error logs
- Verify endpoint path
- Ensure proper content-type headers

## 👆 Next Steps

- [Set up authentication](authorization.md)
- [Use the backend module](backend.md)
- [Configure CLI commands](command-line.md)
- [Create custom providers](providers.md)
