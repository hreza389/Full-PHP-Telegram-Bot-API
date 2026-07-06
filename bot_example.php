<?php

/**
 * Telegram Bot Example - Modern Usage
 * 
 * This file demonstrates how to use the modern TelegramBot library
 * with MySQL database, webhook control, payments, and more.
 * 
 * @package TelegramBot\Examples
 * @version 2.0.0
 */

// Autoload classes (adjust path as needed)
require_once __DIR__ . '/vendor/autoload.php';

use TelegramBot\Bot\TelegramBot;
use TelegramBot\Core\Config;
use TelegramBot\Core\Logger;
use TelegramBot\Database\Database;

// ============================================================================
// EXAMPLE 1: BASIC BOT SETUP WITH LONG POLLING
// ============================================================================

echo "=== Example 1: Basic Bot Setup ===\n\n";

// Load configuration
Config::load(__DIR__ . '/config/config.php');

// Get bot token from config or environment
$botToken = Config::get('bots.default_token') ?: getenv('TELEGRAM_BOT_TOKEN');

if (!$botToken) {
    die("Error: Bot token not configured!\n");
}

// Create bot instance
$bot = new TelegramBot($botToken);

// Test the bot
$me = $bot->getMe();
if ($me && isset($me['ok']) && $me['ok']) {
    echo "✓ Bot connected: @{$me['result']['username']}\n";
    echo "  Name: {$me['result']['first_name']}\n";
    echo "  ID: {$me['result']['id']}\n\n";
} else {
    die("✗ Failed to connect to Telegram API\n");
}

// ============================================================================
// EXAMPLE 2: DATABASE CONNECTION & SETUP
// ============================================================================

echo "=== Example 2: Database Connection ===\n\n";

