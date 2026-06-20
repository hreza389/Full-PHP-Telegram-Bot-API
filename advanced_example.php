<?php

require_once 'TelegramBot.php';
require_once 'TelegramResponse.php';
require_once 'TelegramTypes.php';
require_once 'TelegramBotDispatcher.php';

/**
 * Advanced Telegram Bot Example with Event Dispatcher
 * 
 * Demonstrates modern event-driven bot architecture with:
 * - Command handlers with aliases and admin restrictions
 * - Callback query handlers with pattern matching
 * - Message filters (text, type, chat type)
 * - Event listeners for various update types
 * - Conversation state management
 * - Inline keyboards and reply keyboards
 */

// Initialize bot
$bot = new TelegramBot('YOUR_BOT_TOKEN_HERE');
$dispatcher = new TelegramBotDispatcher($bot);

// Store conversation states (in production, use a database)
$conversations = [];

/* =============================================================================
   COMMAND HANDLERS
   ============================================================================= */

// /start command
$dispatcher->onCommand('start', function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $firstName = $message['from']['first_name'] ?? 'User';
    
    $keyboard = [
        ['text' => '📋 Menu', 'callback_data' => 'menu:main'],
        ['text' => 'ℹ️ Help', 'callback_data' => 'help:info'],
        ['text' => '👤 Profile', 'callback_data' => 'profile:view'],
    ];
    
    $replyKeyboard = [
        ['Contact Us', 'About'],
        ['Settings', 'Help'],
    ];
    
    $bot->sendMessage($chatId, "Welcome, $firstName! 👋\n\nUse the buttons below to navigate.", [
        'reply_markup' => json_encode(['inline_keyboard' => [$keyboard]]),
    ]);
}, ['description' => 'Start the bot']);

// /help command with alias
$dispatcher->onCommand('help', function ($message, $bot) {
    $chatId = $message['chat']['id'];
    
    $helpText = "📚 **Bot Help**\n\n" .
                "Available commands:\n" .
                "/start - Start the bot\n" .
                "/help - Show this help\n" .
                "/settings - Bot settings\n" .
                "/admin - Admin panel (admins only)\n\n" .
                "You can also use the inline buttons!";
    
    $bot->sendMessage($chatId, $helpText, ['parse_mode' => 'Markdown']);
}, ['aliases' => ['support', 'info'], 'description' => 'Show help information']);

// /settings command
$dispatcher->onCommand('settings', function ($message, $bot) {
    $chatId = $message['chat']['id'];
    
    $keyboard = [
        ['text' => '🔔 Notifications', 'callback_data' => 'settings:notifications'],
        ['text' => '🌐 Language', 'callback_data' => 'settings:language'],
        ['text' => '🔒 Privacy', 'callback_data' => 'settings:privacy'],
    ];
    
    $bot->sendMessage($chatId, "⚙️ **Settings**\n\nChoose an option:", [
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [$keyboard]]),
    ]);
}, ['description' => 'Open bot settings']);

// /admin command (admin only)
$dispatcher->onCommand('admin', function ($message, $bot) {
    $chatId = $message['chat']['id'];
    
    // Check if user is bot admin (you can customize this)
    $adminIds = [123456789]; // Replace with actual admin IDs
    
    if (!in_array($message['from']['id'], $adminIds)) {
        $bot->sendMessage($chatId, "❌ You don't have permission to use this command.");
        return;
    }
    
    $keyboard = [
        ['text' => '📊 Statistics', 'callback_data' => 'admin:stats'],
        ['text' => '📢 Broadcast', 'callback_data' => 'admin:broadcast'],
        ['text' => '👥 Users', 'callback_data' => 'admin:users'],
    ];
    
    $bot->sendMessage($chatId, "🛡️ **Admin Panel**\n\nSelect an action:", [
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [$keyboard]]),
    ]);
}, ['adminOnly' => true, 'description' => 'Admin panel (admins only)']);

// /echo command with arguments
$dispatcher->onCommand('echo', function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $args = explode(' ', trim(substr($message['text'], strpos($message['text'], ' '))));
    $text = implode(' ', $args);
    
    if (empty($text)) {
        $bot->sendMessage($chatId, "Please provide text to echo.\nUsage: /echo <text>");
        return;
    }
    
    $bot->sendMessage($chatId, "🔊 " . $text);
}, ['description' => 'Echo back your message']);

/* =============================================================================
   CALLBACK QUERY HANDLERS
   ============================================================================= */

// Main menu callback
$dispatcher->onCallback('menu:*', function ($callbackQuery, $bot) {
    $data = $callbackQuery['data'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    
    // Answer callback query
    $bot->answerCallbackQuery($callbackQuery['id'], ['show_alert' => false]);
    
    $menuText = "📋 **Main Menu**\n\n" .
                "• View your profile\n" .
                "• Change settings\n" .
                "• Get help\n" .
                "• Contact support";
    
    $keyboard = [
        ['text' => '👤 My Profile', 'callback_data' => 'profile:view'],
        ['text' => '⚙️ Settings', 'callback_data' => 'settings:main'],
        ['text' => '📞 Support', 'callback_data' => 'support:contact'],
    ];
    
    $bot->editMessageText($menuText, $chatId, $messageId, [
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [$keyboard]]),
    ]);
});

