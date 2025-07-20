# Provider Development Guide

Providers are the core components that perform actual monitoring checks.
Once they implement the `MonitoringProvider` interface they are automatically
discovered through the dependency injection container.

## 🔨 Basic Provider Implementation

### Simple Provider

Create a basic monitoring provider:

```php
<?php

declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\Result;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(tag: 'monitoring.provider')]
final class MyServiceProvider implements MonitoringProvider
{
    public function getName(): string
    {
        return 'MyService';
    }

    public function getDescription(): string
    {
        return 'Monitors the health of my custom service';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        try {
            // Perform your monitoring check here
            $isHealthy = $this->checkServiceHealth();

            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: $isHealthy,
                reason: $isHealthy ? null : 'Service is not responding'
            );
        } catch (\Exception $e) {
            return new MonitoringResult(
                name: $this->getName(),
                isHealthy: false,
                reason: 'Error: ' . $e->getMessage()
            );
        }
    }

    private function checkServiceHealth(): bool
    {
        // Your actual monitoring logic
        return true;
    }
}
```

### Service Registration

The provider is automatically registered through the
`#[AutoconfigureTag(tag: 'monitoring.provider')]` attribute. No additional
configuration is required.

## 🚀 Advanced Provider Features

### Cacheable Provider

For expensive operations, implement the `CacheableMonitoringProvider` interface:

```php
<?php

declare(strict_types=1);

namespace My\Extension\Provider;

use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(tag: 'monitoring.provider')]
final class ExpensiveServiceProvider implements CacheableMonitoringProvider
{
    use SlugifyCacheKeyTrait;

    public function getName(): string
    {
        return 'ExpensiveService';
    }

    public function getDescription(): string
    {
        return 'Monitors an expensive service with caching';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function execute(): Result
    {
        // Expensive operation that will be cached
        $result = $this->performExpensiveCheck();

        return new MonitoringResult(
            name: $this->getName(),
            isHealthy: $result,
        );
    }

    public function getCacheKey(): string
    {
        return $this->slugifyCacheKey($this->getName());
    }

    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }

    // demo .. don't actually do that in production ;-)
    private function performExpensiveCheck(): bool
    {
        // Simulate expensive operation
        sleep(2);
        return true;
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

#[AutoconfigureTag(tag: 'monitoring.provider')]
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

#[AutoconfigureTag(tag: 'monitoring.provider')]
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

## 📦 Built-in Providers

The monitoring extension includes several built-in providers:

### SelfCareProvider

The `SelfCareProvider` monitors the health of the monitoring middleware itself by making HTTP requests to its own health endpoint. This provides meta-level monitoring to ensure the monitoring system is accessible and responding correctly.

**Configuration:**
```yaml
# ext_conf_template.txt
provider {
    mteu\Monitoring\Provider\SelfCareProvider {
        enabled=1
    }
}
```
**Note**: The SelfCareProvider is automatically excluded from HTTP middleware execution to avoid redundancy and unnecessary performance overhead. A successful HTTP response from the monitoring endpoint already proves the system is working.

## ⚙️ Configuration

### Conditional Activation

Make providers conditionally active:

```php
public function isActive(): bool
{
    // Only active if specific extension is loaded
    return \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded(
        'my_extension'
    );
}
```

### Environment-Specific Providers

```php
public function isActive(): bool
{
    $context = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext();

    // Only active in production
    return $context->isProduction();
}
```

## ✨ Best Practices

### Performance

- Keep monitoring checks lightweight
- Use caching for expensive operations
- Implement timeouts for external calls
- Avoid blocking operations

### Security

- Don't expose sensitive information in error messages
- Validate all inputs
- Use secure communication for external checks
- Implement proper authentication for external services

### Reliability

- Handle exceptions gracefully
- Provide meaningful error messages
- Use appropriate timeouts
- Implement retry logic for transient failures

### Monitoring

- Log important events
- Use structured logging
- Monitor the monitoring system itself
- Implement alerting for critical failures

## ➡️ Next Steps

After creating providers:

1. [Set up authorization](authorization.md)
2. [Test the API](api.md)
3. [Configure monitoring systems](api.md#monitoring-system-integration)
4. [Use the backend module](backend.md) to manage providers
