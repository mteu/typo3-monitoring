# Provider Development Guide

Providers perform monitoring checks and are auto-discovered via `#[AutoconfigureTag('monitoring.provider')]`.

## Basic Provider

```php
<?php
declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

#[AutoconfigureTag('monitoring.provider')]
final class MyServiceProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'MyService';
    }

    public function getDescription(): string
    {
        return 'Monitors my custom service';
    }

    public function isEnabled(): bool
    {
        // Operator intent. A hardcoded `return true` is fine to be on by default;
        // otherwise back it with whatever fits your deployment — extension
        // configuration, an environment variable, a YAML/PHP settings file, a
        // database flag.
        //
        // Note: a provider whose `isEnabled()` returns `false` is never executed —
        // not on the health endpoint, the CLI, nor the reporter — regardless of
        // what `isActive()` returns.
        return true;
    }

    public function isActive(): bool
    {
        // Enforce technical preconditions as needed, or delegate to `isEnabled()`
        // by returning true.
        return ExtensionManagementUtility::isLoaded('required_ext')
            && Environment::getContext()->isProduction();
    }

    public function execute(): Result
    {
        try {
            return new MonitoringResult($this->getName(), Status::Healthy);
        } catch (\Exception $e) {
            return new MonitoringResult($this->getName(), Status::Unhealthy, $e->getMessage());
        }
    }
}
```

### Reporting a status

`MonitoringResult` takes a `mteu\Monitoring\Result\Status` — one of
`Status::Healthy`, `Status::Unhealthy`, or `Status::Degraded`:

```php
use mteu\Monitoring\Result\Status;

return new MonitoringResult($this->getName(), Status::Degraded, 'Cache hit rate is low');
```

A `degraded` result keeps the endpoint at HTTP 200 (it is not an outage) but is
surfaced distinctly in the JSON response and the backend module. When a result
has sub-results, its overall status is aggregated automatically as the worst of
its own status and all sub-statuses.

## Enablement and activation

A provider is only executed when **both** gates return `true`. They answer
different questions and are intentionally separate:

- **`isEnabled()` — operator intent (the kill-switch).** Whether someone has
  switched this provider on. Source it however fits your deployment: a hardcoded
  `return true`, a typed extension-configuration object (as the shipped
  `SchedulerProvider` does), an environment variable, a settings file, or a
  database flag. A provider whose `isEnabled()` returns `false` is never
  executed — not on the health endpoint, the CLI, nor the reporter — regardless
  of `isActive()`.
- **`isActive()` — runtime readiness.** Whether the technical preconditions for
  a meaningful check are met right now: a required extension is loaded, a
  dependency is reachable, the context is production. When a precondition is
  unmet, return `false` so the provider is skipped instead of reporting a
  misleading failure.

A provider that is enabled but not active is simply skipped. Delegating
`isActive()` to `isEnabled()` (`return $this->isEnabled();`) is fine when there
are no technical preconditions to enforce.

## Advanced Features

### Caching
Implement `CacheableMonitoringProvider` for expensive operations:

```php
use mteu\Monitoring\Provider\CacheableMonitoringProvider;

#[AutoconfigureTag('monitoring.provider')]
final class ExpensiveProvider implements CacheableMonitoringProvider
{
    // ...plus the MonitoringProvider methods shown above
    // (getName(), getDescription(), isEnabled(), isActive(), execute()).

    public function getCacheKey(): string
    {
        // Any stable identifier that is safe as a cache key (a-z, 0-9,
        // underscore/hyphen). Backslashes are sanitized by the handler, but
        // keeping the key simple avoids surprises.
        return 'my_service_health';
    }

    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }
}
```

#### Cache Expiration Behavior

Cached results are automatically managed by the monitoring system:

- Automatic Expiration Checks: On each request, cached results are checked for expiration
- Automatic Cleanup: When a cached result expires, it is automatically removed from cache
- Fresh Execution: After expiration, the provider's `execute()` method is called to generate fresh results
- Re-caching: The new result is automatically cached according to the provider's `getCacheLifetime()`

This ensures monitoring results are always fresh while maintaining performance benefits. You don't need to handle cache expiration manually - the system handles it transparently.

### Provider with Dependencies

Inject TYPO3 services into your provider:

```php
<?php
declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Result\Result;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Silly example since a missing DB connection would fail in bootstrapping already.
 */
#[AutoconfigureTag('monitoring.provider')]
final class DatabaseConnectionProvider implements MonitoringProvider
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    public function getName(): string
    {
        return 'DatabaseConnection';
    }

    public function getDescription(): string
    {
        return 'Monitors database connectivity';
    }

    public function isEnabled(): bool
    {
        return $this->extensionConfiguration->isEnabled();
    }

    public function isActive(): bool
    {
        return $this->isEnabled();
    }

    public function execute(): Result
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable('pages');
            $result = $connection->executeQuery('SELECT 1');
            $result->fetchOne();

            return new MonitoringResult(
                name: $this->getName(),
                status: Status::Healthy,
            );
        } catch (\Exception $e) {
            return new MonitoringResult(
                name: $this->getName(),
                status: Status::Unhealthy,
                reason: 'Database connection failed: ' . $e->getMessage()
            );
        }
    }
}
```

### Sub-Component Monitoring

Monitor multiple sub-components within a single provider:

```php
<?php
declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Result\Result;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monitoring.provider')]
final class MultiComponentProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'MultiComponent';
    }

    public function getDescription(): string
    {
        return 'Monitors multiple sub-components';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isActive(): bool
    {
        return $this->isEnabled();
    }

    public function execute(): Result
    {
        $subResults = [];

        // Check component A
        $subResults[] = $this->checkComponentA();

        // Check component B
        $subResults[] = $this->checkComponentB();

        // The parent status is aggregated from the sub-results automatically
        // (the worst sub-status wins), so the parent can simply start healthy.
        return new MonitoringResult(
            name: $this->getName(),
            status: Status::Healthy,
            subResults: $subResults
        );
    }

    private function checkComponentA(): MonitoringResult
    {
        // Check component A logic
        return new MonitoringResult('ComponentA', Status::Healthy);
    }

    private function checkComponentB(): MonitoringResult
    {
        // Check component B logic
        return new MonitoringResult('ComponentB', Status::Healthy);
    }
}
```

## Example
Find a set of examples returning various `Result` combinations in [Documentation/Providers/Example](Providers/Example).
