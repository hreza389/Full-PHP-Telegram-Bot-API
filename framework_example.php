<?php

/**
 * Telegram Bot Framework Example
 * 
 * A comprehensive example demonstrating all framework components:
 * - TelegramBot (API client)
 * - TelegramCache (caching)
 * - TelegramLogger (logging)
 * - TelegramMiddleware (middleware pipeline)
 * - TelegramDB (database)
 * - TelegramCron (scheduler)
 * - TelegramBotDispatcher (event dispatcher)
 * 
 * @package TelegramBot
 */

// Require all framework files
require_once 'TelegramBot.php';
require_once 'TelegramResponse.php';
require_once 'TelegramTypes.php';
require_once 'TelegramCache.php';
require_once 'TelegramLogger.php';
require_once 'TelegramMiddleware.php';
require_once 'TelegramDB.php';
require_once 'TelegramCron.php';
require_once 'TelegramBotDispatcher.php';
require_once 'InputFile.php';
require_once 'Keyboard.php';

// Configuration
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('ADMIN_IDS', [123456789]); // Replace with your Telegram ID
define('CACHE_DIR', '/tmp/telegram_cache');
define('LOG_FILE', '/tmp/telegram_bot.log');
define('DB_FILE', '/tmp/telegram_bot.db');

// Initialize global instances
$telegramBot = new TelegramBot(BOT_TOKEN);
$telegramCache = new TelegramCache(CACHE_DIR);
$telegramLogger = new TelegramLogger(LOG_FILE, TelegramLogger::LEVEL_INFO);
$telegramDB = new TelegramDB(DB_FILE);
$telegramMiddleware = new TelegramMiddleware($telegramBot);
$telegramDispatcher = new TelegramBotDispatcher($telegramBot);
$telegramCron = new TelegramCron($telegramBot);

// Set dependencies
$telegramMiddleware->setCache($telegramCache);
$telegramMiddleware->setLogger($telegramLogger);
$telegramCron->setCache($telegramCache);
$telegramCron->setLogger($telegramLogger);
$telegramCron->setDB($telegramDB);

// ==================== Middleware Setup ====================

$telegramMiddleware->merge([
    TelegramMiddleware::logging(),
    TelegramMiddleware::errorHandler(),
    TelegramMiddleware::spamProtection(2),
    TelegramMiddleware::rateLimit(20, 60)
]);

// ==================== Command Handlers ====================

// /start command
$telegramDispatcher->command('start', function($update, $matches) {
    global $telegramBot, $telegramDB;
    
    $chatId = $update['message']['chat']['id'];
    $user = $update['message']['from'];
    
    // Save user to database
    $telegramDB->saveUser($user);
    
    $text = "👋 Welcome {$user['first_name']}!\n\n";
    $text .= "I'm a powerful Telegram bot built with PHP.\n\n";
    $text .= "Available commands:\n";
    $text .= "/help - Show help message\n";
    $text .= "/settings - Manage settings\n";
    $text .= "/stats - View statistics\n";
    $text .= "/contact - Send contact info";
    
    $keyboard = Keyboard::inline()
        ->row([
            ['text' => '📊 Stats', 'callback_data' => 'stats'],
            ['text' => '⚙️ Settings', 'callback_data' => 'settings']
        ])
        ->row([
            ['text' => '📞 Contact', 'callback_data' => 'contact']
        ])
        ->create();
    
    return $telegramBot->sendMessage($chatId, $text, [
        'reply_markup' => $keyboard
    ]);
});

// /help command
$telegramDispatcher->command('help', function($update, $matches) {
    global $telegramBot;
    
    $chatId = $update['message']['chat']['id'];
    
    $text = "ℹ️ *Bot Help*\n\n";
    $text .= "*General Commands:*\n";
    $text .= "/start - Start the bot\n";
    $text .= "/help - Show this help\n";
    $text .= "/settings - Manage your settings\n";
    $text .= "/stats - View bot statistics\n\n";
    $text .= "*Features:*\n";
    $text .= "• Multi-step conversations\n";
    $text .= "• Inline keyboards\n";
    $text .= "• Database persistence\n";
    $text .= "• Caching system\n";
    $text .= "• Scheduled tasks";
    
    return $telegramBot->sendMessage($chatId, $text, ['parse_mode' => 'Markdown']);
});

