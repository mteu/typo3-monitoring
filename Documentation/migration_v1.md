# Migration Guide

## Adding `isEnabled()` to `MonitoringProvider`

The `MonitoringProvider` interface gains a new method:

```php
public function isEnabled(): bool;
```

This is a **breaking change** for custom providers: every class implementing
`MonitoringProvider` (directly or via `CacheableMonitoringProvider`) must now
provide an `isEnabled()` method. Providers shipped with the extension are already
updated.

### Why

Previously a provider was either "on" or "off" through `isActive()` alone, which
conflated two different questions:

- **Should this provider run?** — operator intent (a configuration toggle).
- **Can this provider run right now?** — runtime readiness (a dependency is
  installed, an extension is loaded, …).

Splitting them lets the backend show *enabled but not ready* as a configuration
mismatch instead of silently hiding the provider, and gives operators a real
kill-switch.

### What changed at runtime

`isEnabled()` is now an authoritative kill-switch. A provider runs only when

```
isEnabled() && isActive()
```

is true — on the health endpoint, the CLI command, and the reporter alike. A
provider that returns `false` from `isEnabled()` is **never executed**, even if
its `isActive()` returns `true`.

### How to update your provider

Implement `isEnabled()` to express operator intent, and (optionally) narrow
`isActive()` to AND-in technical preconditions.

**Minimal** — always on, behaves exactly as before:

```php
public function isEnabled(): bool
{
    return true;
}

// Keep your existing isActive(), or default it to isEnabled():
public function isActive(): bool
{
    return $this->isEnabled();
}
```

If your old `isActive()` already mixed in a precondition, keep that and gate it
behind `isEnabled()`:

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

That is your choice — a hardcoded `true`, an environment variable, a YAML/PHP
settings file, a database flag, or extension configuration.

**Recommended:** back it with a typed extension-configuration object so an
operator can toggle the provider without a deployment in instances with a writable `settings.php`|`additional.php`. The shipped
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

Here `$this->configuration` is a typed object mapped from the extension
configuration (`SchedulerProviderConfiguration`), keeping the configuration read
in one strongly-typed place.

See [providers.md](providers.md#enablement-and-activation) for the full
enablement-vs-activation model.
