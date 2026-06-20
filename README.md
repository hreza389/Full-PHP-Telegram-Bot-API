# PHP Telegram Bot API - Complete Implementation

A comprehensive, production-ready PHP implementation of the complete Telegram Bot API with helper classes for easier development.

## 📦 Files Included

1. **TelegramBot.php** - Main class with 179 methods covering the entire Telegram Bot API
2. **InputFile.php** - Helper for handling file uploads (local files, URLs, file IDs)
3. **Keyboard.php** - Helper for creating inline and reply keyboards
4. **example.php** - Basic long polling example
5. **webhook.php** - Webhook handler example
6. **advanced_example.php** - Feature-rich bot demonstrating all capabilities

## ✨ New Features Added

### 1. InputFile Helper Class
Simplifies file handling for photos, documents, videos, etc.

```php
// Local file upload
$photo = InputFile::local('/path/to/image.jpg', 'custom_name.jpg');

// URL (Telegram downloads it)
$photo = InputFile::url('https://example.com/image.jpg');

// Existing Telegram file_id
$photo = InputFile::id('AgADBAAD...');

// Raw content (generated images)
$photo = InputFile::content($imageData, 'generated.png');

// Usage in bot methods
$bot->sendPhoto(chat_id: $chatId, photo: $photo, caption: 'My photo');
```

### 2. Keyboard Helper Class
Creates complex keyboards with clean syntax.

```php
// Inline Keyboard
$keyboard = Keyboard::inline(
    Keyboard::inlineRow(
        Keyboard::inlineButton('🌐 Website', url: 'https://example.com'),
        Keyboard::inlineButton('ℹ️ Info', callback_data: 'info')
    ),
    Keyboard::inlineRow(
        Keyboard::inlineButton('🔍 Search', switch_inline_query: 'query')
    )
);

// Reply Keyboard
$keyboard = Keyboard::reply(
    Keyboard::replyRow('📢 Broadcast', '👥 Users'),
    Keyboard::replyRow('🛑 Stop'),
    resize_keyboard: true,
    one_time_keyboard: false
);

// Remove keyboard
$remove = Keyboard::remove();

// Force reply
$force = Keyboard::forceReply(selective: true);
```

### 3. Advanced Features Demonstrated

#### Conversation States
```php
// State-based conversations for multi-step forms
$userStates[$userId] = 'waiting_for_email';
```

#### Media Groups (Albums)
```php
$media = [
    ['type' => 'photo', 'media' => InputFile::url('...'), 'caption' => 'Image 1'],
    ['type' => 'photo', 'media' => InputFile::url('...'), 'caption' => 'Image 2'],
    ['type' => 'video', 'media' => InputFile::local('video.mp4')]
];
$bot->sendMediaGroup(chat_id: $chatId, media: $media);
```

#### Polls & Quizzes
```php
// Regular poll
$bot->sendPoll(
    chat_id: $chatId,
    question: "Favorite language?",
    options: ['PHP', 'Python', 'JavaScript'],
    allows_multiple_answers: true
);

// Quiz with correct answer
$bot->sendPoll(
    chat_id: $chatId,
    question: "Capital of France?",
    options: ['London', 'Berlin', 'Paris', 'Madrid'],
    type: 'quiz',
    correct_option_id: 2,
    explanation: "Paris is the capital of France."
);
```

#### Callback Query Handling
```php
if ($callbackData) {
    $bot->answerCallbackQuery(
        callback_query_id: $callbackId,
        text: "Response message",
        show_alert: true
    );
}
```

## 🚀 Quick Start

### 1. Install Requirements
- PHP 8.0+ (for named parameters and union types)
- cURL extension enabled
- A Telegram Bot Token from [@BotFather](https://t.me/BotFather)

### 2. Basic Bot (Long Polling)
```bash
php example.php
```

Edit `example.php` and replace `YOUR_BOT_TOKEN_HERE` with your actual token.

### 3. Webhook Setup (Production)
```php
// First, set the webhook once
$bot->setWebhook(url: 'https://yourdomain.com/webhook.php');

// Then access webhook.php via browser/cron
```

### 4. Advanced Bot
```bash
php advanced_example.php
```

Demonstrates:
- File uploads
- Complex keyboards
- Conversation flows
- Admin panels
- Media groups
- Polls & quizzes

## 📚 Available API Methods (179 total)

### Getting Updates
- `getUpdates()`, `setWebhook()`, `deleteWebhook()`, `getWebhookInfo()`

### General Methods
- `sendMessage()`, `sendPhoto()`, `sendVideo()`, `sendAudio()`, `sendDocument()`
- `sendAnimation()`, `sendVoice()`, `sendVideoNote()`, `sendPaidMedia()`
- `sendMediaGroup()`, `sendLocation()`, `sendVenue()`, `sendContact()`
- `sendPoll()`, `sendDice()`, `sendChatAction()`, `forwardMessage()`, `copyMessage()`
- `sendInvoice()`, `sendGame()`, `sendSticker()`

### Message Editing
- `editMessageText()`, `editMessageCaption()`, `editMessageMedia()`
- `editMessageLiveLocation()`, `editMessageReplyMarkup()`
- `deleteMessage()`, `deleteMessages()`

### Chat Management
- `banChatMember()`, `unbanChatMember()`, `restrictChatMember()`
- `promoteChatMember()`, `setChatPermissions()`
- `createChatInviteLink()`, `editChatInviteLink()`, `revokeChatInviteLink()`
- `pinChatMessage()`, `unpinChatMessage()`, `unpinAllChatMessages()`
- `setChatTitle()`, `setChatDescription()`, `setChatPhoto()`
- Forum topics management (`createForumTopic()`, `closeForumTopic()`, etc.)

### Stickers
- Full sticker set management (create, add, remove, modify)

### Inline Mode
- `answerInlineQuery()`, `setMyCommands()`, `getMyCommands()`
- Bot names, descriptions, menu buttons

### Payments & Stars
- `sendInvoice()`, `createInvoiceLink()`
- `getMyStarBalance()`, `getStarTransactions()`, `refundStarPayment()`

### Business Features
- Business account management
- Gifts, stories, verification

### And Many More!
See `TelegramBot.php` for complete method list with PHPDoc documentation.

## 🎯 Best Practices

1. **Error Handling**: All methods throw exceptions on API errors
2. **Named Parameters**: Use PHP 8+ named parameters for clarity
3. **File Cleanup**: Temp files are created for raw content uploads
4. **State Management**: Use Redis/Database for production conversation states
5. **Security**: Validate all user inputs, especially in callbacks

## 📝 Example Usage

```php
<?php
require_once 'TelegramBot.php';
require_once 'InputFile.php';
require_once 'Keyboard.php';

$bot = new TelegramBot('YOUR_TOKEN');

// Send message with inline keyboard
$keyboard = Keyboard::inline(
    Keyboard::inlineRow(
        Keyboard::inlineButton('Click Me', callback_data: 'click')
    )
);

$bot->sendMessage(
    chat_id: $chatId,
    text: 'Hello!',
    reply_markup: $keyboard
);

// Handle callback
if ($update['callback_query']['data'] === 'click') {
    $bot->answerCallbackQuery(
        callback_query_id: $callbackId,
        text: 'Button clicked!',
        show_alert: true
    );
}
```

## 🔗 Resources

- [Official Telegram Bot API](https://core.telegram.org/bots/api)
- [BotFather](https://t.me/BotFather)
- [Telegram Bot Documentation](https://core.telegram.org/bots)

## 📄 License

MIT License - Free to use for personal and commercial projects.