// /settings command with conversation
$telegramDispatcher->command('settings', function($update, $matches) {
    global $telegramBot, $telegramCache;
    
    $chatId = $update['message']['chat']['id'];
    $userId = $update['message']['from']['id'];
    
    // Start a conversation
    $telegramCache->set("conversation:{$userId}", [
        'state' => 'settings_menu',
        'chat_id' => $chatId
    ], 300);
    
    $text = "⚙️ *Settings Menu*\n\n";
    $text .= "Choose an option:";
    
    $keyboard = Keyboard::inline()
        ->row([
            ['text' => '🔔 Notifications', 'callback_data' => 'settings_notifications'],
            ['text' => '🌐 Language', 'callback_data' => 'settings_language']
        ])
        ->row([
            ['text' => '❌ Close', 'callback_data' => 'settings_close']
        ])
        ->create();
    
    return $telegramBot->sendMessage($chatId, $text, [
        'parse_mode' => 'Markdown',
        'reply_markup' => $keyboard
    ]);
});

// /stats command
$telegramDispatcher->command('stats', function($update, $matches) {
    global $telegramBot, $telegramDB, $telegramCache;
    
    $chatId = $update['message']['chat']['id'];
    
    $dbStats = $telegramDB->stats();
    $cacheStats = $telegramCache->stats();
    
    $text = "📊 *Bot Statistics*\n\n";
    $text .= "*Database:*\n";
    $text .= "Users: {$dbStats['users']}\n";
    $text .= "Chats: {$dbStats['chats']}\n";
    $text .= "Settings: {$dbStats['settings']}\n\n";
    $text .= "*Cache:*\n";
    $text .= "Items: {$cacheStats['total_items']}\n";
    $text .= "Size: " . round($cacheStats['total_size'] / 1024, 2) . " KB\n";
    $text .= "Valid: {$cacheStats['valid_items']}\n";
    $text .= "Expired: {$cacheStats['expired_items']}";
    
    return $telegramBot->sendMessage($chatId, $text, ['parse_mode' => 'Markdown']);
});

// Admin-only command
$telegramDispatcher->command('admin', function($update, $matches) {
    global $telegramBot;
    
    $chatId = $update['message']['chat']['id'];
    $userId = $update['message']['from']['id'];
    
    if (!in_array($userId, ADMIN_IDS)) {
        return $telegramBot->sendMessage($chatId, '❌ Access denied. Admin only.');
    }
    
    $text = "👨‍💼 *Admin Panel*\n\n";
    $text .= "Welcome, Administrator!";
    
    $keyboard = Keyboard::inline()
        ->row([
            ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast'],
            ['text' => '👥 Users', 'callback_data' => 'admin_users']
        ])
        ->row([
            ['text' => '🧹 Cleanup', 'callback_data' => 'admin_cleanup']
        ])
        ->create();
    
    return $telegramBot->sendMessage($chatId, $text, [
        'parse_mode' => 'Markdown',
        'reply_markup' => $keyboard
    ]);
}, ['admin_only' => true]);

// ==================== Callback Query Handlers ====================

// Stats callback
$telegramDispatcher->callback('stats', function($update) {
    global $telegramBot;
    
    $chatId = $update['callback_query']['message']['chat']['id'];
    
    $text = "📊 Real-time statistics coming soon!";
    
    return $telegramBot->answerCallbackQuery($update['callback_query']['id'], [
        'text' => $text,
        'show_alert' => true
    ]);
});

// Settings callbacks
$telegramDispatcher->callback('settings_*', function($update) {
    global $telegramBot;
    
    $data = $update['callback_query']['data'];
    $chatId = $update['callback_query']['message']['chat']['id'];
    
    switch ($data) {
        case 'settings_notifications':
            $text = "🔔 Notification settings would go here.";
            break;
        case 'settings_language':
            $text = "🌐 Language selection would go here.";
            break;
        case 'settings_close':
            $text = "Settings closed.";
            break;
        default:
            $text = "Unknown setting.";
    }
    
    return $telegramBot->answerCallbackQuery($update['callback_query']['id'], [
        'text' => $text
    ]);
});

// ==================== Message Filters ====================

