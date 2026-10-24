# Configuration Folder

This folder contains all application configuration files.

## Files

- `config.php` - Main configuration loader (loads all other configs)
- `database.php` - PostgreSQL/PostGIS database configuration
- `geoserver.php` - GeoServer WMS configuration
- `google_oauth.php` - Google OAuth 2.0 / OpenID Connect configuration
- `app.php` - General application settings (session, CSRF, auth, map, pagination)
- `config.example.php` - Example configuration (copy to `config.local.php` and customize)

## Usage

```php
// Load all config
require_once __DIR__ . "/config/config.php";

// Access config values
$dbHost = config("database.host");
$geoserverUrl = config("geoserver.url");
$googleClientId = config("google_oauth.client_id");
$appDebug = config("app.debug");
```

## Environment Variables

All configuration values can be overridden via environment variables:

### Database
- `WEBGIS_DB_HOST` - Database host (default: localhost)
- `WEBGIS_DB_PORT` - Database port (default: 5432)
- `WEBGIS_DB_NAME` - Database name (default: Baitap_21092026)
- `WEBGIS_DB_USER` - Database user (default: postgres)
- `WEBGIS_DB_PASSWORD` - Database password (default: 1234)

### GeoServer
- `WEBGIS_GEOSERVER_URL` - GeoServer base URL (default: http://localhost:8181/geoserver)
- `WEBGIS_GEOSERVER_WORKSPACE` - Workspace name (default: dong_thanh)
- `WEBGIS_GEOSERVER_LAYER` - Layer name (default: thua_dat_dong_thanh)

### Google OAuth
- `WEBGIS_GOOGLE_CLIENT_ID` - OAuth Client ID
- `WEBGIS_GOOGLE_CLIENT_SECRET` - OAuth Client Secret
- `WEBGIS_GOOGLE_REDIRECT_URI` - OAuth Redirect URI

### App
- `WEBGIS_APP_ENV` - Environment (development/production)
- `WEBGIS_APP_DEBUG` - Debug mode (true/false)

## Security

- Never commit `config.local.php` or any file with real credentials
- Use environment variables in production
- The `.htaccess` file protects sensitive config files from web access
