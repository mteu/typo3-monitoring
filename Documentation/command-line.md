# CLI Commands Guide

This guide covers the command-line interface for the TYPO3 Monitoring
Extension, including available commands, options, and usage scenarios.

## 🔍 Overview

The monitoring extension provides CLI commands for:
- Running monitoring checks from the command line
- Integration with cron jobs and automated scripts
- Debugging and testing monitoring providers
- Batch monitoring operations

## 📜 Available Commands

### monitoring:run

The main CLI command for executing monitoring checks.

**Command**: `monitoring:run`
**Alias**: None
**Description**: Runs all active monitoring providers and displays their
health status

#### Basic Usage

```bash
# Run monitoring checks
./vendor/bin/typo3 monitoring:run

# Example output:
Checking Monitoring status
 ✅ Scheduler
 ✅ Solr Cores
 🚨 FancyExternalApiService (cached)
```

#### Return Codes

The command returns standard exit codes:

| Exit Code | Constant           | Meaning                             |
|-----------|--------------------|-------------------------------------|
| 0         | `Command::SUCCESS` | All providers are healthy           |
| 1         | `Command::FAILURE` | One or more providers are unhealthy |
| 2         | `Command::INVALID` | No active providers found           |

#### Output Format

The command outputs:
- **Status Icons**: ✅ for healthy, 🚨 for unhealthy providers
- **Provider Names**: Display name of each provider
- **Cache Indicators**: `(cached)` for providers with cached results
- **Color Coding**: Green for healthy, red for unhealthy providers

## 💻 Usage Examples

### Basic Health Check

```bash
# Simple health check
./vendor/bin/typo3 monitoring:run
```

### Automated Monitoring

```bash
#!/bin/bash
# monitoring-check.sh

# Run monitoring
./vendor/bin/typo3 monitoring:run
EXIT_CODE=$?

# Handle results
case $EXIT_CODE in
    0)
        echo "✅ All systems healthy"
        ;;
    1)
        echo "🚨 Some systems unhealthy"
        # Send alert notification
        curl -X POST -H 'Content-type: application/json' \
            --data '{"text":"TYPO3 monitoring detected unhealthy systems"}' \
            YOUR_WEBHOOK_URL
        ;;
    2)
        echo "⚠️ No active providers"
        ;;
    *)
        echo "❌ Unknown error"
        ;;
esac

exit $EXIT_CODE
```

### Cron Job Integration

```bash
# Add to crontab for regular monitoring
# Run every 5 minutes
*/5 * * * * /path/to/typo3/vendor/bin/typo3 monitoring:run >> \
    /var/log/typo3-monitoring.log 2>&1

# Run every hour with email notification on failure
0 * * * * /path/to/typo3/vendor/bin/typo3 monitoring:run || \
    echo "TYPO3 monitoring failed" | mail -s "TYPO3 Alert" admin@example.com
```

### Docker Integration

```bash
# Run in Docker container
docker exec typo3-container ./vendor/bin/typo3 monitoring:run

# Docker health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD ./vendor/bin/typo3 monitoring:run || exit 1
```

### Kubernetes Integration

```yaml
# Kubernetes CronJob
apiVersion: batch/v1
kind: CronJob
metadata:
  name: typo3-monitoring
spec:
  schedule: "*/5 * * * *"
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: typo3-monitoring
            image: typo3:latest
            command: ["./vendor/bin/typo3", "monitoring:run"]
          restartPolicy: OnFailure
```

## 🚀 Advanced Usage

### Logging and Monitoring

```bash
#!/bin/bash
# Advanced monitoring script with logging

LOG_FILE="/var/log/typo3-monitoring.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Run monitoring with timestamp
echo "[$TIMESTAMP] Running TYPO3 monitoring..." >> $LOG_FILE
./vendor/bin/typo3 monitoring:run >> $LOG_FILE 2>&1
EXIT_CODE=$?

# Log result
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] SUCCESS: All systems healthy" >> $LOG_FILE
elif [ $EXIT_CODE -eq 1 ]; then
    echo "[$TIMESTAMP] FAILURE: Some systems unhealthy" >> $LOG_FILE
else
    echo "[$TIMESTAMP] INVALID: No active providers" >> $LOG_FILE
fi

# Rotate log file if too large
if [ -f "$LOG_FILE" ] && [ $(stat -c%s "$LOG_FILE") -gt 10485760 ]; then
    mv "$LOG_FILE" "${LOG_FILE}.old"
    echo "[$TIMESTAMP] Log file rotated" > $LOG_FILE
fi
```

### Integration with Monitoring Systems

#### Nagios Integration

```bash
#!/bin/bash
# nagios-check-typo3.sh

# Nagios plugin for TYPO3 monitoring
cd /path/to/typo3
./vendor/bin/typo3 monitoring:run > /tmp/typo3-monitoring.out 2>&1
EXIT_CODE=$?

# Read output
OUTPUT=$(cat /tmp/typo3-monitoring.out)

case $EXIT_CODE in
    0)
        echo "OK - All TYPO3 systems healthy | $OUTPUT"
        exit 0
        ;;
    1)
        echo "CRITICAL - Some TYPO3 systems unhealthy | $OUTPUT"
        exit 2
        ;;
    2)
        echo "WARNING - No active TYPO3 monitoring providers | $OUTPUT"
        exit 1
        ;;
    *)
        echo "UNKNOWN - TYPO3 monitoring error | $OUTPUT"
        exit 3
        ;;
esac
```

