# 📚 Telegram Bot Framework - Complete File Map & Documentation

## Overview

This is a comprehensive PHP Telegram Bot Framework with MySQL database support, web-based control panel, and extensive documentation.

---

## 🗂️ File Structure

```
/workspace/
├── README.md                      # Project overview and quick start guide
├── FILE_MAP.md                    # This file - detailed documentation of all files
├── TelegramDB.php                 # MySQL database handler (ALL DB operations)
├── control_panel.php              # Web-based admin control panel
├── TelegramBot.php                # Main Telegram Bot API class (179 methods)
├── TelegramBotDispatcher.php      # Request dispatcher and routing
├── TelegramBroadcast.php          # Broadcast/messaging system
├── TelegramCache.php              # Caching mechanisms
├── TelegramCron.php               # Cron job scheduler
├── TelegramDeepLinking.php        # Deep linking utilities
├── TelegramLogger.php             # Logging system
├── TelegramMiddleware.php         # Middleware pipeline
├── TelegramResponse.php           # Response handling
├── TelegramStats.php              # Statistics and analytics
├── TelegramTypes.php              # Type definitions and helpers
├── InputFile.php                  # File upload helper
├── Keyboard.php                   # Keyboard builder helper
├── webhook.php                    # Webhook endpoint example
├── example.php                    # Basic bot example
├── advanced_example.php           # Advanced features example
├── enterprise_example.php         # Enterprise-level example
└── framework_example.php          # Framework integration example
```

---

## 📄 Detailed File Documentation

### 1. `TelegramDB.php` ⭐ (NEW - MySQL Version)

**Purpose:** Centralized MySQL database management for ALL database operations.

**Key Features:**
- Automatic database and table creation
- Backup and restore functionality (phpMyAdmin compatible)
- Database statistics and monitoring
- User, Chat, Settings, Bot management
- Conversation state management
- Logging system
- Cache system
- Cron job management
- Transaction support

**Configuration:**
```php
$db = new TelegramDB([
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'telegram_bot',
    'username' => 'root',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'prefix' => '' // Optional table prefix
]);
```

**Tables Created:**
| Table | Purpose |
|-------|---------|
| `users` | Telegram user information |
| `chats` | Telegram chat information |
| `user_chats` | User-chat relationships |
| `settings` | Key-value settings storage |
| `bot_settings` | Bot configurations |
| `conversations` | Multi-step conversation states |
| `logs` | Application logs |
| `updates` | Raw Telegram updates |
| `broadcasts` | Broadcast message tracking |
| `cron_jobs` | Scheduled tasks |
| `cache` | Temporary cache storage |

**Main Methods:**

**Core Operations:**
- `execute($sql, $params)` - Execute SQL query
- `insert($table, $data)` - Insert record
- `upsert($table, $data, $updateColumns)` - Insert or update
- `update($table, $data, $where, $whereParams)` - Update records
- `delete($table, $where, $params)` - Delete records
- `select($table, $columns, $where, $params, $orderBy, $limit, $offset)` - Select records
- `selectOne($table, $columns, $where, $params)` - Select single record
- `count($table, $where, $params)` - Count records

**User Management:**
- `saveUser($userData)` - Save/update user
- `getUser($telegramId)` - Get user by ID
- `getAllUsers($limit, $offset)` - Get all users
- `getUserCount()` - Get total user count

**Chat Management:**
- `saveChat($chatData)` - Save/update chat
- `getChat($telegramId)` - Get chat by ID
- `getAllChats($limit, $offset)` - Get all chats

**Settings Management:**
- `setSetting($key, $value, $userId, $chatId)` - Set setting value
- `getSetting($key, $default, $userId, $chatId)` - Get setting value
- `deleteSetting($key, $userId, $chatId)` - Delete setting

**Bot Management:**
- `saveBot($token, $username, $name, $settings)` - Register bot
- `getBotByToken($token)` - Get bot by token
- `getBotByUsername($username)` - Get bot by username
- `getAllBots($activeOnly)` - Get all bots
- `setBotActive($username, $isActive)` - Activate/deactivate bot
- `setBotWebhook($username, $url)` - Set webhook URL

**Conversation Management:**
- `saveConversation($userId, $chatId, $state, $data, $expiresAt)` - Save conversation
- `getConversation($userId, $chatId)` - Get conversation
- `deleteConversation($userId, $chatId)` - Delete conversation
- `cleanExpiredConversations()` - Clean expired conversations

**Logging:**
- `log($message, $level, $category, $context, $userId, $chatId)` - Add log entry
- `getLogs($level, $category, $limit, $offset)` - Get logs
- `clearOldLogs($daysOld)` - Clear old logs

**Cache:**
- `setCache($key, $value, $ttl)` - Set cache value
- `getCache($key, $default)` - Get cache value
- `deleteCache($key)` - Delete cache entry
- `cleanExpiredCache()` - Clean expired cache

