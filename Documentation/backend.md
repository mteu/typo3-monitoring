# Backend Module

The backend module provides an interface to view and manage monitoring providers.

## Access

Navigate to **System → Monitoring** in the TYPO3 backend.

## Interface

### Provider List

Each provider displays:
- **Name**: Provider display name
- **Status**: Health status (Healthy/Unhealthy/Inactive)
- **Description**: Provider description
- **Cache Status**: Whether cached results are available

### Status Indicators

| Badge         | Color | Meaning                          |
|---------------|-------|----------------------------------|
| **Healthy**   | Green | Provider is functioning normally |
| **Unhealthy** | Red   | Provider has detected issues     |
| **Inactive**  | Gray  | Provider is disabled             |
| **Cached**    | Blue  | Provider results are cached      |

### Cache Management

For cacheable providers:
- View cache lifetime and expiration
- Flush provider cache via "Flush Cache" link
- Flash messages use queue identifier: `'typo3_monitoring'`

### Authorization Information

View configured authorizers:
- Authorizer class names
- Priority order (higher = evaluated first)
- Current HMAC authentication token

### Sub-Results

For providers with sub-components:
- Individual component health status
- Detailed status information

## Templates

Customize via Fluid templates:
- Main: `Resources/Private/Templates/Backend/Monitoring.html`
- Provider List: `Resources/Private/Partials/Backend/Provider/List.html`
- Provider Item: `Resources/Private/Partials/Backend/Provider/Item.html`
- Result List: `Resources/Private/Partials/Backend/Result/List.html`