#### Prometheus Integration

```bash
#!/bin/bash
# prometheus-typo3-exporter.sh

# Export TYPO3 monitoring metrics for Prometheus
METRICS_FILE="/var/lib/prometheus/node-exporter/typo3_monitoring.prom"

# Run monitoring
./vendor/bin/typo3 monitoring:run > /tmp/typo3-monitoring.out 2>&1
EXIT_CODE=$?

# Generate metrics
cat > $METRICS_FILE << EOF
# HELP typo3_monitoring_health Overall TYPO3 monitoring health status
# TYPE typo3_monitoring_health gauge
typo3_monitoring_health{instance="$(hostname)"} \
    $([ $EXIT_CODE -eq 0 ] && echo 1 || echo 0)

# HELP typo3_monitoring_providers_total Total number of monitoring providers
# TYPE typo3_monitoring_providers_total counter
typo3_monitoring_providers_total{instance="$(hostname)"} \
    $(grep -c "✅\|🚨" /tmp/typo3-monitoring.out)

# HELP typo3_monitoring_last_check_timestamp Unix timestamp of last check
# TYPE typo3_monitoring_last_check_timestamp gauge
typo3_monitoring_last_check_timestamp{instance="$(hostname)"} $(date +%s)
EOF
```

## 🤖 Scripting and Automation

### Health Check Script

```bash
#!/bin/bash
# comprehensive-health-check.sh

set -e

# Configuration
TYPO3_PATH="/path/to/typo3"
ALERT_EMAIL="admin@example.com"
SLACK_WEBHOOK="https://hooks.slack.com/services/YOUR/WEBHOOK/URL"

# Function to send Slack notification
send_slack_notification() {
    local message="$1"
    local color="$2"

    curl -X POST -H 'Content-type: application/json' \
        --data "{\"attachments\":[{\"color\":\"$color\",\"text\":\"$message\"}]}" \
        \
        "$SLACK_WEBHOOK"
}

# Function to send email
send_email() {
    local subject="$1"
    local body="$2"

    echo "$body" | mail -s "$subject" "$ALERT_EMAIL"
}

# Main health check
cd "$TYPO3_PATH"
echo "Running TYPO3 monitoring at $(date)..."

# Capture output and exit code
OUTPUT=$(./vendor/bin/typo3 monitoring:run 2>&1)
EXIT_CODE=$?

# Process results
case $EXIT_CODE in
    0)
        echo "✅ All systems healthy"
        send_slack_notification "TYPO3 monitoring: All systems healthy" "good"
        ;;
    1)
        echo "🚨 Some systems unhealthy"
        echo "$OUTPUT"
        send_slack_notification \
            "TYPO3 monitoring: Some systems unhealthy\\n$OUTPUT" "danger"
        send_email "TYPO3 Alert: Systems Unhealthy" "$OUTPUT"
        ;;
    2)
        echo "⚠️ No active providers"
        send_slack_notification \
            "TYPO3 monitoring: No active providers" "warning"
        ;;
    *)
        echo "❌ Unknown error (exit code: $EXIT_CODE)"
        echo "$OUTPUT"
        send_slack_notification \
            "TYPO3 monitoring: Unknown error\\n$OUTPUT" "danger"
        send_email "TYPO3 Alert: Monitoring Error" "$OUTPUT"
        ;;
esac

exit $EXIT_CODE
```

### Batch Operations

```bash
#!/bin/bash
# batch-monitoring.sh

# Monitor multiple TYPO3 instances
INSTANCES=(
    "/var/www/site1"
    "/var/www/site2"
    "/var/www/site3"
)

OVERALL_STATUS=0

for instance in "${INSTANCES[@]}"; do
    echo "Checking $instance..."
    cd "$instance"

    ./vendor/bin/typo3 monitoring:run
    EXIT_CODE=$?

    if [ $EXIT_CODE -ne 0 ]; then
        OVERALL_STATUS=1
        echo "❌ $instance has issues"
    else
        echo "✅ $instance is healthy"
    fi

    echo "---"
done

if [ $OVERALL_STATUS -eq 0 ]; then
    echo "🎉 All instances are healthy"
else
    echo "🚨 Some instances have issues"
fi

exit $OVERALL_STATUS
```

## 🔍 Debugging and Testing

### Debug Mode

```bash
# Enable TYPO3 debug mode for detailed output
TYPO3_CONTEXT=Development ./vendor/bin/typo3 monitoring:run

# Or set in environment
export TYPO3_CONTEXT=Development
./vendor/bin/typo3 monitoring:run
```

### Verbose Output

