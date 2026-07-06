<?php

/**
 * Telegram Bot Webhook Handler
 * 
 * Handles incoming webhook updates from Telegram.
 */

// Error reporting (disable in production)
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('BASE_PATH', __DIR__);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'TelegramBot\\';
    $baseDir = BASE_PATH . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use TelegramBot\Core\Config;
use TelegramBot\Core\Logger;
use TelegramBot\Database\Database;
use TelegramBot\Bot\TelegramBot;

// Load configuration
$config = require BASE_PATH . '/config/config.php';

// Initialize database
$db = new Database($config['database']);

// Initialize logger
$logger = new Logger();
$logger->setDatabase($db);

// Get update data
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(400);
    exit('Invalid update');
}

// Log the update
$logger->logUpdate($update);

// Save update to database
if (isset($update['update_id'])) {
    $db->insert('updates', [
        'update_id' => $update['update_id'],
        'update_data' => json_encode($update),
    ]);
}

// Extract chat and user info for logging/storage
$chatId = null;
$userId = null;

if (isset($update['message']['chat']['id'])) {
    $chatId = $update['message']['chat']['id'];
} elseif (isset($update['callback_query']['message']['chat']['id'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
}

if (isset($update['message']['from']['id'])) {
    $userId = $update['message']['from']['id'];
} elseif (isset($update['callback_query']['from']['id'])) {
    $userId = $update['callback_query']['from']['id'];
}

// Store user and chat if available
if ($userId && isset($update['message']['from'])) {
    $db->saveUser($update['message']['from']);
}

if ($chatId && isset($update['message']['chat'])) {
    $db->saveChat($update['message']['chat']);
}

// Here you would add your bot logic
// For now, we just acknowledge the update

http_response_code(200);
echo 'OK';
