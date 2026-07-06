# Telegram Bot Framework v2.0.0

A modern, modular Telegram Bot framework built with PHP and MySQL.

## Features

- **MySQL Database**: Full MySQL support with automatic table creation
- **Modular Architecture**: Clean separation of concerns with namespaces
- **Control Panel**: Web-based admin panel for managing bots, users, and settings
- **Logging System**: File and database logging with rotation and sensitive data masking
- **Multi-Bot Support**: Manage multiple bots from a single installation
- **Admin Management**: Role-based admin user management
- **Cache System**: Built-in caching with expiration
- **Conversation Handler**: Stateful conversation management
- **Cron Jobs**: Scheduled task management
- **Broadcast System**: Mass messaging capabilities

## Project Structure

```
/workspace/
├── config/
│   └── config.php          # Centralized configuration
├── src/
│   ├── Bot/
│   │   └── TelegramBot.php  # Telegram API client
│   ├── Core/
│   │   ├── Config.php       # Configuration manager
│   │   └── Logger.php       # Logging system
│   ├── Controllers/
│   │   └── AdminController.php  # Admin panel controller
│   ├── Database/
│   │   └── Database.php     # MySQL database handler
│   ├── Helpers/            # Utility helpers
│   ├── Middleware/         # Request middleware
│   └── Models/             # Data models
├── storage/
│   ├── logs/               # Log files directory
│   ├── cache/              # File cache directory
│   └── locks/              # Cron lock files
├── dashboard/              # Web control panel (existing)
├── index.php               # Main entry point
├── webhook.php             # Webhook handler
└── README.md
```

## Installation

1. **Configure Database**
   Edit `config/config.php` or set environment variables:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=telegram_bot
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

2. **Set Admin Password**
   Change the default admin password in `config/config.php`:
   ```php
   'admin' => [
       'password' => 'CHANGE_THIS_STRONG_PASSWORD',
   ]
   ```

3. **Configure Logs Directory**
   Set your preferred logs path in `config/config.php`:
   ```php
   'logging' => [
       'path' => '/var/log/telegram_bot', // or leave default
   ]
   ```

4. **Add Your Bot**
   Use the control panel or programmatically:
   ```php
   $db->saveBot('YOUR_BOT_TOKEN', 'your_bot_username');
   ```

## Usage

### Basic Bot Setup

```php
require 'index.php';

use TelegramBot\Bot\TelegramBot;
use TelegramBot\Database\Database;

// Initialize bot
$bot = new TelegramBot('YOUR_BOT_TOKEN');

// Send message
$bot->sendMessage($chatId, 'Hello World!');

// Send photo
$bot->sendPhoto($chatId, 'path/to/photo.jpg');

// Get updates
$updates = $bot->getUpdates();
```

### Database Operations

```php
use TelegramBot\Database\Database;

$db = new Database([...]);

// Save user
$db->saveUser(['id' => 123456, 'username' => 'john']);

// Get setting
$value = $db->getSetting('language', userId: 123456);

// Cache
$db->setCache('key', $value, ttl: 3600);
$cachedValue = $db->getCache('key');
```

### Logging

```php
use TelegramBot\Core\Logger;

$logger = new Logger();
$logger->info('Bot started');
$logger->error('Something went wrong', ['context' => 'data']);
```

## Control Panel

Access the web-based control panel at:
```
http://yourdomain.com/dashboard/
```

Features:
- Dashboard with statistics
- Bot management (add, activate, deactivate, delete)
- Admin user management
- Database backup/restore
- Log viewing
- Settings management

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| DB_HOST | Database host | localhost |
| DB_PORT | Database port | 3306 |
| DB_DATABASE | Database name | telegram_bot |
| DB_USERNAME | Database username | root |
| DB_PASSWORD | Database password | |
| BOT_TOKEN | Default bot token | |
| WEBHOOK_URL | Webhook URL | |
| ADMIN_PASSWORD | Admin panel password | admin123 |
| LOG_PATH | Logs directory | ./storage/logs |
| LOG_LEVEL | Minimum log level | 1 (INFO) |
| APP_DEBUG | Debug mode | false |

## API Methods

The `TelegramBot` class supports all Telegram Bot API methods:

- **Messaging**: sendMessage, forwardMessage, copyMessage, sendPhoto, sendAudio, sendDocument, sendVideo, sendAnimation, sendVoice, sendVideoNote, sendMediaGroup, sendLocation, sendVenue, sendContact, sendPoll, sendDice
- **Chat Management**: banChatMember, unbanChatMember, restrictChatMember, promoteChatMember, getChat, getChatAdministrators, etc.
- **Messages**: editMessageText, editMessageCaption, deleteMessage, pinChatMessage, etc.
- **Payments**: sendInvoice, answerShippingQuery, answerPreCheckoutQuery
- **Webhooks**: setWebhook, deleteWebhook, getWebhookInfo
- **And many more...**

## License

MIT License