```bash
# Capture detailed output
./vendor/bin/typo3 monitoring:run -vvv 2>&1 | tee monitoring-debug.log

# Check specific provider execution
./vendor/bin/typo3 monitoring:run 2>&1 | grep -E "✅|🚨"
```

### Testing Scenarios

```bash
#!/bin/bash
# test-monitoring-scenarios.sh

# Test 1: Normal operation
echo "Test 1: Normal operation"
./vendor/bin/typo3 monitoring:run
echo "Exit code: $?"
echo

# Test 2: Timeout scenarios
echo "Test 2: Testing with timeout"
timeout 30 ./vendor/bin/typo3 monitoring:run
echo "Exit code: $?"
echo

# Test 3: Memory limit testing
echo "Test 3: Memory limit testing"
php -d memory_limit=64M ./vendor/bin/typo3 monitoring:run
echo "Exit code: $?"
echo
```

## ⚡ Performance Optimization

### Caching Considerations

```bash
#!/bin/bash
# cache-aware-monitoring.sh

# Warm up cache before monitoring
./vendor/bin/typo3 cache:warmup

# Run monitoring
./vendor/bin/typo3 monitoring:run
EXIT_CODE=$?

# Clear cache if monitoring failed
if [ $EXIT_CODE -ne 0 ]; then
    echo "Monitoring failed, clearing cache..."
    ./vendor/bin/typo3 cache:flush
fi

exit $EXIT_CODE
```

### Parallel Execution

```bash
#!/bin/bash
# parallel-monitoring.sh

# Run monitoring in background
./vendor/bin/typo3 monitoring:run &
MONITORING_PID=$!

# Set timeout
TIMEOUT=60

# Wait for completion or timeout
if timeout $TIMEOUT bash -c "wait $MONITORING_PID"; then
    # Get exit code
    wait $MONITORING_PID
    EXIT_CODE=$?
    echo "Monitoring completed with exit code: $EXIT_CODE"
else
    # Kill if timeout
    kill $MONITORING_PID 2>/dev/null
    echo "Monitoring timed out after $TIMEOUT seconds"
    exit 124
fi

exit $EXIT_CODE
```

## 🔄 Integration Patterns

### Service Integration

```bash
#!/bin/bash
# service-integration.sh

# Systemd service integration
case "$1" in
    start)
        echo "Starting TYPO3 monitoring service..."
        ;;
    stop)
        echo "Stopping TYPO3 monitoring service..."
        ;;
    status)
        ./vendor/bin/typo3 monitoring:run
        ;;
    *)
        echo "Usage: $0 {start|stop|status}"
        exit 1
        ;;
esac
```

### CI/CD Integration

```bash
#!/bin/bash
# ci-cd-monitoring.sh

# CI/CD pipeline integration
echo "Running TYPO3 monitoring in CI/CD pipeline..."

# Set appropriate environment
export TYPO3_CONTEXT=Production

# Run monitoring
./vendor/bin/typo3 monitoring:run
EXIT_CODE=$?

# Generate report
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ Monitoring passed - deployment can proceed"
else
    echo "🚨 Monitoring failed - blocking deployment"
    exit 1
fi
```

## ✨ Best Practices

### Error Handling

1. **Always check exit codes** before proceeding with automated actions
2. **Implement proper logging** for audit trails
3. **Set appropriate timeouts** to prevent hanging processes
4. **Handle edge cases** like empty provider lists

### Security

1. **Run with minimal privileges** - don't run as root
2. **Secure log files** with appropriate permissions
3. **Validate input** when accepting parameters
4. **Use environment variables** for sensitive configuration

### Performance

1. **Monitor execution time** and set reasonable timeouts
2. **Consider caching** for frequently run checks
3. **Implement circuit breakers** for external dependencies
4. **Log performance metrics** for optimization

### Monitoring

1. **Monitor the monitoring** - ensure CLI commands are working
2. **Set up alerts** for CLI command failures
3. **Track execution frequency** and success rates
4. **Maintain execution logs** for troubleshooting

## 🔧 Troubleshooting

### Common Issues

#### Command Not Found
```bash
# Check if TYPO3 is properly installed
./vendor/bin/typo3 --version

# Check command availability
./vendor/bin/typo3 list | grep monitoring
```

#### Permission Errors
```bash
# Check file permissions
ls -la vendor/bin/typo3
chmod +x vendor/bin/typo3
```

#### Memory Issues
```bash
# Increase memory limit
php -d memory_limit=256M ./vendor/bin/typo3 monitoring:run
```

#### Timeout Problems
```bash
# Set execution timeout
php -d max_execution_time=300 ./vendor/bin/typo3 monitoring:run
```

### Debug Output

```bash
# Enable debug output
TYPO3_CONTEXT=Development ./vendor/bin/typo3 monitoring:run -v

# Check system requirements
./vendor/bin/typo3 configuration:show
```

## 👆 Next Steps

After setting up CLI commands:

1. [Use the backend module](backend.md) for visual monitoring
2. [Test the API](api.md) for HTTP-based monitoring
3. [Create custom providers](providers.md) for additional checks
4. [Configure external monitoring systems](api.md#monitoring-system-integration)