**Database Management:**
- `stats()` - Get database statistics
- `backup($filename, $includeData)` - Create backup (SQL dump)
- `restore($filename)` - Restore from SQL file
- `truncateAll()` - Clear all data (DANGER!)
- `getTables()` - Get list of tables
- `optimize()` - Optimize all tables

**Transactions:**
- `beginTransaction()` - Start transaction
- `commit()` - Commit transaction
- `rollback()` - Rollback transaction

---

### 2. `control_panel.php` ⭐ (NEW)

**Purpose:** Web-based administrative control panel for managing bots, database, and settings.

**Access:** `http://yourdomain.com/control_panel.php`

**Features:**
- 🔐 Password-protected login
- 📊 Dashboard with real-time statistics
- 🤖 Bot management (add, activate, deactivate, delete)
- 💾 Database backup and restore
- 👥 User viewing and management
- 📝 Log viewing and cleanup
- ⚙️ System settings display
- 📅 Cron job monitoring

**Configuration (edit at top of file):**
```php
define('DATABASE_CONFIG', [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'telegram_bot',
    'username' => 'root',
    'password' => 'YOUR_PASSWORD', // CHANGE THIS!
    'charset' => 'utf8mb4'
]);

define('ADMIN_PASSWORD', 'CHANGE_THIS_STRONG_PASSWORD'); // CHANGE THIS!
```

**Sections:**
1. **Dashboard** - Overview statistics and quick actions
2. **Bots** - Manage multiple bot configurations
3. **Database** - Backup, restore, optimize, truncate
4. **Users** - View all registered users
5. **Logs** - View and clean system logs
6. **Settings** - System configuration and cron jobs

---

### 3. `TelegramBot.php`

**Purpose:** Complete Telegram Bot API implementation with 179 methods.

**Key Features:**
- Full Telegram Bot API coverage
- File upload support
- Named parameters (PHP 8+)
- Comprehensive error handling

**Method Categories:**

**Getting Updates:**
- `getUpdates()`, `setWebhook()`, `deleteWebhook()`, `getWebhookInfo()`

**Sending Messages:**
- `sendMessage()`, `sendPhoto()`, `sendVideo()`, `sendAudio()`, `sendDocument()`
- `sendAnimation()`, `sendVoice()`, `sendVideoNote()`, `sendPaidMedia()`
- `sendMediaGroup()`, `sendLocation()`, `sendVenue()`, `sendContact()`
- `sendPoll()`, `sendDice()`, `sendChatAction()`, `forwardMessage()`, `copyMessage()`
- `sendInvoice()`, `sendGame()`, `sendSticker()`

**Editing Messages:**
- `editMessageText()`, `editMessageCaption()`, `editMessageMedia()`
- `editMessageLiveLocation()`, `editMessageReplyMarkup()`
- `deleteMessage()`, `deleteMessages()`

**Chat Management:**
- `banChatMember()`, `unbanChatMember()`, `restrictChatMember()`
- `promoteChatMember()`, `setChatPermissions()`
- `createChatInviteLink()`, `editChatInviteLink()`, `revokeChatInviteLink()`
- `pinChatMessage()`, `unpinChatMessage()`, `unpinAllChatMessages()`
- Forum topics management

**And Many More:**
- Sticker management
- Inline mode
- Payments & Stars
- Business features
- Bot commands and menus

---

### 4. `TelegramBotDispatcher.php`

**Purpose:** Request routing and dispatching for bot updates.

**Methods:**
- `dispatch($update)` - Route update to appropriate handler
- `onMessage($handler)` - Handle text messages
- `onCallback($handler)` - Handle callback queries
- `onCommand($command, $handler)` - Handle bot commands
- `run()` - Start dispatcher loop

---

### 5. `TelegramBroadcast.php`

**Purpose:** Broadcast messages to multiple users/chats.

**Methods:**
- `broadcast($message, $recipients)` - Send broadcast
- `getProgress($broadcastId)` - Get broadcast progress
- `cancel($broadcastId)` - Cancel ongoing broadcast

---

### 6. `TelegramCache.php`

**Purpose:** Caching mechanisms for performance optimization.

**Methods:**
- `get($key)` - Get cached value
- `set($key, $value, $ttl)` - Set cache value
- `delete($key)` - Delete cache entry
- `clear()` - Clear all cache

---

### 7. `TelegramCron.php`

**Purpose:** Cron job scheduling and execution.

**Methods:**
- `register($name, $expression, $callback)` - Register cron job
- `run()` - Execute due jobs
- `getNextRun($expression)` - Calculate next run time

---

### 8. `TelegramDeepLinking.php`

**Purpose:** Telegram deep linking utilities.

**Methods:**
- `generateLink($botUsername, $param)` - Generate deep link
- `parseLink($link)` - Parse deep link parameter

---

### 9. `TelegramLogger.php`

**Purpose:** Application logging system.

**Methods:**
- `info($message, $context)` - Info level log
- `warning($message, $context)` - Warning level log
- `error($message, $context)` - Error level log
- `debug($message, $context)` - Debug level log

