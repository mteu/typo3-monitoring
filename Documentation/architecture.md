# Architecture Overview

## Entry Points

The monitoring system can be accessed through three channels:

- **HTTP Endpoint**: Configurable endpoint handled by `MonitoringMiddleware`
- **CLI Command**: `typo3 monitoring:run` for automated checks
- **Backend Module**: TYPO3 backend interface for management

## Flow

1. **Request** → **Authorization** → **Provider Execution** → **Response**
2. Authorization uses priority-based authorizer chain
3. Providers perform health checks and return structured results
4. Results are formatted for HTTP (JSON), CLI (text), or backend display

## Key Components

- **MonitoringMiddleware**: PSR-15 middleware handling HTTP requests
- **Authorization Layer**: Multiple strategies with priority ordering
- **Provider System**: Auto-discovered monitoring checks via DI attributes
- **Caching System**: Transparent caching for expensive monitoring operations
- **Result Handling**: Aggregates provider outputs and formats responses

## Caching Architecture

The extension provides sophisticated caching capabilities for expensive monitoring operations:

### Core Components

- **MonitoringCacheManager**: Central cache management with TYPO3 caching framework integration
- **MonitoringExecutionHandler**: Orchestrates provider execution with automatic cache handling
- **CacheableMonitoringProvider**: Interface for providers that support caching
- **CachedMonitoringResult**: Wrapper storing results with expiration metadata
- **SlugifyCacheKeyTrait**: Ensures consistent cache key generation

## Extensibility

Both providers and authorizers use automatic service discovery through Symfony DI attributes (`#[AutoconfigureTag]`). No manual service registration required.
