<?php

/**
 * Configuration Manager
 * 
 * Centralized configuration management for the Telegram Bot Framework.
 * All settings are controlled from this file or can be overridden via environment variables.
 * 
 * @package TelegramBot\Config
 * @version 2.0.0
 */

return [
    // ==================== DATABASE CONFIGURATION ====================
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE') ?: 'telegram_bot',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => getenv('DB_PREFIX') ?: '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // ==================== BOT CONFIGURATION ====================
    'bot' => [
        // Default bot token (can be overridden per-bot in database)
        'token' => getenv('BOT_TOKEN') ?: '',
        
        // Bot API settings
        'api_url' => 'https://api.telegram.org/bot',
        'timeout' => 30,
        'local_server' => false,
        'local_server_url' => '',
        
        // Update handling
        'update_mode' => 'webhook', // 'webhook' or 'polling'
        'allowed_updates' => null, // null = all updates
        'drop_pending_updates' => false,
        
        // Webhook settings
        'webhook' => [
            'url' => getenv('WEBHOOK_URL') ?: '',
            'certificate' => '',
            'max_connections' => 40,
            'secret_token' => getenv('WEBHOOK_SECRET') ?: '',
            'ip_address' => '',
        ],
        
        // Polling settings
        'polling' => [
            'limit' => 100,
            'timeout' => 30,
            'offset' => null,
        ],
    ],

    // ==================== LOGGING CONFIGURATION ====================
    'logging' => [
        // Enable/disable logging
        'enabled' => true,
        
        // Log directory path (relative to project root or absolute)
        'path' => getenv('LOG_PATH') ?: __DIR__ . '/../storage/logs',
        
        // Minimum log level (0=DEBUG, 1=INFO, 2=NOTICE, 3=WARNING, 4=ERROR, 5=CRITICAL, 6=ALERT, 7=EMERGENCY)
        'level' => getenv('LOG_LEVEL') ?: 1,
        
        // Maximum log file size in bytes before rotation (10MB default)
        'max_file_size' => 10485760,
        
        // Number of rotated log files to keep
        'max_files' => 5,
        
        // Log channels
        'channels' => ['file', 'database'], // 'file', 'database', or both
        
        // Sensitive data masking
        'mask_sensitive' => true,
        'sensitive_keys' => [
            'token', 'password', 'secret', 'api_key', 'apikey',
            'auth', 'credential', 'private_key', 'access_token'
        ],
    ],

    // ==================== ADMIN PANEL CONFIGURATION ====================
    'admin' => [
        // Admin panel enabled
        'enabled' => true,
        
        // Admin password (CHANGE THIS!)
        'password' => getenv('ADMIN_PASSWORD') ?: 'admin123',
        
        // Session timeout in seconds (30 minutes default)
        'session_timeout' => 1800,
        
        // Allowed IP addresses (empty = all allowed)
        'allowed_ips' => [],
        
        // Two-factor authentication (future feature)
        'two_factor_enabled' => false,
    ],

    // ==================== CACHE CONFIGURATION ====================
    'cache' => [
        // Cache driver: 'database', 'file', 'memory'
        'driver' => 'database',
        
        // File cache directory
        'path' => __DIR__ . '/../storage/cache',
        
        // Default TTL in seconds
        'ttl' => 3600,
        
        // Prefix for cache keys
        'prefix' => 'tgbot_',
    ],

    // ==================== BROADCAST CONFIGURATION ====================
    'broadcast' => [
        // Delay between messages in microseconds
        'delay' => 50000, // 50ms
        
        // Maximum concurrent broadcasts
        'max_concurrent' => 30,
        
        // Retry failed sends
        'retry_failed' => true,
        'max_retries' => 3,
    ],

    // ==================== CRON JOBS CONFIGURATION ====================
    'cron' => [
        // Enable cron jobs
        'enabled' => true,
        
        // Timezone for cron execution
        'timezone' => 'UTC',
        
        // Lock file directory
        'lock_dir' => __DIR__ . '/../storage/locks',
    ],

    // ==================== MIDDLEWARE CONFIGURATION ====================
    'middleware' => [
        // Global middleware stack
        'global' => [
            // Add middleware class names here
        ],
        
        // Per-bot middleware
        'bots' => [],
    ],

    // ==================== ERROR HANDLING ====================
    'errors' => [
        // Display errors (disable in production!)
        'display' => getenv('APP_DEBUG') === 'true',
        
        // Log errors
        'log' => true,
        
        // Report to admin
        'report_to_admin' => false,
        'admin_chat_id' => '',
    ],

    // ==================== SECURITY ====================
    'security' => [
        // Verify webhook secret token
        'verify_webhook_token' => true,
        
        // Validate update source IP
        'validate_ip' => false,
        
        // Rate limiting
        'rate_limit' => [
            'enabled' => false,
            'max_requests' => 100,
            'time_window' => 60, // seconds
        ],
    ],

    // ==================== APPLICATION ====================
    'app' => [
        'name' => 'Telegram Bot Framework',
        'version' => '2.0.0',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => 'UTC',
        'locale' => 'en',
    ],
];
