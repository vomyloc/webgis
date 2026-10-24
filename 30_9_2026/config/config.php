<?php
/**
 * Main Configuration Loader
 * Loads all configuration files from the config directory.
 */

$configDir = __DIR__;

$config = [];

// Load all config files
$files = [
    "database" => "database.php",
    "geoserver" => "geoserver.php",
    "google_oauth" => "google_oauth.php",
    "app" => "app.php",
];

foreach ($files as $key => $file) {
    $path = $configDir . DIRECTORY_SEPARATOR . $file;
    if (is_file($path)) {
        $config[$key] = require $path;
    }
}

// Helper function to get nested config
function config($key, $default = null) {
    global $config;
    $keys = explode(".", $key);
    $value = $config;
    foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    return $value;
}

// Auto-start session with config
if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = config("app.session", []);
    session_set_cookie_params([
        "lifetime" => $sessionConfig["lifetime"] ?? 0,
        "path" => $sessionConfig["path"] ?? "/",
        "secure" => $sessionConfig["secure"] ?? false,
        "httponly" => $sessionConfig["httponly"] ?? true,
        "samesite" => $sessionConfig["samesite"] ?? "Lax",
    ]);
    session_start();
}

// Set timezone
date_default_timezone_set(config("app.timezone", "Asia/Ho_Chi_Minh"));

return $config;
