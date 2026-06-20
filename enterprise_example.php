<?php

/**
 * Enterprise Telegram Bot Example
 * 
 * Demonstrates integration of Broadcast, DeepLinking, and Stats
 * with a complete subscription management system.
 */

require_once 'TelegramBot.php';
require_once 'TelegramDB.php';
require_once 'TelegramBroadcast.php';
require_once 'TelegramDeepLinking.php';
require_once 'TelegramStats.php';
require_once 'TelegramCache.php';
require_once 'TelegramLogger.php';

// Configuration
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('DB_PATH', __DIR__ . '/bot_database.sqlite');
define('ADMIN_USER_IDS', [123456789]); // Replace with your Telegram user ID

// Initialize components
$db = new TelegramDB(DB_PATH);
$bot = new TelegramBot(BOT_TOKEN);
$broadcast = new TelegramBroadcast($bot, $db);
$deepLinking = new TelegramDeepLinking($bot, $db);
$stats = new TelegramStats($bot, $db);
$cache = new TelegramCache(__DIR__ . '/cache');
$logger = new TelegramLogger(__DIR__ . '/logs');

// Register deep link routes
$deepLinking->registerRoute('ref_*', function($param, $matches, $user, $chatId) use ($bot, $deepLinking, $db) {
    // Handle referral links
    $referrerId = str_replace('ref_', '', $param);
    
    if ($deepLinking->processReferral((int)$referrerId, $user['id'])) {
        $bot->sendMessage($chatId, "🎉 Welcome! You've been referred by a friend. Both of you get a bonus!");
        // Here you would add bonus logic
        return ['success' => true, 'type' => 'referral'];
    }
    
    return ['success' => false, 'error' => 'Referral already processed'];
});

$deepLinking->registerRoute('premium_*', function($param, $matches, $user, $chatId) use ($bot, $db) {
    // Handle premium subscription links
    $planId = str_replace('premium_', '', $param);
    
    $bot->sendMessage($chatId, "💎 You're interested in our Premium plan! Click below to activate:", null, null, null, null, null, null, 
        json_encode([
            'inline_keyboard' => [[
                ['text' => 'Activate Premium', 'callback_data' => "activate_premium_{$planId}"]
            ]]
        ])
    );
    
    return ['success' => true, 'type' => 'premium', 'plan' => $planId];
});

$deepLinking->registerRoute('content_*', function($param, $matches, $user, $chatId) use ($bot, $db) {
    // Handle content access links
    $contentId = str_replace('content_', '', $param);
    
    // Check if user has access
    $hasAccess = true; // Replace with actual access check
    
    if ($hasAccess) {
        $bot->sendMessage($chatId, "📚 Here's your exclusive content!");
        // Send content here
        return ['success' => true, 'type' => 'content', 'content_id' => $contentId];
    } else {
        $bot->sendMessage($chatId, "🔒 This content requires a premium subscription.");
        return ['success' => false, 'error' => 'No access'];
    }
});

// Command handlers
function handleStart($update, $bot, $db, $stats, $deepLinking, $cache) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $from = $message['from'];
    
    // Track user
    $stats->trackUser($from);
    
    // Check for deep link parameter
    $args = explode(' ', $message['text']);
    if (isset($args[1])) {
        $result = $deepLinking->processStartParam($args[1], $from, $chatId);
        
        if (!$result['success']) {
            $logger->info("Deep link failed: " . ($result['error'] ?? 'unknown'));
        }
        return;
    }
    
    // Regular start message
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '📊 View Stats', 'callback_data' => 'admin_stats']],
            [['text' => '📢 Create Broadcast', 'callback_data' => 'admin_broadcast']],
            [['text' => '🔗 Generate Link', 'callback_data' => 'admin_deeplink']]
        ]
    ]);
    
    $bot->sendMessage(
        $chatId,
        "👋 Welcome! I'm your Enterprise Bot.\n\n" .
        "I can help you with:\n" .
        "• Broadcasting messages to users\n" .
        "• Creating trackable deep links\n" .
        "• Managing referrals and subscriptions\n\n" .
        "Use /help for more commands.",
        'HTML',
        null,
        null,
        null,
        null,
        null,
        $keyboard
    );
}

function handleHelp($update, $bot, $stats) {
    $chatId = $update['message']['chat']['id'];
    $from = $update['message']['from'];
    
    $stats->trackCommand('help', $from['id'], $chatId, 'private', 0, true);
    
    $bot->sendMessage(
        $chatId,
        "📖 <b>Available Commands:</b>\n\n" .
        "/start - Start the bot\n" .
        "/help - Show this help message\n" .
        "/stats - View bot statistics\n" .
        "/broadcast - Create a broadcast (admin only)\n" .
        "/deeplink - Generate a deep link (admin only)\n" .
        "/referral - Get your referral link",
        'HTML'
    );
}

