<?php

require_once 'TelegramBot.php';
require_once 'InputFile.php';
require_once 'Keyboard.php';

/**
 * Advanced Example: Feature-Rich Telegram Bot
 * 
 * Demonstrates:
 * - File uploads with InputFile helper
 * - Complex keyboards with Keyboard helper
 * - Conversation states
 * - Admin commands
 * - Media groups
 * - Polls and quizzes
 */

// Configuration
$botToken = 'YOUR_BOT_TOKEN_HERE';
$bot = new TelegramBot($botToken);

// Simple in-memory state storage (use Redis/Database in production)
$userStates = [];

// Get updates
$updates = $bot->getUpdates();

if (!empty($updates)) {
    foreach ($updates as $update) {
        $chatId = $update['message']['chat']['id'] ?? null;
        $userId = $update['message']['from']['id'] ?? null;
        $text = $update['message']['text'] ?? '';
        $callbackData = $update['callback_query']['data'] ?? null;
        $callbackId = $update['callback_query']['id'] ?? null;

        // Handle callback queries (inline button clicks)
        if ($callbackData) {
            handleCallback($bot, $callbackId, $callbackData, $chatId);
            continue;
        }

        // Command: /start
        if ($text === '/start') {
            $keyboard = Keyboard::inline(
                Keyboard::inlineRow(
                    Keyboard::inlineButton('🌐 Visit Website', url: 'https://example.com'),
                    Keyboard::inlineButton('ℹ️ About', callback_data: 'about')
                ),
                Keyboard::inlineRow(
                    Keyboard::inlineButton('📊 Take Poll', callback_data: 'poll'),
                    Keyboard::inlineButton('📍 Send Location', callback_data: 'location_request')
                )
            );

            $bot->sendMessage(
                chat_id: $chatId,
                text: "👋 Welcome! I'm a feature-rich PHP Telegram Bot.\n\n" .
                      "Use the buttons below to explore my features!",
                reply_markup: $keyboard
            );
        }

        // Command: /admin
        elseif ($text === '/admin') {
            // Check if user is admin (implement your logic)
            $isAdmin = true; 
            
            if ($isAdmin) {
                $keyboard = Keyboard::reply(
                    Keyboard::replyRow('📢 Broadcast', '👥 Users'),
                    Keyboard::replyRow('🛑 Stop'),
                    resize_keyboard: true
                );

                $bot->sendMessage(
                    chat_id: $chatId,
                    text: "🔧 Admin Panel",
                    reply_markup: $keyboard
                );
            } else {
                $bot->sendMessage(chat_id: $chatId, text: "❌ Access denied.");
            }
        }

        // Command: /upload
        elseif ($text === '/upload') {
            // Example: Send a local image
            try {
                $photo = InputFile::local('path/to/your/image.jpg', 'demo_image.jpg');
                
                $bot->sendPhoto(
                    chat_id: $chatId,
                    photo: $photo,
                    caption: "📸 This is a photo uploaded from your server!"
                );
            } catch (Exception $e) {
                $bot->sendMessage(chat_id: $chatId, text: "Error: " . $e->getMessage());
            }
        }

        // Command: /media_group
        elseif ($text === '/media_group') {
            // Send multiple photos as an album
            $media = [
                ['type' => 'photo', 'media' => InputFile::url('https://via.placeholder.com/600x400/FF5733/FFFFFF?text=Image+1'), 'caption' => 'First Image'],
                ['type' => 'photo', 'media' => InputFile::url('https://via.placeholder.com/600x400/33FF57/FFFFFF?text=Image+2'), 'caption' => 'Second Image'],
                ['type' => 'photo', 'media' => InputFile::url('https://via.placeholder.com/600x400/3357FF/FFFFFF?text=Image+3')]
            ];

            $bot->sendMediaGroup(chat_id: $chatId, media: $media);
        }

        // Command: /quiz
        elseif ($text === '/quiz') {
            // Create a quiz poll
            $bot->sendPoll(
                chat_id: $chatId,
                question: "🧠 What is the capital of France?",
                options: ['London', 'Berlin', 'Paris', 'Madrid'],
                type: 'quiz',
                correct_option_id: 2,
                explanation: "Paris is the capital and most populous city of France."
            );
        }

        // State-based conversation example
        elseif ($userId && isset($userStates[$userId])) {
            handleState($bot, $userId, $chatId, $text, $userStates);
        }
    }
}

/**
 * Handle inline button callbacks
 */
function handleCallback($bot, $callbackId, $data, $chatId) {
    switch ($data) {
        case 'about':
            $bot->answerCallbackQuery(
                callback_query_id: $callbackId,
                text: "I'm a PHP bot built with the Telegram Bot API!",
                show_alert: false
            );
            
            $bot->sendMessage(
                chat_id: $chatId,
                text: "🤖 **About This Bot**\n\n" .
                      "Built with PHP using a comprehensive Telegram Bot API wrapper.\n\n" .
                      "✅ 100+ API methods\n" .
                      "✅ File uploads\n" .
                      "✅ Keyboards\n" .
                      "✅ Polls & Quizzes"
            );
            break;

        case 'poll':
            $bot->answerCallbackQuery(callback_query_id: $callbackId, text: "Creating poll...");
            
            $bot->sendPoll(
                chat_id: $chatId,
                question: "What's your favorite programming language?",
                options: ['PHP', 'Python', 'JavaScript', 'Go'],
                is_anonymous: false,
                allows_multiple_answers: true
            );
            break;

        case 'location_request':
            $bot->answerCallbackQuery(
                callback_query_id: $callbackId,
                text: "Please share your location using the attachment button!",
                show_alert: true
            );
            break;
    }
}

/**
 * Handle conversation states
 */
function handleState($bot, $userId, $chatId, $text, &$userStates) {
    $state = $userStates[$userId];

    if ($state === 'waiting_for_name') {
        $bot->sendMessage(
            chat_id: $chatId,
            text: "Nice to meet you, {$text}! 🎉\n\nWhat's your email address?"
        );
        $userStates[$userId] = 'waiting_for_email';
    } 
    elseif ($state === 'waiting_for_email') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $bot->sendMessage(
                chat_id: $chatId,
                text: "✅ Registration complete!\n\nName: {$text}\nWe'll be in touch!"
            );
            unset($userStates[$userId]);
        } else {
            $bot->sendMessage(
                chat_id: $chatId,
                text: "❌ Invalid email. Please try again:"
            );
        }
    }
}

// Mark updates as read
if (!empty($updates)) {
    $lastUpdateId = end($updates)['update_id'];
    // In production, store this offset properly
    // $bot->getUpdates(offset: $lastUpdateId + 1);
}
