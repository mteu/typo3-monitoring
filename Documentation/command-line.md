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
 ✅ Solr Cores (cached)
 🚨 FancyExternalApiService
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