// Help callback
$dispatcher->onCallback('help:*', function ($callbackQuery, $bot) {
    $chatId = $callbackQuery['message']['chat']['id'];
    
    $bot->answerCallbackQuery($callbackQuery['id'], [
        'text' => 'ℹ️ Need help? Contact @support',
        'show_alert' => true,
    ]);
});

// Profile callback
$dispatcher->onCallback('profile:view', function ($callbackQuery, $bot) {
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $firstName = $callbackQuery['from']['first_name'];
    $username = $callbackQuery['from']['username'] ?? 'Not set';
    
    $bot->answerCallbackQuery($callbackQuery['id']);
    
    $profileText = "👤 **Your Profile**\n\n" .
                   "**ID:** `$userId`\n" .
                   "**Name:** $firstName\n" .
                   "**Username:** @$username";
    
    $keyboard = [
        ['text' => '🔄 Refresh', 'callback_data' => 'profile:view'],
        ['text' => '🔙 Back', 'callback_data' => 'menu:main'],
    ];
    
    $bot->sendMessage($chatId, $profileText, [
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [$keyboard]]),
    ]);
});

// Settings callbacks
$dispatcher->onCallback('settings:*', function ($callbackQuery, $bot) {
    $data = $callbackQuery['data'];
    $chatId = $callbackQuery['message']['chat']['id'];
    
    $bot->answerCallbackQuery($callbackQuery['id'], [
        'text' => 'Settings feature coming soon!',
        'show_alert' => true,
    ]);
});

/* =============================================================================
   MESSAGE HANDLERS WITH FILTERS
   ============================================================================= */

// Handle messages containing "hello" or "hi"
$dispatcher->onMessage(function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $bot->sendMessage($chatId, "Hello there! 👋 How can I help you?");
}, ['contains' => 'hello']);

// Handle photo messages
$dispatcher->onMessage(function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $bot->sendMessage($chatId, "Nice photo! 📸 Thanks for sharing!");
}, ['type' => 'photo']);

// Handle messages in private chats only
$dispatcher->onMessage(function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $bot->sendMessage($chatId, "This is a private message handler.");
}, ['chat_type' => 'private']);

// Handle document messages
$dispatcher->onMessage(function ($message, $bot) {
    $chatId = $message['chat']['id'];
    $fileName = $message['document']['file_name'] ?? 'unknown';
    $bot->sendMessage($chatId, "📄 Received document: $fileName");
}, ['type' => 'document']);

/* =============================================================================
   EVENT HANDLERS
   ============================================================================= */

// Listen to all messages
$dispatcher->onEvent('message', function ($data, $bot) {
    $message = $data['message'];
    // Log all messages (in production, use proper logging)
    error_log("Message from {$message['from']['id']}: " . ($message['text'] ?? '[media]'));
});

// Listen to edited messages
$dispatcher->onEvent('edited_message', function ($data, $bot) {
    $message = $data['edited_message'];
    $chatId = $message['chat']['id'];
    $bot->sendMessage($chatId, "✏️ I see you edited your message!");
});

// Listen to chat member changes
$dispatcher->onEvent('chat_member', function ($data, $bot) {
    $chatMember = $data['chat_member'];
    $chatId = $chatMember['chat']['id'];
    $newStatus = $chatMember['new_chat_member']['status'];
    $userId = $chatMember['new_chat_member']['user']['id'];
    
    if ($newStatus === 'member') {
        $bot->sendMessage($chatId, "🎉 Welcome to the group!");
    } elseif ($newStatus === 'left') {
        $bot->sendMessage($chatId, "👋 User $userId left the group.");
    }
});

/* =============================================================================
   CONVERSATION EXAMPLE
   ============================================================================= */

// Start a conversation
$dispatcher->onCommand('feedback', function ($message, $bot) use (&$conversations) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    
    // Set conversation state
    $conversations[$userId] = [
        'state' => 'waiting_for_feedback',
        'step' => 1
    ];
    
    $bot->sendMessage($chatId, "📝 Please send us your feedback:\n\n(Type /cancel to cancel)");
});

// Handle conversation flow
$dispatcher->onMessage(function ($message, $bot) use (&$conversations) {
    $userId = $message['from']['id'];
    $chatId = $message['chat']['id'];
    
    if (!isset($conversations[$userId])) {
        return;
    }
    
    $conversation = $conversations[$userId];
    
    // Check for cancel
    if (isset($message['text']) && $message['text'] === '/cancel') {
        unset($conversations[$userId]);
        $bot->sendMessage($chatId, "❌ Conversation cancelled.");
        return;
    }
    
    if ($conversation['state'] === 'waiting_for_feedback') {
        // Save feedback (in production, save to database)
        $feedback = $message['text'] ?? '[media]';
        
        unset($conversations[$userId]);
        
        $bot->sendMessage($chatId, "✅ Thank you for your feedback!\n\nWe received: \"$feedback\"");
    }
}, []);

/* =============================================================================
   RUN THE BOT
   ============================================================================= */

echo "🤖 Advanced Bot Starting...\n";
echo "Commands registered: start, help, settings, admin, echo, feedback\n";
echo "Press Ctrl+C to stop\n\n";

// Run with long polling
$dispatcher->run();