try {
    // Initialize database (auto-creates tables if needed)
    $db = new Database(
        Config::get('database.host'),
        Config::get('database.name'),
        Config::get('database.user'),
        Config::get('database.password'),
        Config::get('database.port', 3306)
    );
    
    echo "✓ Database connected: " . Config::get('database.name') . "\n";
    echo "  Tables created/verified successfully\n\n";
    
    // Save bot info to database
    $botId = $db->saveBot($botToken, $me['result']['username']);
    echo "✓ Bot saved to database with ID: {$botId}\n\n";
    
} catch (\Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXAMPLE 3: WEBHOOK CONFIGURATION
// ============================================================================

echo "=== Example 3: Webhook Configuration ===\n\n";

$webhookUrl = 'https://your-domain.com/webhook.php';

// Set webhook with options
$webhookSet = $bot->setWebhook($webhookUrl, [
    'allowed_updates' => ['message', 'callback_query', 'inline_query'],
    'max_connections' => 40,
    'drop_pending_updates' => true
]);

if ($webhookSet) {
    echo "✓ Webhook set successfully\n";
    
    // Get webhook info
    $webhookInfo = $bot->getWebhookInfo();
    if ($webhookInfo && isset($webhookInfo['result'])) {
        echo "  URL: " . ($webhookInfo['result']['url'] ?? 'Not set') . "\n";
        echo "  Has custom certificate: " . ($webhookInfo['result']['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
        echo "  Pending updates: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
        echo "  Max connections: " . ($webhookInfo['result']['max_connections'] ?? 40) . "\n";
    }
    echo "\n";
} else {
    echo "✗ Failed to set webhook\n\n";
}

// To delete webhook:
// $bot->deleteWebhook();

// ============================================================================
// EXAMPLE 4: SENDING MESSAGES
// ============================================================================

echo "=== Example 4: Sending Messages ===\n\n";

$chatId = 123456789; // Replace with actual chat ID

// Send simple text message
$result = $bot->sendMessage($chatId, "Hello! This is a test message.");
if ($result && isset($result['ok']) && $result['ok']) {
    echo "✓ Message sent (ID: {$result['result']['message_id']})\n";
}

// Send message with Markdown formatting
$bot->sendMessage($chatId, "*Bold text*\n_Italic text_\n`Code`\n[Link](https://example.com)", [
    'parse_mode' => TelegramBot::PARSE_MARKDOWN
]);
echo "✓ Markdown message sent\n";

// Send message with HTML formatting
$bot->sendMessage($chatId, "<b>Bold</b>\n<i>Italic</i>\n<a href='https://example.com'>Link</a>", [
    'parse_mode' => TelegramBot::PARSE_HTML
]);
echo "✓ HTML message sent\n";

// Send message with reply keyboard
$keyboard = $bot->buildReplyKeyboard([
    [$bot->keyboardButton('📍 Send Location', false, true)],
    [$bot->keyboardButton('📞 Send Contact', true)]
], false, true);

$bot->sendMessage($chatId, "Please share your location or contact:", [
    'reply_markup' => $keyboard
]);
echo "✓ Message with reply keyboard sent\n";

// Send message with inline keyboard
$inlineKeyboard = $bot->buildInlineKeyboard([
    [
        $bot->inlineButton('🌐 Website', 'https://example.com'),
        $bot->inlineButton('📱 Telegram', 'https://t.me/example')
    ],
    [
        $bot->inlineButton('ℹ️ Info', 'callback_info'),
        $bot->inlineButton('⚙️ Settings', 'callback_settings')
    ]
]);

$bot->sendMessage($chatId, "Choose an option:", [
    'reply_markup' => $inlineKeyboard
]);
echo "✓ Message with inline keyboard sent\n\n";

// ============================================================================
// EXAMPLE 5: SENDING MEDIA
// ============================================================================

echo "=== Example 5: Sending Media ===\n\n";

// Send photo by URL
$bot->sendPhoto($chatId, 'https://picsum.photos/800/600.jpg', [
    'caption' => 'Random photo from Picsum',
    'parse_mode' => TelegramBot::PARSE_HTML
]);
echo "✓ Photo sent\n";

// Send document
$bot->sendDocument($chatId, 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', [
    'caption' => 'Sample PDF document'
]);
echo "✓ Document sent\n";

// Send media group (multiple photos/videos)
$mediaGroup = [
    ['type' => 'photo', 'media' => 'https://picsum.photos/800/600.jpg?random=1', 'caption' => 'Photo 1'],
    ['type' => 'photo', 'media' => 'https://picsum.photos/800/600.jpg?random=2', 'caption' => 'Photo 2'],
    ['type' => 'photo', 'media' => 'https://picsum.photos/800/600.jpg?random=3', 'caption' => 'Photo 3']
];

$bot->sendMediaGroup($chatId, $mediaGroup);
echo "✓ Media group sent\n\n";

// ============================================================================
// EXAMPLE 6: CHAT MANAGEMENT
// ============================================================================

echo "=== Example 6: Chat Management ===\n\n";

$groupId = -123456789; // Replace with actual group ID

// Get chat info
$chat = $bot->getChat($groupId);
if ($chat) {
    echo "✓ Chat info retrieved:\n";
    echo "  Title: {$chat['title']}\n";
    echo "  Type: {$chat['type']}\n";
    echo "  Members: {$chat['members_count']}\n";
}

// Export invite link
$inviteLink = $bot->exportChatInviteLink($groupId);
if ($inviteLink) {
    echo "✓ Invite link: {$inviteLink}\n";
}

// Create invite link with options
$newInviteLink = $bot->createChatInviteLink($groupId, [
    'name' => 'Special Invitation',
    'expire_date' => time() + 86400, // 24 hours
    'member_limit' => 10,
    'creates_join_request' => true
]);
if ($newInviteLink) {
    echo "✓ New invite link created: {$newInviteLink['invite_link']}\n";
}

echo "\n";

// ============================================================================
// EXAMPLE 7: PAYMENTS (INVOICES)
// ============================================================================

echo "=== Example 7: Payments ===\n\n";

$providerToken = Config::get('payments.provider_token'); // Your payment provider token

if ($providerToken) {
    // Define prices
    $prices = [
        ['label' => 'Product', 'amount' => 10000], // 100.00 in currency minor units
        ['label' => 'Tax', 'amount' => 2000],      // 20.00
        ['label' => 'Shipping', 'amount' => 500]   // 5.00
    ];

    // Send invoice
    $invoice = $bot->sendInvoice(
        $chatId,
        'Premium Subscription',
        'Get access to premium features for 1 month',
        'payload_12345', // Bot-defined payload
        $providerToken,
        'USD',
        $prices,
        [
            'need_name' => true,
            'need_email' => true,
            'need_phone_number' => true,
            'is_flexible' => true
        ]
    );

    if ($invoice && isset($invoice['ok']) && $invoice['ok']) {
        echo "✓ Invoice sent successfully\n";
    }
} else {
    echo "⚠ Payment provider token not configured\n";
}

echo "\n";

// ============================================================================
// EXAMPLE 8: LOGGING
// ============================================================================

echo "=== Example 8: Logging ===\n\n";

try {
    $logger = new Logger();
    $logger->info('Bot started', ['bot_id' => $me['result']['id']]);
    echo "✓ Log entry created\n";
    
    $logger->debug('Debug information', ['data' => ['key' => 'value']]);
    echo "✓ Debug log created\n";
    
    $logger->warning('Warning message', ['context' => 'test']);
    echo "✓ Warning log created\n";
    
    $logger->error('Error occurred', ['error_code' => 500]);
    echo "✓ Error log created\n";
    
} catch (\Exception $e) {
    echo "✗ Logger error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// EXAMPLE 9: HELPER METHODS
// ============================================================================

echo "=== Example 9: Helper Methods ===\n\n";

// Simulate an update for demonstration
$simulatedUpdate = [
    'update_id' => 12345,
    'message' => [
        'message_id' => 999,
        'from' => [
            'id' => 123456,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'language_code' => 'en'
        ],
        'chat' => [
            'id' => 123456,
            'type' => 'private'
        ],
        'text' => 'Hello bot!'
    ]
];

$bot->setUpdate($simulatedUpdate);

echo "Update type: " . $bot->getUpdateType() . "\n";
echo "Message type: " . $bot->getMessageType() . "\n";
echo "Text: " . $bot->text() . "\n";
echo "Chat ID: " . $bot->chatId() . "\n";
echo "User ID: " . $bot->userId() . "\n";
echo "First name: " . $bot->firstName() . "\n";
echo "Last name: " . $bot->lastName() . "\n";
echo "Username: " . $bot->username() . "\n";
echo "Language: " . $bot->languageCode() . "\n";
echo "Is private: " . ($bot->isPrivate() ? 'Yes' : 'No') . "\n";
echo "Is group: " . ($bot->isGroup() ? 'Yes' : 'No') . "\n";
echo "Is channel: " . ($bot->isChannel() ? 'Yes' : 'No') . "\n";

echo "\n";
echo "=== All Examples Completed ===\n";
