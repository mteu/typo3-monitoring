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

| Sub-result          | Healthy when …                                                                 |
|---------------------|--------------------------------------------------------------------------------|
| `Scheduler Execution` | The scheduler ran within the heartbeat threshold (cron is alive).            |
| `Overdue Tasks`     | No enabled, *scheduled* task is past its next-execution time + grace period.    |
| `Failed Tasks`      | No enabled task recorded an execution failure.                                  |

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

Tune the thresholds to your cron frequency: `heartbeatThreshold` should comfortably
exceed how often cron runs the scheduler, and `overdueThreshold` should absorb the
expected runtime of your longest scheduled task.

## Example output

```json
{
  "name": "Scheduler",
  "healthy": false,
  "reason": "One or more scheduler health checks failed. See sub-results for details.",
  "subResults": [
    { "name": "Scheduler Execution", "healthy": true, "reason": "Scheduler last ran at 2026-06-08T10:00:00+00:00." },
    { "name": "Overdue Tasks", "healthy": true, "reason": "No task is overdue." },
    { "name": "Failed Tasks", "healthy": false, "reason": "1 task reported an execution failure: ..." }
  ]
}
```
