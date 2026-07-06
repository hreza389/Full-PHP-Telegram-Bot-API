<?php
/**
 * Modern Bot Example & Integration Guide
 * 
 * This file demonstrates how to use the refactored Telegram Bot Framework.
 * It covers: Database, Logging, Webhooks, Payments, and Admin Controls.
 * 
 * Usage:
 * 1. Configure config/config.php
 * 2. Run this file via CLI or Browser to test components
 * 3. Set webhook via: php bot_example.php set_webhook
 */

// Autoloader for the new structure
require_once __DIR__ . '/src/Core/Config.php';
require_once __DIR__ . '/src/Core/Logger.php';
require_once __DIR__ . '/src/Database/Database.php';
require_once __DIR__ . '/src/Bot/TelegramBot.php';

use App\Core\Config;
use App\Core\Logger;
use App\Database\Database;
use App\Bot\TelegramBot;

// Initialize Configuration
$config = new Config(__DIR__ . '/config/config.php');

// Initialize Logger
$logger = new Logger($config->get('logging'));

// Initialize Database (MySQL)
try {
    $db = new Database($config->get('database'));
    $logger->info('Database connection established successfully.');
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Get Active Bot from Database (Controlled via Admin Panel)
$activeBot = $db->getActiveBot();

if (!$activeBot) {
    $logger->warning('No active bot found. Please add a bot token in the Control Panel.');
    die("No active bot configured. Go to Control Panel > Bots to add one.");
}

// Initialize Telegram Bot Client
$bot = new TelegramBot($activeBot['token'], $logger);

echo "=== Modern Bot Framework Example ===\n";
echo "Bot: @" . $activeBot['username'] . "\n";
echo "Database: MySQL (" . $config->get('database')['database'] . ")\n";
echo "Log Path: " . $config->get('logging')['path'] . "\n\n";

// Handle CLI Commands for Demonstration
$action = $_SERVER['argv'][1] ?? 'help';

switch ($action) {
    case 'set_webhook':
        // Example: Setting Webhook via CLI
        $webhookUrl = $config->get('bot_settings')['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            echo "Error: Webhook URL not set in config.\n";
            exit(1);
        }
        
        echo "Setting webhook to: $webhookUrl\n";
        $result = $bot->setWebhook($webhookUrl);
        
        if ($result['ok']) {
            echo "Success: Webhook set!\n";
            $db->updateBotStatus($activeBot['id'], ['webhook_status' => 'active']);
        } else {
            echo "Failed: " . ($result['description'] ?? 'Unknown error') . "\n";
        }
        break;

    case 'delete_webhook':
        echo "Deleting webhook...\n";
        $result = $bot->deleteWebhook();
        if ($result['ok']) {
            echo "Success: Webhook deleted.\n";
            $db->updateBotStatus($activeBot['id'], ['webhook_status' => 'inactive']);
        }
        break;

    case 'test_payment':
        // Example: Creating a Payment Invoice
        echo "Testing Payment Invoice creation...\n";
        $title = "Premium Subscription";
        $description = "1 Month Access to Premium Features";
        $payload = "user_123_upgrade";
        $providerToken = $config->get('payment')['provider_token'] ?? ''; // Stripe/Yookassa etc.
        $currency = "USD";
        $prices = [
            ['label' => 'Subscription', 'amount' => 999], // $9.99
            ['label' => 'Tax', 'amount' => 100]
        ];

        if (empty($providerToken)) {
            echo "Warning: Provider Token missing in config. Using dummy mode.\n";
        }

        // Note: This requires a chat_id to send. Usually triggered by user command.
        // Here we just demonstrate the method availability.
        echo "Invoice Payload Prepared: $payload\n";
        echo "Amount: 9.99 $currency\n";
        echo "To send this, use: \$bot->sendInvoice(\$chatId, ...)\n";
        break;

    case 'stats':
        // Display System Stats from DB
        $stats = $db->getStatistics();
        echo "--- System Statistics ---\n";
        echo "Total Users: " . ($stats['users'] ?? 0) . "\n";
        echo "Total Chats: " . ($stats['chats'] ?? 0) . "\n";
        echo "Total Updates Processed: " . ($stats['updates'] ?? 0) . "\n";
        echo "Active Bots: " . ($stats['active_bots'] ?? 0) . "\n";
        break;

    case 'clean_logs':
        // Trigger log rotation/cleanup
        echo "Rotating logs...\n";
        $logger->rotate();
        echo "Done.\n";
        break;

    case 'help':
    default:
        echo "Available Commands:\n";
        echo "  php bot_example.php set_webhook   - Set webhook URL\n";
        echo "  php bot_example.php delete_webhook- Remove webhook\n";
        echo "  php bot_example.php test_payment  - Test invoice generation\n";
        echo "  php bot_example.php stats         - Show DB statistics\n";
        echo "  php bot_example.php clean_logs    - Rotate log files\n";
        echo "\nWeb Interface:\n";
        echo "  Visit /dashboard/ for Control Panel (Admin, Bots, Webhooks, Payments)\n";
        break;
}

/**
 * EXAMPLE: How to handle updates in webhook.php or long-polling loop
 * Uncomment below to see logic structure
 */
/*
function handleUpdate($update, $db, $bot, $logger) {
    $logger->info('Processing update', ['update_id' => $update['update_id']]);

    // Save update to DB
    $db->saveUpdate($update);

    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];

        // Save/User Tracking
        $db->saveUser($userId, $message['from']);
        $db->saveChat($chatId, $message['chat']);

        // Command Router
        if ($text === '/start') {
            $bot->sendMessage($chatId, "Welcome! Use /pay to test payments.");
        } 
        elseif ($text === '/pay') {
            // Payment Example
            $bot->sendInvoice($chatId, [
                'title' => 'Test Product',
                'description' => 'Description here',
                'payload' => 'order_123',
                'provider_token' => getenv('PAYMENT_TOKEN'),
                'currency' => 'USD',
                'prices' => [['label' => 'Item', 'amount' => 500]]
            ]);
        }
        elseif ($text === '/admin') {
            // Check Admin Role from DB
            if ($db->isAdmin($userId)) {
                $bot->sendMessage($chatId, "Welcome Admin! Visit /dashboard for full control.");
            } else {
                $bot->sendMessage($chatId, "Access Denied.");
            }
        }
    }
    
    // Handle Pre-checkout query (Payment Success)
    if (isset($update['pre_checkout_query'])) {
        $bot->answerPreCheckoutQuery($update['pre_checkout_query']['id'], true);
    }
    
    // Handle Successful Payment
    if (isset($update['successful_payment'])) {
        $logger->alert('Payment Received', $update['successful_payment']);
        // Grant user access here
    }
}
*/

echo "\nExecution finished.\n";
