<?php
/**
 * Database Configuration
 * Environment-based configuration for development and production
 */

// Get environment
$env = getenv('APP_ENV') ?: 'development';

if ($env === 'production') {
    // Production configuration
    $db_config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'name' => getenv('DB_NAME') ?: 'ent_clinic',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4'
    ];
} else {
    // Development configuration (XAMPP local) — allow environment overrides for CI
    $db_config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'name' => getenv('DB_NAME') ?: 'ent_clinic',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4'
    ];
}

// API Configuration
$api_config = [
    'base_url' => getenv('API_BASE_URL') ?: 'http://localhost/ENT-clinic-online/ent-app/public',
    'debug' => $env === 'development',
    'cors_enabled' => true,
    'cors_origins' => ['http://localhost:5173', 'http://localhost:8000', 'http://localhost']
];

// Session Configuration
$session_config = [
    'lifetime' => 3600, // 1 hour
    'secure' => $env === 'production',
    'http_only' => true,
    'same_site' => 'Lax'
];

define('DB_CONFIG', $db_config);
define('API_CONFIG', $api_config);
define('SESSION_CONFIG', $session_config);
define('ENV', $env);
// Security: disallow header-based auth by default in production/dev.
// Set to true only for development/debugging purposes.
// TEMPORARY: Enabled for fixing session forwarding issue
define('ALLOW_HEADER_AUTH', true);