function handleStats($update, $bot, $stats) {
    $chatId = $update['message']['chat']['id'];
    $from = $update['message']['from'];
    
    // Check admin
    if (!in_array($from['id'], ADMIN_USER_IDS)) {
        $bot->sendMessage($chatId, "❌ This command is admin-only.");
        return;
    }
    
    $overallStats = $stats->getOverallStats();
    
    $message = "📊 <b>Bot Statistics</b>\n\n" .
        "👥 <b>Users:</b>\n" .
        "• Total: {$overallStats['total_users']}\n" .
        "• Active (24h): {$overallStats['active_users_24h']}\n" .
        "• Active (7d): {$overallStats['active_users_7d']}\n\n" .
        "💬 <b>Messages:</b>\n" .
        "• Total: {$overallStats['total_messages']}\n\n" .
        "⚡ <b>Commands:</b>\n" .
        "• Total: {$overallStats['total_commands']}\n" .
        "• Avg execution: {$overallStats['avg_command_time_ms']}ms\n" .
        "• Success rate: {$overallStats['success_rate']}%\n\n" .
        "⚠️ <b>Errors:</b>\n" .
        "• Total: {$overallStats['total_errors']}";
    
    $bot->sendMessage($chatId, $message, 'HTML');
}

function handleReferral($update, $bot, $deepLinking, $db) {
    $chatId = $update['message']['chat']['id'];
    $from = $update['message']['from'];
    
    $linkData = $deepLinking->createReferralLink($from['id'], ['bonus' => '100 coins']);
    
    $bot->sendMessage(
        $chatId,
        "🔗 <b>Your Referral Link:</b>\n\n" .
        "Share this link with friends:\n" .
        "<code>{$linkData['url']}</code>\n\n" .
        "For each friend who joins, you'll both receive a bonus!",
        'HTML',
        null,
        null,
        null,
        null,
        null,
        json_encode([
            'inline_keyboard' => [[
                ['text' => '📊 View Referral Stats', 'callback_data' => 'referral_stats']
            ]]
        ])
    );
}

// Process updates
function processUpdate($update, $bot, $db, $broadcast, $deepLinking, $stats, $cache, $logger) {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        $text = $message['text'] ?? '';
        
        // Track user and message
        $stats->trackUser($from);
        $stats->trackMessageReceived($message, $from);
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            $startTime = microtime(true);
            $parts = explode(' ', $text);
            $command = strtolower(str_replace('@bot', '', substr($parts[0], 1)));
            
            try {
                switch ($command) {
                    case 'start':
                        handleStart($update, $bot, $db, $stats, $deepLinking, $cache);
                        break;
                    case 'help':
                        handleHelp($update, $bot, $stats);
                        break;
                    case 'stats':
                        handleStats($update, $bot, $stats);
                        break;
                    case 'referral':
                        handleReferral($update, $bot, $deepLinking, $db);
                        break;
                    default:
                        $bot->sendMessage($chatId, "Unknown command. Use /help for available commands.");
                }
                
                $executionTime = (microtime(true) - $startTime) * 1000;
                $stats->trackCommand($command, $from['id'], $chatId, $message['chat']['type'], $executionTime, true);
                
            } catch (Exception $e) {
                $executionTime = (microtime(true) - $startTime) * 1000;
                $stats->trackCommand($command, $from['id'], $chatId, $message['chat']['type'], $executionTime, false, $e->getMessage());
                $logger->error("Command failed: {$command}", ['error' => $e->getMessage(), 'user' => $from['id']]);
                $bot->sendMessage($chatId, "❌ An error occurred while processing your command.");
            }
        }
        
    } elseif (isset($update['callback_query'])) {
        // Handle callback queries
        $callback = $update['callback_query'];
        $chatId = $callback['message']['chat']['id'];
        $from = $callback['from'];
        $data = $callback['data'];
        
        if (strpos($data, 'activate_premium_') === 0) {
            $planId = str_replace('activate_premium_', '', $data);
            $bot->answerCallbackQuery($callback['id'], "✅ Premium activated!", false);
            $bot->sendMessage($chatId, "🎉 Congratulations! Your premium subscription is now active.");
            
        } elseif ($data === 'admin_stats') {
            handleStats($update, $bot, $stats);
            $bot->answerCallbackQuery($callback['id']);
            
        } elseif ($data === 'referral_stats') {
            $linkStats = $deepLinking->getLinkStats(1); // Example
            $bot->answerCallbackQuery($callback['id']);
            $bot->sendMessage($chatId, "📊 Your referral stats will appear here.");
        }
    }
}

// Main polling loop (for testing)
echo "🤖 Enterprise Bot starting...\n";
echo "Press Ctrl+C to stop\n\n";

$offset = 0;
while (true) {
    try {
        $updates = $bot->getUpdates($offset, 100, 30);
        
        if (!empty($updates)) {
            foreach ($updates as $update) {
                processUpdate($update, $bot, $db, $broadcast, $deepLinking, $stats, $cache, $logger);
                $offset = $update['update_id'] + 1;
            }
        }
        
        usleep(500000); // 500ms delay
        
    } catch (Exception $e) {
        $logger->error("Polling error", ['error' => $e->getMessage()]);
        sleep(5);
    }
}
