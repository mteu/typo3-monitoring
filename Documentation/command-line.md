# CLI Commands Guide

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

## Next Steps

After setting up CLI commands:

1. [Use the backend module](backend.md) for visual monitoring
2. [Test the API](api.md) for HTTP-based monitoring
3. [Create custom providers](providers.md) for additional checks
4. [Configure external monitoring systems](api.md#monitoring-system-integration)
