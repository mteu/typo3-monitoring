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
- **Result Handling**: Aggregates provider outputs and formats responses

## Extensibility

Both providers and authorizers use automatic service discovery through Symfony DI attributes (`#[AutoconfigureTag]`). No manual service registration required.