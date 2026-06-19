# Migrating to `EXT:monitoring` v1.0

The first stable release introduces breaking changes. This guide seeks to help you smoothen the transition:

## Table of Contents
1. [Adding `isEnabled()` to `MonitoringProvider`](#adding-isenabled-to-monitoringprovider)
2. [Three-state health `Status`](#three-state-health-status)

---

## 1. Adding `isEnabled()` to `MonitoringProvider`

**Breaking change.** The `MonitoringProvider` interface gains:

```php
public function isEnabled(): bool;
```

Every class implementing `MonitoringProvider` — directly or via
`CacheableMonitoringProvider` — must provide this method. Shipped providers are
already updated.

### Why

`isActive()` previously mixed two concerns:

| Question                         | Meaning                                                                  |
|----------------------------------|--------------------------------------------------------------------------|
| Should this provider run?        | Operator intent — a configuration toggle.                                |
| Can this provider run right now? | Runtime readiness — a dependency is installed, an extension is loaded, … |

Splitting them lets the backend surface *enabled but not ready* as a
configuration mismatch rather than silently hiding the provider.

`isEnabled()` is now an authoritative kill-switch. A provider runs only when
both conditions are true:

```
isEnabled() && isActive()
```

This applies to the health endpoint, the CLI command, and the reporter. A
provider returning `false` from `isEnabled()` is **never executed**, regardless
of `isActive()`.

### How to update your provider

**Minimal — always on, same behaviour as before:**

```php
public function isEnabled(): bool
{
    return true;
}

public function isActive(): bool
{
    return $this->isEnabled();
}
```

**With a technical precondition in `isActive()`:**

```php
// Before
public function isActive(): bool
{
    return ExtensionManagementUtility::isLoaded('my_extension');
}

// After
public function isEnabled(): bool
{
    return true; // or read your configuration — see below
}

public function isActive(): bool
{
    return $this->isEnabled()
        && ExtensionManagementUtility::isLoaded('my_extension');
}
```

### Where `isEnabled()` should read from

Any source works: a hardcoded `true`, an environment variable, a settings file,
or a database flag.

**Recommended:** back it with a typed extension-configuration object so an
operator can toggle the provider without a deployment. The shipped
`SchedulerProvider` is the reference implementation:

```php
public function isEnabled(): bool
{
    return $this->configuration->isEnabled();
}

public function isActive(): bool
{
    return $this->isEnabled() && ExtensionManagementUtility::isLoaded('scheduler');
}
```

`$this->configuration` is a typed object mapped from extension configuration
(`SchedulerProviderConfiguration`), keeping the configuration read in one
strongly-typed place.

See [providers.md](providers.md#enablement-and-activation) for the full
enablement-vs-activation model.

---

## 2. New three-state health `Status`

**Breaking change.** The binary healthy/unhealthy model is replaced by a
`Status` enum (`mteu\Monitoring\Result\Status`) with three ordered cases:

```
Status::Healthy  <  Status::Degraded  <  Status::Unhealthy
```

`Degraded` means *operational but attention-worthy*. It keeps the endpoint at
HTTP 200 and is not treated as an outage.

This change affects three places: the `Result` interface, the `MonitoringResult`
constructor, and the endpoint's JSON response.

### `Result::getStatus()`

The `Result` interface gains:

```php
public function getStatus(): Status;
```

`isHealthy()` is retained as a derived convenience meaning `status !==
Unhealthy`. If your provider only *returns* a `MonitoringResult` rather than
implementing `Result` directly, you are unaffected here.

### `MonitoringResult` constructor

The second parameter changed from `bool $isHealthy` to `Status $status`. The
`setHealthy(bool)` mutator is replaced by `setStatus(Status)`.

```php
use mteu\Monitoring\Result\Status;

// Before
return new MonitoringResult($this->getName(), true);
return new MonitoringResult($this->getName(), false, $reason);
$result->setHealthy(false);

// After
return new MonitoringResult($this->getName(), Status::Healthy);
return new MonitoringResult($this->getName(), Status::Unhealthy, $reason);
$result->setStatus(Status::Unhealthy);
```

A parent result's status is still aggregated automatically as the worst of its
own status and all sub-results.

### Endpoint response shape

External consumers must update. Key changes:

- Top-level `isHealthy` boolean → `status` string (`healthy` | `degraded` | `unhealthy`).
- Sub-results are now `{ "status": … }` objects instead of bare strings.
- HTTP 503 is returned only for `unhealthy`; `degraded` returns 200.

**Before:**

```json
{
    "isHealthy": false,
    "services": {
        "Scheduler": {
            "status": "unhealthy",
            "subResults": {
                "Heartbeat": "healthy",
                "Failed Tasks": "unhealthy"
            }
        }
    }
}
```

**After:**

```json
{
    "status": "unhealthy",
    "services": {
        "Scheduler": {
            "status": "unhealthy",
            "subResults": {
                "Heartbeat": { "status": "healthy" },
                "Failed Tasks": { "status": "unhealthy" }
            }
        }
    }
}
```

`Result::toArray()` and `jsonSerialize()` likewise emit `status` instead of
`isHealthy`. See [api.md](api.md) for the full response reference.
