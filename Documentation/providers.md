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

### Provider with Dependencies

Inject TYPO3 services into your provider:

```php
<?php
declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

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

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable('pages');
            $result = $connection->executeQuery('SELECT 1');
            $result->fetchOne();

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: true,
            );
        } catch (\Exception $e) {
            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
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

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        $subResults = [];

        // Check component A
        $subResults[] = $this->checkComponentA();

        // Check component B
        $subResults[] = $this->checkComponentB();

        // Overall health is true if all sub-components are healthy
        $isHealthy = array_reduce(
            $subResults,
            fn(bool $carry, Result $result): bool => $carry &&
                $result->isHealthy(),
            true
        );

        return new MonitoringResult(
            name: $this->getName(),
            isHealthy: $isHealthy,
            subResults: $subResults
        );
    }

    private function checkComponentA(): MonitoringResult
    {
        // Check component A logic
        return new MonitoringResult('ComponentA', true);
    }

    private function checkComponentB(): MonitoringResult
    {
        // Check component B logic
        return new MonitoringResult('ComponentB', true);
    }
}
```

### Conditional Activation

```php
public function isActive(): bool
{
    // Only active if extension loaded
    return ExtensionManagementUtility::isLoaded('my_extension');
    
    // Or environment-specific
    return GeneralUtility::getApplicationContext()->isProduction();
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

