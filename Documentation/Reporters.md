# Reporter Development Guide

Reporters **push** monitoring status out (email, Slack, Teams, …) for setups
where no external system scrapes the [HTTP endpoint](Api.md). They are
evaluated by the `ReportDispatcher`, which is triggered on demand via the
`monitoring:report` CLI command (cron-friendly), not on every request.

Reporters are auto-discovered via `#[AutoconfigureTag('monitoring.reporter')]`.

## The dispatch flow

On each run the dispatcher:

1. executes all active providers and builds the current failure set;
2. compares it against the persisted state (the dedup state machine) and
   produces a single `NotificationDecision`:
   - `Unhealthy` — a new outage or a *changed* failure set (notify now);
   - `Reminder` — same failure set, reminder interval elapsed;
   - `Recovered` — status returned to healthy after an outage (one-off);
   - `None` — nothing to send (healthy, or throttled within the interval);
3. fans the decision out to every active reporter whose **threshold** accepts it.

State lives in a dedicated `typo3_monitoring_notification` cache that is
deliberately outside the `system` cache group, so flushing system caches
cannot make the dispatcher forget it already alerted. A failed delivery does
not advance the reminder clock, so it is retried on the next run.

See [Configuration](Configuration.md) for the `reportDispatcher` settings
(`reminderInterval`, `notifyOnRecovery`, `failOpenOnMissingState`, `notifyFrom`).

## Severity floor (`notifyFrom`)

A provider can report `healthy`, `degraded` or `unhealthy`. The dispatcher
builds its failure set from every result whose status is **at least** the
configured `reportDispatcher.notifyFrom` floor:

| `notifyFrom` | Pages on            | `degraded` behaviour                          |
|--------------|---------------------|-----------------------------------------------|
| `unhealthy`  | `unhealthy` only    | silent — stays HTTP 200, never notifies (default) |
| `degraded`   | `degraded` + `unhealthy` | enters the failure set and notifies      |

The floor is **global**: it is a single severity gate in front of the one
shared dedup/reminder/recovery state machine, not a per-reporter setting.
Lowering it to `degraded` makes degradation page through exactly the same
fingerprint/reminder/recovery machinery as an outage. Recovery (`Recovered`)
then fires when the status drops back **below the floor**, which is not
necessarily fully healthy — the recovery copy is worded accordingly.

This is distinct from the per-reporter **threshold** below: `notifyFrom` decides
*what counts as a failure* (a severity gate), while a reporter's threshold
decides *which decisions* (`Unhealthy` / `Reminder` / `Recovered`) it wants
once that failure has been determined.

## Thresholds

Each reporter declares which decisions it wants via `ReportThreshold`:

| Threshold      | Receives                          |
|----------------|-----------------------------------|
| `always`       | `Unhealthy`, `Reminder`, `Recovered` |
| `unhealthy`    | `Unhealthy`, `Reminder`           |
| `state-change` | `Unhealthy`, `Recovered`          |

> `ReportThreshold::Unhealthy` keeps its name for backward compatibility; read
> it as "the failure decision", where *failure* means a result met the
> `notifyFrom` floor — not literally the `unhealthy` status.

## Custom Reporter

```php
<?php
declare(strict_types=1);

namespace My\Extension\Reporter;

use mteu\Monitoring\Reporter\Reporter;
use mteu\Monitoring\Reporter\ReportContext;
use mteu\Monitoring\Reporter\ReportThreshold;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monitoring.reporter')]
final class MyReporter implements Reporter
{
    public function isActive(): bool
    {
        return true; // e.g. enabled && credentials present
    }

    public function getThreshold(): ReportThreshold
    {
        return ReportThreshold::Unhealthy;
    }

    public function report(ReportContext $context): bool
    {
        // $context->decision, ->results, ->unhealthyProviderNames, ->fingerprint
        // Return true only when delivery succeeded; false makes the
        // dispatcher retry on the next run.
        return $this->send((string)$context->decision->name);
    }

    public function getName(): string
    {
        return 'MyReporter';
    }

    public static function getPriority(): int
    {
        return 0; // higher is reported first
    }

    private function send(string $payload): bool
    {
        // Your delivery logic
        return true;
    }
}
```

## Built-in Reporters

- **EmailReporter**: sends status via TYPO3's mailer, see [EmailReporter](Reporter/EmailReporter.md).

## Best Practices

- Return `false` from `report()` on delivery failure so it is retried.
- Keep credentials out of extension configuration (use TYPO3's `MAIL`
  settings, the Webhooks module, secrets, …).
- Pick the narrowest threshold that fits the channel to avoid noise.
