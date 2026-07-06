<?php

/**
 * Modernized Super Example Bot
 * 
 * This file demonstrates how to use the refactored Telegram Bot Framework.
 * It replaces the legacy 'Telegram' class with 'TelegramBot', uses the new
 * Database and Logger classes, and implements clean error handling.
 * 
 * CHANGES FROM OLD VERSION:
 * - Replaced old Telegram class with modern TelegramBot class
 * - Replaced $telegram->Text() with $bot->text()
 * - Replaced $telegram->ChatID() with $bot->chatId()
 * - Replaced $telegram->UserID() with $bot->userId()
 * - Replaced $telegram->Username() with $bot->username()
 * - Replaced $telegram->FirstName() with $bot->firstName()
 * - Replaced $telegram->LastName() with $bot->lastName()
 * - Replaced $telegram->MessageID() with $bot->messageId()
 * - Replaced $telegram->getUpdateType() with $bot->getUpdateType()
 * - Replaced $telegram->getData() with $bot->getUpdate()
 * - Replaced $telegram->Callback_Query() with $bot->callbackData()
 * - Replaced $telegram->endpoint() with specific methods like sendMessage(), sendPhoto(), etc.
 * - Integrated with new Logger and Database classes
 * - Added webhook security validation
 * - Clean, modular code structure
 * 
 * @package TelegramBot\Examples
 */

