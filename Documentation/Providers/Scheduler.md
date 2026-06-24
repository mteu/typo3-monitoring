# Scheduler Provider

Monitors the TYPO3 scheduler and reports its health on the monitoring endpoint.
A broken cron job silently stops *every* task without any single task "failing",
so this provider checks the scheduler as a whole rather than relying on
individual task errors.

The provider is **inactive** unless the `typo3/cms-scheduler` system extension is
installed *and* the provider is enabled in the configuration.

## What it checks

`execute()` returns one aggregate result (`Scheduler`) composed of three
sub-results. The aggregate is healthy only when all enabled checks pass.

| Sub-result            | Healthy when …                                                                                    |
|-----------------------|---------------------------------------------------------------------------------------------------|
| `Scheduler Execution` | The scheduler ran within the heartbeat threshold, or a task is currently running (cron is alive). |
| `Overdue Tasks`       | No enabled, *scheduled* task is past its next-execution time + grace period.                      |
| `Failed Tasks`        | No enabled task recorded an execution failure.                                                    |

> [!NOTE]
>- Manual tasks are ignored by the overdue check — only tasks with an explicit next-execution time are considered.
>- Each failing/overdue reason lists up to 5 affected tasks. In the backend module these render as deep links to the
>task's edit view.

## Configuration

Set via Extension Configuration (`monitoring`) or `config/system/settings.php`:

```php
return [
    'EXTENSIONS' => [
        'monitoring' => [
            'provider' => [
                'mteu\\Monitoring\\Provider\\SchedulerProvider' => [
                    'enabled' => true,
                    'heartbeatThreshold' => 900,
                    'overdueThreshold' => 300,
                    'overdueSeverity' => 'unhealthy',
                    'heartbeatStaleSeverity' => 'unhealthy',
                ],
            ],
        ],
    ],
];
```

| Setting              | Default | Description                                                                                         |
|----------------------|---------|-----------------------------------------------------------------------------------------------------|
| `enabled`            | `true`  | Enables the provider. When `false` the provider reports as inactive.                                |
| `heartbeatThreshold` | `900`   | Max age (seconds) of the last scheduler run before the heartbeat is unhealthy. `0` disables it.     |
| `overdueThreshold`   | `300`   | Grace period (seconds) added to a task's next-execution time before it is overdue. `0` disables it. |
| `overdueSeverity`        | `unhealthy` | Status reported for an overdue task: `unhealthy` (an outage — HTTP 503, may notify) or `degraded` (visible on the endpoint at HTTP 200, no notification). |
| `heartbeatStaleSeverity` | `unhealthy` | Status reported when the heartbeat is stale: `unhealthy` or `degraded`. A heartbeat that never ran at all is always `unhealthy`. |

Tune the thresholds to your cron frequency: `heartbeatThreshold` should comfortably
exceed how often cron runs the scheduler, and `overdueThreshold` should absorb the
expected runtime of your longest scheduled task.

The severity knobs let you classify, per condition, what counts as `degraded`
versus `unhealthy` — for example "an overdue task is FYI (`degraded`), a failed
task is an outage (`unhealthy`)". Failed tasks are always `unhealthy` and have no
knob. A `degraded` sub-result keeps the Scheduler's aggregate status at
`degraded` (HTTP 200) and, by default, does **not** notify; pair it with
`reportDispatcher.notifyFrom = degraded` (see [Reporters](../Reporters.md)) if
you do want degradation to page.

## Example output

```json
{
  "status": "unhealthy",
  "services": {
    ...
    "Scheduler": {
      "status": "unhealthy",
      "subResults": {
        "Scheduler Execution": { "status": "healthy" },
        "Overdue Tasks": { "status": "healthy" },
        "Failed Tasks": { "status": "unhealthy" }
      }
    },
    ...
}
```

> [!NOTE]
> The full breakdown (reasons and deep links to the affected tasks) is available in the TYPO3 backend module.