// Handle text messages
$telegramDispatcher->onMessage(function($update) {
    global $telegramBot, $telegramCache, $telegramDB;
    
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';
    
    // Check for active conversation
    $conversation = $telegramCache->get("conversation:{$userId}");
    
    if ($conversation !== null) {
        // Handle conversation state
        switch ($conversation['state']) {
            case 'settings_menu':
                // Process settings input
                $telegramCache->delete("conversation:{$userId}");
                return $telegramBot->sendMessage($chatId, "✅ Settings updated!");
        }
    }
    
    // Regular message handling
    if (stripos($text, 'hello') !== false) {
        return $telegramBot->sendMessage($chatId, "Hello there! 👋");
    }
    
    return null;
});

// Handle new chat members
$telegramDispatcher->onNewChatMembers(function($update) {
    global $telegramBot;
    
    $chatId = $update['message']['chat']['id'];
    $newMembers = $update['message']['new_chat_members'];
    
    foreach ($newMembers as $member) {
        $text = "👋 Welcome {$member['first_name']} to the group!";
        $telegramBot->sendMessage($chatId, $text);
    }
    
    return true;
});

// ==================== Scheduled Tasks ====================

// Clean up expired conversations every hour
$telegramCron->addConversationCleanup('hourly');

// Health check every 5 minutes
$telegramCron->addHealthCheck(function($issues) use ($telegramLogger) {
    $telegramLogger->critical('Bot health check failed', ['issues' => $issues]);
}, 'every 5 minutes');

// Custom daily task
$telegramCron->every('daily_greeting', function($context) {
    $context['logger']->info('Running daily greeting task');
    // Send daily greetings to active users
    return true;
}, 'daily');

// ==================== Main Handler ====================

/**
 * Handle incoming update
 * This function should be called from webhook.php or polling loop
 * 
 * @param array $update Update data from Telegram
 * @return mixed Response
 */
function handleUpdate(array $update) {
    global $telegramMiddleware, $telegramDispatcher, $telegramLogger;
    
    try {
        // Process through middleware
        return $telegramMiddleware->handle($update, function() use ($update, $telegramDispatcher) {
            // Dispatch to appropriate handler
            return $telegramDispatcher->dispatch($update);
        });
    } catch (Throwable $e) {
        $telegramLogger->logException($e, 'Error handling update');
        return false;
    }
}

// ==================== Usage Examples ====================

/*
 * WEBHOOK MODE (webhook.php):
 * --------------------------
 * <?php
 * require_once 'framework_example.php';
 * 
 * $update = json_decode(file_get_contents('php://input'), true);
 * $telegramLogger->logUpdate($update);
 * handleUpdate($update);
 * 
 * 
 * POLLING MODE (run in terminal):
 * --------------------------------
 * <?php
 * require_once 'framework_example.php';
 * 
 * echo "Starting bot in polling mode...\n";
 * 
 * $offset = 0;
 * while (true) {
 *     $updates = $telegramBot->getUpdates(['offset' => $offset, 'timeout' => 30]);
 *     
 *     if ($updates['ok']) {
 *         foreach ($updates['result'] as $update) {
 *             $offset = $update['update_id'] + 1;
 *             handleUpdate($update);
 *         }
 *     }
 *     
 *     // Run scheduled tasks
 *     $telegramCron->runDueTasks();
 * }
 * 
 * 
 * CRON SCHEDULER (separate process):
 * -----------------------------------
 * <?php
 * require_once 'framework_example.php';
 * 
 * echo "Starting cron scheduler...\n";
 * $telegramCron->run();
 * 
 * 
 * COMMAND LINE TASK:
 * ------------------
 * php -r "require 'framework_example.php'; \$telegramCron->runTask('health_check');"
 */

echo "Telegram Bot Framework initialized successfully!\n";
echo "Files loaded: TelegramBot, TelegramCache, TelegramLogger, TelegramMiddleware, TelegramDB, TelegramCron, TelegramBotDispatcher\n";
echo "\nTo start the bot:\n";
echo "1. Set your BOT_TOKEN in this file\n";
echo "2. For webhook: deploy webhook.php to your server\n";
echo "3. For polling: run the polling example above\n";
echo "4. For cron: run the cron scheduler separately\n";
