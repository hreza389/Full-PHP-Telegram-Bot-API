<?php

/**
 * Telegram Bot Framework - Main Entry Point
 * 
 * A modern, modular Telegram Bot framework with MySQL support.
 * 
 * @package TelegramBot
 * @version 2.0.0
 */

// Error reporting (disable in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Define base path
define('BASE_PATH', __DIR__);

// Autoloader
spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'TelegramBot\\';
    
    // Base directory for the namespace prefix
    $baseDir = BASE_PATH . '/src/';
    
    // Check if class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
$config = require BASE_PATH . '/config/config.php';

// Initialize core services
use TelegramBot\Core\Config as AppConfig;
use TelegramBot\Core\Logger;
use TelegramBot\Database\Database;
use TelegramBot\Bot\TelegramBot;

// Initialize database
$db = new Database($config['database']);

// Initialize logger
$logger = new Logger();
$logger->setDatabase($db);

// Example: Get default bot token from config or database
$botToken = $config['bot']['token'];

if (empty($botToken)) {
    // Try to get first active bot from database
    $bots = $db->getAllBots(true);
    if (!empty($bots)) {
        $botToken = $db->getBotToken($bots[0]['bot_username']);
    }
}

// Initialize bot if token is available
$bot = null;
if (!empty($botToken)) {
    $bot = new TelegramBot($botToken);
    $bot->setLogger($logger);
    $bot->setDatabase($db);
}

// Example usage
echo "Telegram Bot Framework v" . $config['app']['version'] . "\n";
echo "=====================================\n\n";

// Show database stats
$stats = $db->stats();
echo "Database Statistics:\n";
foreach ($stats['tables'] as $table => $count) {
    echo "  - {$table}: {$count} records\n";
}
echo "\nTotal Records: {$stats['total_records']}\n";
echo "Database Size: {$stats['database_size']}\n\n";

// Show available bots
$bots = $db->getAllBots(false);
if (!empty($bots)) {
    echo "Registered Bots:\n";
    foreach ($bots as $botInfo) {
        $status = $botInfo['is_active'] ? '✓ Active' : '✗ Inactive';
        echo "  - @{$botInfo['bot_username']} ({$status})\n";
    }
} else {
    echo "No bots registered yet.\n";
    echo "Add a bot via the control panel or using:\n";
    echo "  \$db->saveBot('YOUR_BOT_TOKEN', 'your_bot_username');\n";
}

echo "\n=====================================\n";
echo "Framework initialized successfully!\n";
