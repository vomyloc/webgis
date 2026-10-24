<?php
/**
 * Example Configuration
 * Copy this file to config.local.php and fill in your values.
 * DO NOT commit config.local.php to version control.
 */

return [
    "database" => [
        "host" => "localhost",
        "port" => "5432",
        "database" => "Baitap_21092026",
        "username" => "postgres",
        "password" => "your_password_here",
    ],
    "geoserver" => [
        "url" => "http://localhost:8181/geoserver",
        "workspace" => "dong_thanh",
        "layer" => "thua_dat_dong_thanh",
    ],
    "google_oauth" => [
        "client_id" => "your-client-id.apps.googleusercontent.com",
        "client_secret" => "your-client-secret",
        "redirect_uri" => "https://yourdomain.com/google_callback.php",
    ],
    "app" => [
        "env" => "production",
        "debug" => false,
        "timezone" => "Asia/Ho_Chi_Minh",
    ],
];
