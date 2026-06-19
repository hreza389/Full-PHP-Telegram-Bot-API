<?php
/**
 * Example usage of the TelegramBot class
 * 
 * This example demonstrates how to use the TelegramBot class
 * to create a simple bot that responds to messages.
 */

require_once 'TelegramBot.php';

// Replace with your actual bot token from @BotFather
$botToken = 'YOUR_BOT_TOKEN_HERE';

// Initialize the bot
$bot = new TelegramBot($botToken);

// Get updates from Telegram
$updates = $bot->getUpdates();

if ($updates && isset($updates['result'])) {
    foreach ($updates['result'] as $update) {
        // Check if the update contains a message
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'] ?? '';
            
            // Simple command handling
            if ($text === '/start') {
                $bot->sendMessage(
                    $chatId, 
                    "Welcome! I'm a simple Telegram bot.\n\nCommands:\n/start - Show this message\n/help - Show help\n/echo <text> - Echo your text",
                    'Markdown'
                );
            } elseif ($text === '/help') {
                $bot->sendMessage(
                    $chatId,
                    "Help: Send me any message and I'll echo it back!\n\nUse /start to see available commands.",
                    'Markdown'
                );
            } elseif (strpos($text, '/echo ') === 0) {
                $echoText = substr($text, 6);
                $bot->sendMessage($chatId, "You said: " . $echoText);
            } elseif ($text !== '') {
                // Echo any other message
                $bot->sendMessage($chatId, "You said: " . $text);
            }
        }
        
        // Handle callback queries (inline buttons)
        if (isset($update['callback_query'])) {
            $callbackQueryId = $update['callback_query']['id'];
            $data = $update['callback_query']['data'] ?? '';
            
            $bot->answerCallbackQuery($callbackQueryId, "Button clicked: " . $data);
            
            if (isset($update['callback_query']['message'])) {
                $chatId = $update['callback_query']['message']['chat']['id'];
                $bot->sendMessage($chatId, "You clicked: " . $data);
            }
        }
    }
}

echo "Bot processed successfully!\n";