---

### 10. `TelegramMiddleware.php`

**Purpose:** Middleware pipeline for request processing.

**Methods:**
- `use($middleware)` - Add middleware
- `handle($update)` - Process through middleware chain

---

### 11. `TelegramResponse.php`

**Purpose:** HTTP response handling for webhooks.

**Methods:**
- `json($data)` - JSON response
- `success()` - Success response
- `error($message)` - Error response

---

### 12. `TelegramStats.php`

**Purpose:** Statistics and analytics collection.

**Methods:**
- `getUserCount()` - Total users
- `getMessageCount()` - Total messages
- `getInteractionStats()` - User interaction stats

---

### 13. `TelegramTypes.php`

**Purpose:** Type definitions and helper classes.

**Classes:**
- `User` - User object
- `Chat` - Chat object
- `Message` - Message object
- `Update` - Update object

---

### 14. `InputFile.php`

**Purpose:** Helper for file uploads.

**Methods:**
- `local($path, $name)` - Upload local file
- `url($url)` - Upload from URL
- `id($fileId)` - Use existing file ID
- `content($data, $name)` - Upload raw content

**Usage:**
```php
$photo = InputFile::local('/path/to/image.jpg', 'custom.jpg');
$bot->sendPhoto(chat_id: $chatId, photo: $photo);
```

---

### 15. `Keyboard.php`

**Purpose:** Keyboard builder helper.

**Methods:**
- `inline($rows)` - Create inline keyboard
- `reply($rows, $options)` - Create reply keyboard
- `inlineButton($text, $options)` - Create inline button
- `replyButton($text)` - Create reply button
- `remove()` - Remove keyboard
- `forceReply()` - Force reply

**Usage:**
```php
$keyboard = Keyboard::inline(
    Keyboard::inlineRow(
        Keyboard::inlineButton('Click Me', callback_data: 'click')
    )
);
```

---

### 16. Example Files

#### `example.php`
Basic long polling bot example.

#### `advanced_example.php`
Demonstrates advanced features:
- File uploads
- Complex keyboards
- Conversation flows
- Media groups
- Polls & quizzes

#### `enterprise_example.php`
Enterprise-level bot with:
- Multiple bots
- Database integration
- Advanced error handling
- Monitoring

#### `framework_example.php`
Integration with popular frameworks:
- Laravel
- Symfony
- Slim

#### `webhook.php`
Webhook endpoint setup and handling.

---

## 🎯 Quick Reference

### Database Setup
```php
require_once 'TelegramDB.php';

$db = new TelegramDB([
    'host' => 'localhost',
    'database' => 'telegram_bot',
    'username' => 'root',
    'password' => 'password'
]);
```

### Control Panel Access
1. Edit `control_panel.php` and set `ADMIN_PASSWORD`
2. Access via browser: `http://yourdomain.com/control_panel.php`
3. Login with admin password
4. Manage bots, database, users, and settings

### Basic Bot
```php
require_once 'TelegramBot.php';

$bot = new TelegramBot('YOUR_TOKEN');
$update = $bot->getUpdates();
// Process updates...
```

### File Upload
```php
require_once 'InputFile.php';

$photo = InputFile::local('/path/image.jpg');
$bot->sendPhoto(chat_id: $chatId, photo: $photo);
```

### Keyboard
```php
require_once 'Keyboard.php';

$keyboard = Keyboard::inline(
    Keyboard::inlineRow(
        Keyboard::inlineButton('Button 1', callback_data: 'b1'),
        Keyboard::inlineButton('Button 2', callback_data: 'b2')
    )
);
```

---

## 📊 Database Schema

All tables use InnoDB engine with utf8mb4 charset.

### Indexes
- Primary keys on `id`
- Foreign keys with CASCADE delete
- Performance indexes on frequently queried columns

---

## 🔒 Security Notes

1. **Change default passwords** in `control_panel.php`
2. **Use HTTPS** in production
3. **Validate all inputs** from Telegram
4. **Hash bot tokens** before storing (done automatically)
5. **Use prepared statements** (implemented in TelegramDB)

---

## 📝 Best Practices

1. Use environment variables for sensitive config
2. Enable debug mode only in development
3. Regular database backups
4. Monitor logs for errors
5. Clean expired cache and conversations periodically
6. Use transactions for multi-step operations

---

## 🆘 Troubleshooting

**Connection Issues:**
- Check MySQL credentials
- Verify MySQL is running
- Check firewall settings

**Permission Errors:**
- Ensure MySQL user has CREATE, INSERT, UPDATE, DELETE privileges
- Check file permissions for backup directory

**Backup/Restore:**
- Ensure backup directory is writable
- Check disk space
- Verify SQL file format

---

## 📞 Support

For issues and questions:
1. Check this documentation
2. Review example files
3. Check logs in control panel
4. Examine database statistics

---

*Last Updated: 2024*
*Version: 2.0.0 (MySQL)*
