# Installation Guide

This guide covers the installation and initial setup of the TYPO3 Monitoring
Extension.

## 📝 Requirements

Before installing the extension, ensure your system meets these requirements:

- **PHP**: 8.4 or higher
- **TYPO3**: 13.4 or higher
- **HTTPS**: Required for production use
- **Extensions**: `ext-intl` PHP extension (under review)

## 📦 Installation Methods

### Via Composer (Recommended)

The recommended way to install the extension is through Composer:

```bash
composer require mteu/typo3-monitoring
```

### Manual Installation

1. Download the extension from the repository
2. Extract to `typo3conf/ext/typo3_monitoring/` or `packages/typo3_monitoring/`
3. Run the following commands:

```bash
composer install
./vendor/bin/typo3 extension:activate typo3_monitoring
```

## ⚙️ Post-Installation Setup

### 0. Activate the Extension (in non-composer mode)

If not already activated, enable the extension in the TYPO3 backend:

1. Go to **Admin Tools → Extensions**
2. Find `monitoring` in the list
3. Click the **Activate** button

### 1. Configure Extension Settings

Configure the extension in the TYPO3 backend:

1. Navigate to **Admin Tools → Settings → Extension Configuration**
2. Select `monitoring`
3. Configure the following settings:
   - **Endpoint**: The URL path for monitoring (default: `/monitor/health`)
   - **Secret**: A secure secret for HMAC authentication (required for token
     auth)

### 2. Clear Caches

Clear the TYPO3 caches to ensure the configuration takes effect:

```bash
./vendor/bin/typo3 cache:flush
```

Or via the backend:
1. Go to **Admin Tools → Maintenance**
2. Click **Flush TYPO3 and PHP Caches**

## ✅ Verification

### Test the Endpoint

Verify the installation by accessing the monitoring endpoint:

```bash
# If using admin user authentication (logged in as admin)
curl https://yoursite.com/monitor/health

# If using token authentication
curl -H "X-TYPO3-MONITORING-AUTH: your-hmac-token" \
     https://yoursite.com/monitor/health
```

You should receive a JSON response like:

```json
{
  "isHealthy": true,
  "services": {}
}
```

### Check Backend Module

Verify the backend module is available:

1. Log in to the TYPO3 backend as an administrator
2. Navigate to **System → Monitoring**
3. You should see the monitoring overview page

## 🔧 Troubleshooting

### Common Issues

#### Extension Not Found
- Ensure the extension is properly installed via Composer
- Check that the extension is activated in the Extension Manager

#### 404 Error on Endpoint
- Verify the endpoint path in Extension Configuration
- Clear all caches after configuration changes
- Check web server configuration for URL rewriting

#### 403 Forbidden
- Ensure you're accessing the endpoint via HTTPS
- Check that authentication is properly configured
- Verify the secret is set in Extension Configuration

#### Permission Errors
- Check file permissions on the extension directory
- Ensure the web server can read the extension files

## 👆 Next Steps

After successful installation:

1. [Configure the monitoring endpoint](configuration.md)
2. [Set up authentication](authorization.md)
3. [Create custom monitoring providers](providers.md)
4. [Test the API](api.md)

## 🗑️ Uninstallation

To remove the extension:

1. Deactivate in the Extension Manager
2. Remove via Composer: `composer remove mteu/typo3-monitoring`
3. Clear caches: `./vendor/bin/typo3 cache:flush`

If the extension is configured to use the database backend, caching tables will
have been created. Update the TYPO3 database schema in that case to get rid of
those.

```bash
./vendor/bin/typo3 database:updateschema <args>
```
