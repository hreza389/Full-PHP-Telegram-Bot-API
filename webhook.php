<?php
/**
 * Webhook handler example for Telegram Bot
 * 
 * This file should be set as your webhook URL in Telegram.
 * Configure it with: $bot->setWebhook('https://your-domain.com/webhook.php');
 */

require_once 'TelegramBot.php';

// Replace with your actual bot token from @BotFather
$botToken = 'YOUR_BOT_TOKEN_HERE';

// Initialize the bot
$bot = new TelegramBot($botToken);

// Get the update from POST data
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Handle messages
    if (isset($update['message'])) {
        $chatId = $update['message']['chat']['id'];
        $text = $update['message']['text'] ?? '';
        
        // Show typing action
        $bot->sendChatAction($chatId, 'typing');
        
        // Command handling
        switch ($text) {
            case '/start':
                // Send message with inline keyboard
                $inlineKeyboard = [
                    [['text' => 'Button 1', 'callback_data' => 'btn1']],
                    [['text' => 'Button 2', 'callback_data' => 'btn2']]
                ];
                $bot->sendMessageWithInlineKeyboard(
                    $chatId,
                    "Welcome! Choose an option:",
                    $inlineKeyboard
                );
                break;
                
            case '/help':
                $bot->sendMessage(
                    $chatId,
                    "Available commands:\n/start - Start the bot\n/help - Show this help\n/photo - Send a photo demo",
                    'Markdown'
                );
                break;
                
            case '/photo':
                // You would need a valid photo path or URL here
                // $bot->sendPhoto($chatId, '/path/to/photo.jpg', 'Here is a photo!');
                $bot->sendMessage($chatId, "Photo feature - configure the path in webhook.php");
                break;
                
            default:
                $bot->sendMessage($chatId, "You said: " . $text);
        }
    }
    
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $callbackQueryId = $update['callback_query']['id'];
        $data = $update['callback_query']['data'] ?? '';
        
        $bot->answerCallbackQuery($callbackQueryId, "You clicked: " . $data);
        
        if (isset($update['callback_query']['message'])) {
            $chatId = $update['callback_query']['message']['chat']['id'];
            $bot->sendMessage($chatId, "Callback received: " . $data);
        }
    }
}

// Return empty response to Telegram
http_response_code(200);
