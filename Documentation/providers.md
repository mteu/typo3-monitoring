# Provider Development Guide

Providers perform monitoring checks and are auto-discovered via `#[AutoconfigureTag('monitoring.provider')]`.

## Basic Provider

```php
<?php
declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

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

    public function isActive(): bool
    {
        return true; // or conditional logic
    }

    public function execute(): Result
    {
        try {
            $isHealthy = $this->checkService();
            return new MonitoringResult($this->getName(), $isHealthy);
        } catch (\Exception $e) {
            return new MonitoringResult($this->getName(), false, $e->getMessage());
        }
    }

    private function checkService(): bool
    {
        // Your monitoring logic
        return true;
    }
}
```

## Advanced Features

### Caching
Implement `CacheableMonitoringProvider` for expensive operations:

```php
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;

#[AutoconfigureTag('monitoring.provider')]
final class ExpensiveProvider implements CacheableMonitoringProvider
{
    use SlugifyCacheKeyTrait;

    public function getCacheKey(): string
    {
        return $this->slugifyCacheKey($this->getName());
    }

    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }
}
```

### Dependencies
Inject TYPO3 services via constructor:

```php
public function __construct(
    private readonly ConnectionPool $connectionPool
) {}
```

### Conditional Activation

```php
public function isActive(): bool
{
    // Only active if extension loaded
    return ExtensionManagementUtility::isLoaded('my_extension');
}
```

## Built-in Providers

- **MiddlewareStatusProvider**: Meta-monitoring of the monitoring system itself

## Best Practices

- Keep checks lightweight and fast
- Handle exceptions gracefully
- Use caching for expensive operations
- Don't expose sensitive information in error messages
- Implement proper timeouts for external calls