// -----------------------------------------------------------------------------
// 1. BOOTSTRAP & AUTOLOADING
// -----------------------------------------------------------------------------

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load Composer Autoloader (if using composer) or Manual Autoloader
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
} else {
    // Simple manual autoloader for core classes
    spl_autoload_register(function ($class) {
        $base_dir = BASE_PATH . '/src/';
        
        // Map of core classes to their file paths
        $coreClasses = [
            'TelegramBot' => '/Bot/TelegramBot.php',
            'Database' => '/Database/Database.php',
            'Config' => '/Core/Config.php',
            'Logger' => '/Core/Logger.php',
        ];
        
        if (isset($coreClasses[$class])) {
            $file = $base_dir . $coreClasses[$class];
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    });
}

// -----------------------------------------------------------------------------
// 2. INITIALIZATION & CONFIGURATION
// -----------------------------------------------------------------------------

try {
    // Initialize Configuration
    $configFile = BASE_PATH . '/config/config.php';
    if (!file_exists($configFile)) {
        throw new Exception("Configuration file not found. Please create config/config.php");
    }
    
    $config = require $configFile;
    
    // Initialize Database (handles connection and auto-creation of tables)
    $db = new Database($config);
    
    // Initialize Logger
    $logger = new Logger($config['logging']['path'] ?? BASE_PATH . '/storage/logs');
    $logger->setLevel($config['logging']['level'] ?? 'DEBUG');
    
    // Identify the Bot Token
    // Priority: 1.URL param ?token=  2.Env var  3.Database active bot
    $botToken = $_GET['token'] ?? getenv('TELEGRAM_BOT_TOKEN');
    
    if (!$botToken) {
        $activeBot = $db->getActiveBot(); 
        if ($activeBot) {
            $botToken = $activeBot['token'];
            $botUsername = $activeBot['username'];
            $logger->info("Loaded bot from database: {$botUsername}");
        } else {
            throw new Exception("No bot token provided and no active bot found in database.");
        }
    }

    // Instantiate the Modern Bot Class
    // NEW: Pass logger instance for integrated logging
    $bot = new TelegramBot($botToken, true, [], $logger);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

// -----------------------------------------------------------------------------
// 3. WEBHOOK SECURITY & INPUT HANDLING
// -----------------------------------------------------------------------------

// Get raw POST data
$updateRaw = file_get_contents('php://input');
$update = json_decode($updateRaw, true);

// Validate Update
if (!$update || !isset($update['update_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        http_response_code(200);
        echo "Bot is running. Webhook URL: " . htmlspecialchars($bot->getWebhookInfo()['url'] ?? 'Not Set');
        exit;
    }
    $logger->warning("Invalid update received", ['raw' => substr($updateRaw, 0, 100)]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid Update']);
    exit;
}

// Set the update in the bot instance (required for helper methods)
$bot->setUpdate($update);

// Log the incoming update
$logger->debug("Update Received", [
    'id' => $update['update_id'],
    'type' => $bot->getUpdateType()
]);

// Verify Webhook Secret Token if configured
$secretToken = $config['bot']['secret_token'] ?? null;
if ($secretToken) {
    $receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($receivedToken !== $secretToken) {
        $logger->alert("Webhook secret token mismatch!");
        http_response_code(403);
        exit;
    }
}

// -----------------------------------------------------------------------------
// 4. EXTRACT DATA USING NEW TELEGRAMBOT METHODS
// -----------------------------------------------------------------------------

// All these methods replace the old Telegram class methods:

// Message Text - Replaces: $telegram->Text()
$messageText = $bot->text() ?? '';

// Message ID - Replaces: $telegram->MessageID()
$messageId = $bot->messageId();

// Update Type - Replaces: $telegram->getUpdateType()
$updateType = $bot->getUpdateType();

// Chat ID - Replaces: $telegram->ChatID()
$chatId = $bot->chatId();

// User ID - Replaces: $telegram->UserID()
$userId = $bot->userId();

// Username - Replaces: $telegram->Username()
$username = $bot->username();

// First Name - Replaces: $telegram->FirstName()
$firstName = $bot->firstName();

// Last Name - Replaces: $telegram->LastName()
$lastName = $bot->lastName();

// Full Update Data - Replaces: $telegram->getData()
$lastMsgData = $bot->getUpdate();

// Callback Query Data - Replaces: $telegram->Callback_Query()
$callbackQuery = $bot->callbackData();
$callbackId = $bot->callbackId();

// Additional helpers available:
// $bot->caption() - Get message caption
// $bot->isGroup() - Check if chat is group
// $bot->isPrivate() - Check if chat is private
// $bot->isChannel() - Check if chat is channel
// $bot->getMessageType() - Get type of message (text, photo, video, etc.)
// $bot->entities() - Get message entities
// $bot->location() - Get location data
// $bot->contact() - Get contact data

// -----------------------------------------------------------------------------
// 5. MAIN LOGIC HANDLER
// -----------------------------------------------------------------------------

try {
    // Store user in DB if exists
    if ($userId && $chatId) {
        $db->saveUser($userId, $username, $firstName);
        $db->saveUserChat($userId, $chatId);
    }

    // --- ROUTING LOGIC ---

    if ($updateType === 'callback_query') {
        // Handle Callback Queries (Inline Buttons)
        $logger->info("Callback received", ['data' => $callbackQuery, 'user' => $userId]);

        // Acknowledge callback immediately
        $bot->answerCallbackQuery($callbackId, [
            'show_alert' => false, 
            'text' => 'Processing...'
        ]);

        // Router for Callbacks
        if (strpos($callbackQuery, 'settings_') === 0) {
            $bot->sendMessage($chatId, "⚙️ Opening settings menu...", ['reply_to_message_id' => $messageId]);
        } elseif ($callbackQuery === 'help_btn') {
            $bot->sendMessage($chatId, "🆘 Here is the help content you requested.");
        } else {
            $bot->answerCallbackQuery($callbackId, ['text' => "Unknown action: $callbackQuery", 'show_alert' => true]);
        }

    } elseif ($updateType === 'message') {
        // Handle Standard Messages
        
        // Check for Commands (starts with /)
        if (strpos($messageText, '/') === 0) {
            $parts = explode(' ', $messageText);
            $command = strtolower(substr($parts[0], 1));
            $args = array_slice($parts, 1);

            $logger->info("Command executed", ['cmd' => $command, 'user' => $userId]);

            switch ($command) {
                case 'start':
                    $welcomeMsg = "Hello <b>{$firstName}</b>! 👋\n";
                    $welcomeMsg .= "I am your modernized bot.\n\n";
                    $welcomeMsg .= "Use /help to see what I can do.";
                    
                    // Build inline keyboard using new method
                    $keyboard = $bot->buildInlineKeyboard([
                        [['text' => 'ℹ️ Help', 'callback_data' => 'help_btn']],
                        [['text' => '⚙️ Settings', 'callback_data' => 'settings_main']]
                    ]);

                    $bot->sendMessage($chatId, $welcomeMsg, [
                        'parse_mode' => 'HTML',
                        'reply_markup' => $keyboard
                    ]);
                    break;

                case 'help':
                    $helpText = "📚 <b>Available Commands:</b>\n\n";
                    $helpText .= "/start - Start the bot\n";
                    $helpText .= "/help - Show this message\n";
                    $helpText .= "/status - Check system status\n";
                    $helpText .= "/admin - Admin panel (if authorized)";
                    
                    $bot->sendMessage($chatId, $helpText, ['parse_mode' => 'HTML']);
                    break;

                case 'status':
                    $dbStats = $db->getStats();
                    $statusMsg = "📊 <b>System Status</b>\n\n";
                    $statusMsg .= "Users: {$dbStats['users']}\n";
                    $statusMsg .= "Chats: {$dbStats['chats']}\n";
                    $statusMsg .= "Uptime: OK\n";
                    $statusMsg .= "DB: Connected";
                    
                    $bot->sendMessage($chatId, $statusMsg, ['parse_mode' => 'HTML']);
                    break;

                case 'admin':
                    $isAdmin = $db->isAdmin($userId);
                    if ($isAdmin) {
                        $bot->sendMessage($chatId, "🔐 Welcome Admin! Use the Control Panel for full management.");
                    } else {
                        $bot->sendMessage($chatId, "🚫 Access Denied. You are not an administrator.");
                        $logger->warning("Unauthorized admin access attempt", ['user' => $userId]);
                    }
                    break;

                default:
                    $bot->sendMessage($chatId, "❓ Unknown command: /{$command}. Use /help.");
            }

        } else {
            // Handle Non-Command Messages
            $logger->debug("Text message received", ['text' => $messageText]);
            
            // Auto-reply to keywords
            if (stripos($messageText, 'hello') !== false) {
                $bot->sendMessage($chatId, "Hi there! How can I help you today?", ['reply_to_message_id' => $messageId]);
            } 
            elseif (stripos($messageText, 'feedback:') === 0) {
                $feedback = trim(substr($messageText, 9));
                $bot->sendMessage($chatId, "✅ Feedback received! Thank you.");
                $logger->info("User Feedback", ['user' => $userId, 'feedback' => $feedback]);
            }
        }
    } 
    elseif ($updateType === 'pre_checkout_query') {
        // Handle Payment Pre-Checkout
        $preCheckout = $bot->preCheckoutQuery();
        $queryId = $preCheckout['id'] ?? null;
        if ($queryId) {
            $bot->answerPreCheckoutQuery($queryId, ['ok' => true]);
            $logger->info("Pre-checkout approved", ['id' => $queryId]);
        }
    }
    elseif ($updateType === 'shipping_query') {
        // Handle Shipping Query
        $shipping = $bot->shippingQuery();
        $queryId = $shipping['id'] ?? null;
        if ($queryId) {
            $bot->answerShippingQuery($queryId, ['ok' => true]);
        }
    }

    // Save update to database
    $db->logUpdate($update['update_id'], $updateType, $userId, $chatId, $messageText);

} catch (Exception $e) {
    $logger->error("Bot Logic Error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal Server Error']);
}

// -----------------------------------------------------------------------------
// 6. CLEAN EXIT
// -----------------------------------------------------------------------------
http_response_code(200);
echo json_encode(['ok' => true]);
