# Backend Module Guide

The backend module provides a comprehensive interface for administrators to:
- View all registered monitoring providers
- Check provider health status
- Manage provider cache
- View authorization configuration
- Get authentication tokens
- Monitor sub-results from providers

## 🚪 Accessing the Backend Module

1. Log in to the TYPO3 backend as an administrator
2. Navigate to **System → Monitoring**
3. The monitoring overview page will be displayed

## 🕲️ Module Interface

### Main Dashboard

The main dashboard displays:
- **Provider List**: All registered monitoring providers with their status
- **Authorization Information**: Current authorization configuration
- **Health Status**: Overall system health and individual provider status

### Provider Information

Each provider in the list shows:
- **Provider Name**: The display name of the provider
- **Class Name**: The full PHP class name
- **Status**: Health status (Healthy/Unhealthy/Inactive)
- **Description**: Provider description
- **Cache Status**: Whether the provider is cached
- **Sub-Results**: Detailed health information for complex providers

## 🔧 Provider Management

### Provider Status Indicators

The module uses color-coded badges to indicate provider status:

| Badge         | Color | Meaning                          |
|---------------|-------|----------------------------------|
| **Healthy**   | Green | Provider is functioning normally |
| **Unhealthy** | Red   | Provider has detected issues     |
| **Inactive**  | Gray  | Provider is disabled             |
| **Cached**    | Blue  | Provider results are cached      |

### Provider Details

Click on any provider to expand its details panel, which shows:
- **Full Description**: Detailed provider information
- **Cache Information**: Cache lifetime and expiration time
- **Sub-Results**: Detailed status of provider components
- **Cache Management**: Options to flush provider cache

### Cache Management

For cacheable providers, the module provides:

#### Cache Information Display
- **Cache Lifetime**: How long results are cached (in minutes)
- **Cache Expiration**: When the current cache expires
- **Cache Status**: Whether cached results are available

#### Cache Operations
- **Flush Provider Cache**: Clear cache for a specific provider
- **View Cache Expiration**: See when cache expires

#### Flushing Provider Cache

To flush cache for a specific provider:
1. Expand the provider details
2. Click the "Flush Cache" link
3. The system will display a success/error message
4. The page will redirect to show updated cache status

## 🔐 Authorization Overview

The authorization section displays:
- **Configured Authorizers**: List of registered authorization strategies
- **Priority Order**: The order in which authorizers are evaluated
- **Authentication Token**: Current HMAC token for API access
- **Endpoint Configuration**: The configured monitoring endpoint

### Authorization Information

| Field                | Description                                 |
|----------------------|---------------------------------------------|
| **Authorizer Class** | The PHP class name of the authorizer        |
| **Priority**         | Numeric priority (higher = evaluated first) |
| **Status**           | Whether the authorizer is active            |

### Authentication Token

The module displays the current HMAC authentication token for API access:
- **Token Value**: The generated HMAC token
- **Endpoint**: The full monitoring endpoint URL
- **Usage**: Copy-paste ready for API requests

## 📊 Sub-Results Display

For providers that return sub-results (complex providers monitoring multiple
components):

### Sub-Result Information
- **Component Name**: Individual component being monitored
- **Health Status**: Status of each component
- **Details**: Additional information about component health

### Sub-Result Status Indicators
- **✅ Healthy**: Component is functioning normally
- **🚨 Unhealthy**: Component has issues
- **ℹ️ Info**: Additional information available

## ⚙️ Configuration Management

### Provider Configuration

Providers are automatically discovered and cannot be configured through the
backend module. To modify provider behavior:
- Edit provider classes directly
- Modify service configuration
- Use environment-specific activation logic

### Cache Configuration

Cache settings are managed through:
- **Provider Implementation**: Cache lifetime defined in provider code
- **Extension Configuration**: Global cache settings
- **TYPO3 Cache Configuration**: Cache backend configuration

### Authorization Configuration

Authorization is configured through:
- **Extension Configuration**: Secret key and endpoint settings
- **Service Registration**: Authorizer priority and registration
- **Environment Variables**: Secure secret management


## 🎨 Customization

### Template Customization

The backend module uses Fluid templates that can be customized:
- **Main Template**: `Resources/Private/Templates/Backend/Monitoring.html`
- **Provider List**: `Resources/Private/Partials/Backend/Provider/List.html`
- **Provider Item**: `Resources/Private/Partials/Backend/Provider/Item.html`
- **Result List in Provider section**: `Resources/Private/Partials/Backend/Result/List.html`

### Language Customization

Language files can be customized:
- **Backend Labels**: `Resources/Private/Language/locallang.be.xlf`
- **Module Labels**: `Resources/Private/Language/locallang.mod.xlf`

## ➡️ Next Steps

After using the backend module:

1. [Test the API](api.md) with the authentication token
2. [Use CLI commands](command-line.md) for command-line monitoring
3. [Create custom providers](providers.md) for additional monitoring
4. [Configure external monitoring systems](api.md#monitoring-system-integration)
