# CLI Commands Guide

The monitoring extension provides CLI commands for:
- Running monitoring checks from the command line
- Integration with cron jobs and automated scripts
- Debugging and testing monitoring providers
- Batch monitoring operations

## Available Commands

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
Monitoring status: FAILED
```

#### Options

| Option       | Description                                                              |
|--------------|--------------------------------------------------------------------------|
| `--no-cache` | Bypass the monitoring cache and force a fresh execution of every provider. |

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

### monitoring:report

Evaluates the current monitoring status and dispatches push notifications to
active [reporters](reporters.md) (e.g. the [EmailReporter](Reporter/EmailReporter.md)).
Meant to be run on a schedule (cron or a scheduler task); it is a thin wrapper
around the `ReportDispatcher` and applies the dedup/reminder logic described in
the [Reporter guide](reporters.md).

**Command**: `monitoring:report`
**Alias**: None
**Description**: Evaluates monitoring status and dispatches push notifications to
active reporters

#### Basic Usage

```bash
./vendor/bin/typo3 monitoring:report

# Example output (one of):
No notification dispatched.
Dispatched: unhealthy status.
Dispatched: reminder for ongoing unhealthy status.
Dispatched: recovery notice.
```

#### Return Codes

| Exit Code | Constant           | Meaning                                                             |
|-----------|--------------------|--------------------------------------------------------------------|
| 0         | `Command::SUCCESS` | The dispatch decision was handled (nothing due, or delivered)      |
| 1         | `Command::FAILURE` | A notification was due but no active reporter delivered it         |

A notification can be *due* (the status is unhealthy, a reminder is overdue, or
a recovery happened) yet remain *undelivered* when no reporter is active — for
example when the `EmailReporter` has no recipient configured. In that case the
misconfiguration is reported and the command exits non-zero, e.g.:

```bash
No report dispatched: An unhealthy notification was due but no active reporter
delivered it. Enable and configure at least one reporter (e.g. set an email
recipient for the EmailReporter).
```

> [!NOTE]
> The backend Scheduler task (`MonitoringReportTask`) triggers the same dispatch but always reports success to the
> scheduler. The non-zero exit code is specific to the interactive CLI command.

## Usage Examples

### Basic Health Check

```bash
# Simple health check
./vendor/bin/typo3 monitoring:run
```
