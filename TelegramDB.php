<?php

/**
 * Telegram Database Handler - MySQL Version
 * 
 * A comprehensive MySQL database wrapper for storing persistent data
 * like user information, settings, bot state, backups, and management.
 * 
 * This file consolidates ALL database creation, backup, and management operations.
 * Designed for use with phpMyAdmin for easy database administration.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 2.0.0 (MySQL)
 * 
 * USAGE:
 * ------
 * $db = new TelegramDB([
 *     'host' => 'localhost',
 *     'port' => 3306,
 *     'database' => 'telegram_bot',
 *     'username' => 'root',
 *     'password' => 'your_password',
 *     'charset' => 'utf8mb4'
 * ]);
 * 
 * FEATURES:
 * ---------
 * - Automatic table creation on first connection
 * - Backup and restore functionality
 * - Database statistics and monitoring
 * - User, Chat, Settings, Conversations, Logs management
 * - Transaction support
 * - Prepared statements for SQL injection prevention
 */

class TelegramDB
{
    /**
     * @var PDO|null Database connection
     */
    private ?PDO $pdo = null;

    /**
     * @var array Database configuration
     */
    private array $config;

    /**
     * @var bool Enable query logging
     */
    private bool $debug = false;

    /**
     * @var string Default database name
     */
    private const DEFAULT_DATABASE = 'telegram_bot';

    /**
     * Constructor
     * 
     * @param array $config Database configuration
     * @throws RuntimeException If connection fails
     * 
     * Configuration options:
     * - host: Database host (default: localhost)
     * - port: Database port (default: 3306)
     * - database: Database name (default: telegram_bot)
     * - username: Database username (required)
     * - password: Database password (required)
     * - charset: Character set (default: utf8mb4)
     * - prefix: Table prefix (default: empty)
     */
    public function __construct(array $config = [])
    {
        $this->config = [
            'host' => $config['host'] ?? 'localhost',
            'port' => $config['port'] ?? 3306,
            'database' => $config['database'] ?? self::DEFAULT_DATABASE,
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'prefix' => $config['prefix'] ?? ''
        ];

        $this->connect();
        $this->initializeTables();
    }

    /**
     * Connect to MySQL database
     * 
     * Creates database if it doesn't exist, then connects to it.
     * 
     * @return void
     * @throws RuntimeException If connection fails
     */
    private function connect(): void
    {
        try {
            // First, connect without database to create it if needed
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};charset={$this->config['charset']}";
            
            $tempPdo = new PDO($dsn, $this->config['username'], $this->config['password']);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $dbName = $this->config['database'];
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$this->config['charset']} COLLATE {$this->config['charset']}_unicode_ci");
            
            // Now connect to the actual database
            $dsn .= ";dbname={$dbName}";
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password']);
            
            // Set PDO attributes
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            unset($tempPdo);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get table name with prefix
     * 
     * @param string $table Base table name
     * @return string Table name with prefix
     */
    private function tableName(string $table): string
    {
        return $this->config['prefix'] . $table;
    }

    /**
     * Enable/disable debug mode
     * 
     * @param bool $enabled Debug status
     * @return self
     */
    public function setDebug(bool $enabled): self
    {
        $this->debug = $enabled;
        return $this;
    }

    /**
     * Get PDO instance
     * 
     * @return PDO Database connection
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get database configuration
     * 
     * @return array Configuration array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Initialize all database tables
     * 
     * Creates all necessary tables if they don't exist.
     * Safe to call multiple times.
     * 
     * @return void
     */
    private function initializeTables(): void
    {
        // Users table - stores Telegram user information
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('users')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                username VARCHAR(255),
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                language_code VARCHAR(10),
                is_bot TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_telegram_id (telegram_id),
                INDEX idx_username (username),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Chats table - stores Telegram chat information
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('chats')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                type VARCHAR(50),
                title VARCHAR(255),
                username VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_telegram_id (telegram_id),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // User-chat relationships table
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('user_chats')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                chat_id BIGINT NOT NULL,
                role VARCHAR(50) DEFAULT 'member',
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_chat (user_id, chat_id),
                FOREIGN KEY (user_id) REFERENCES {$this->tableName('users')}(telegram_id) ON DELETE CASCADE,
                FOREIGN KEY (chat_id) REFERENCES {$this->tableName('chats')}(telegram_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_chat_id (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Settings table - key-value storage for bot and user settings
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('settings')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT,
                chat_id BIGINT,
                setting_key VARCHAR(255) NOT NULL,
                setting_value TEXT,
                setting_type VARCHAR(50) DEFAULT 'string',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_setting (user_id, chat_id, setting_key),
                INDEX idx_key (setting_key),
                INDEX idx_user_id (user_id),
                INDEX idx_chat_id (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Bot settings table - global bot configuration
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('bot_settings')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bot_token_hash VARCHAR(64) UNIQUE NOT NULL,
                bot_username VARCHAR(255),
                bot_name VARCHAR(255),
                is_active TINYINT DEFAULT 1,
                webhook_url VARCHAR(500),
                last_update_id BIGINT DEFAULT 0,
                settings_json JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bot_username (bot_username),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Conversations table - stores conversation states for multi-step interactions
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('conversations')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                chat_id BIGINT NOT NULL,
                state VARCHAR(255) NOT NULL,
                data JSON,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_chat_id (chat_id),
                INDEX idx_state (state),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Logs table - application logs
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('logs')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) DEFAULT 'INFO',
                category VARCHAR(100),
                message TEXT,
                context JSON,
                user_id BIGINT,
                chat_id BIGINT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_category (category),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Updates table - stores raw Telegram updates for processing
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('updates')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                update_id BIGINT UNIQUE NOT NULL,
                update_data JSON NOT NULL,
                processed TINYINT DEFAULT 0,
                processed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_update_id (update_id),
                INDEX idx_processed (processed),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Broadcasts table - tracks broadcast messages
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('broadcasts')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_text TEXT,
                total_recipients INT DEFAULT 0,
                successful_sends INT DEFAULT 0,
                failed_sends INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'pending',
                started_at DATETIME,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Cron jobs table - scheduled tasks
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('cron_jobs')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_name VARCHAR(255) NOT NULL,
                cron_expression VARCHAR(100),
                last_run DATETIME,
                next_run DATETIME,
                is_active TINYINT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_job_name (job_name),
                INDEX idx_is_active (is_active),
                INDEX idx_next_run (next_run)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Cache table - temporary cache storage
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('cache')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) UNIQUE NOT NULL,
                cache_value TEXT,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cache_key (cache_key),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Execute a SQL query
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement|false Result or false on failure
     */
    public function execute(string $sql, array $params = []): PDOStatement|false
    {
        try {
            if ($this->debug) {
                echo "[DB] {$sql} " . json_encode($params) . PHP_EOL;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if ($this->debug) {
                echo "[DB Error] " . $e->getMessage() . PHP_EOL;
            }
            return false;
        }
    }

    /**
     * Insert a record into a table
     * 
     * @param string $table Table name
     * @param array $data Data to insert (key-value pairs)
     * @return int|false Last insert ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->tableName($table)} ({$columns}) VALUES ({$placeholders})";

        if ($this->execute($sql, $data)) {
            return (int)$this->pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Insert or update a record (upsert)
     * 
     * @param string $table Table name
     * @param array $data Data to insert/update
     * @param array|string|null $updateColumns Columns to update on duplicate key (null for all except primary key)
     * @return int|false Last insert ID or false on failure
     */
    public function upsert(string $table, array $data, array|string|null $updateColumns = null): int|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        if ($updateColumns === null) {
            $updateColumns = array_diff(array_keys($data), ['id']);
        }

        if (is_string($updateColumns)) {
            $updateColumns = [$updateColumns];
        }

        $updateSet = implode(', ', array_map(fn($col) => "{$col} = VALUES({$col})", $updateColumns));

        $sql = "INSERT INTO {$this->tableName($table)} ({$columns}) VALUES ({$placeholders}) 
                ON DUPLICATE KEY UPDATE {$updateSet}, updated_at = CURRENT_TIMESTAMP";

        if ($this->execute($sql, $data)) {
            return (int)$this->pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Update records in a table
     * 
     * @param string $table Table name
     * @param array $data Data to update (key-value pairs)
     * @param string $where WHERE clause (without 'WHERE')
     * @param array $whereParams WHERE parameters
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE {$this->tableName($table)} SET {$setClause}, updated_at = CURRENT_TIMESTAMP WHERE {$where}";

        $params = array_merge($data, $whereParams);
        $stmt = $this->execute($sql, $params);

        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Delete records from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause (without 'WHERE')
     * @param array $params WHERE parameters
     * @return int Number of deleted rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$this->tableName($table)} WHERE {$where}";
        $stmt = $this->execute($sql, $params);

        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Select records from a table
     * 
     * @param string $table Table name
     * @param array $columns Columns to select (empty for all)
     * @param string|null $where WHERE clause (without 'WHERE')
     * @param array $params WHERE parameters
     * @param string|null $orderBy ORDER BY clause (without 'ORDER BY')
     * @param int|null $limit LIMIT
     * @param int|null $offset OFFSET
     * @return array Results
     */
    public function select(
        string $table,
        array $columns = [],
        ?string $where = null,
        array $params = [],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $selectClause = empty($columns) ? '*' : implode(', ', $columns);

        $sql = "SELECT {$selectClause} FROM {$this->tableName($table)}";

        if ($where !== null) {
            $sql .= " WHERE {$where}";
        }

        if ($orderBy !== null) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        $stmt = $this->execute($sql, $params);

        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Select a single record from a table
     * 
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $where WHERE clause (without 'WHERE')
     * @param array $params WHERE parameters
     * @return array|null Single record or null if not found
     */
    public function selectOne(
        string $table,
        array $columns = [],
        string $where,
        array $params = []
    ): ?array {
        $results = $this->select($table, $columns, $where, $params, null, 1);
        return $results[0] ?? null;
    }

    /**
     * Count records in a table
     * 
     * @param string $table Table name
     * @param string|null $where WHERE clause (without 'WHERE')
     * @param array $params WHERE parameters
     * @return int Count of records
     */
    public function count(string $table, ?string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->tableName($table)}";

        if ($where !== null) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->execute($sql, $params);
        $result = $stmt ? $stmt->fetch() : null;

        return (int)($result['count'] ?? 0);
    }

    // ==================== User Methods ====================

    /**
     * Save or update a user
     * 
     * @param array $userData User data from Telegram API
     * @return int User database ID
     */
    public function saveUser(array $userData): int
    {
        $data = [
            'telegram_id' => $userData['id'],
            'username' => $userData['username'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'language_code' => $userData['language_code'] ?? null,
            'is_bot' => $userData['is_bot'] ? 1 : 0
        ];

        return $this->upsert('users', $data, 'telegram_id') ?: 0;
    }

    /**
     * Get user by Telegram ID
     * 
     * @param int $telegramId Telegram user ID
     * @return array|null User data or null if not found
     */
    public function getUser(int $telegramId): ?array
    {
        return $this->selectOne('users', [], 'telegram_id = ?', [$telegramId]);
    }

    /**
     * Get all users with pagination
     * 
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array Array of users
     */
    public function getAllUsers(int $limit = 100, int $offset = 0): array
    {
        return $this->select('users', [], null, [], 'created_at DESC', $limit, $offset);
    }

    /**
     * Get total user count
     * 
     * @return int Total number of users
     */
    public function getUserCount(): int
    {
        return $this->count('users');
    }

    // ==================== Chat Methods ====================

    /**
     * Save or update a chat
     * 
     * @param array $chatData Chat data from Telegram API
     * @return int Chat database ID
     */
    public function saveChat(array $chatData): int
    {
        $data = [
            'telegram_id' => $chatData['id'],
            'type' => $chatData['type'] ?? null,
            'title' => $chatData['title'] ?? null,
            'username' => $chatData['username'] ?? null
        ];

        return $this->upsert('chats', $data, 'telegram_id') ?: 0;
    }

    /**
     * Get chat by Telegram ID
     * 
     * @param int $telegramId Telegram chat ID
     * @return array|null Chat data or null if not found
     */
    public function getChat(int $telegramId): ?array
    {
        return $this->selectOne('chats', [], 'telegram_id = ?', [$telegramId]);
    }

    /**
     * Get all chats with pagination
     * 
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array Array of chats
     */
    public function getAllChats(int $limit = 100, int $offset = 0): array
    {
        return $this->select('chats', [], null, [], 'created_at DESC', $limit, $offset);
    }

    // ==================== Settings Methods ====================

    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value (will be JSON encoded if array/object)
     * @param int|null $userId User ID (optional, for user-specific settings)
     * @param int|null $chatId Chat ID (optional, for chat-specific settings)
     * @return bool Success status
     */
    public function setSetting(string $key, mixed $value, ?int $userId = null, ?int $chatId = null): bool
    {
        $isJson = is_array($value) || is_object($value);
        
        $data = [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'setting_key' => $key,
            'setting_value' => $isJson ? json_encode($value) : (string)$value,
            'setting_type' => $isJson ? 'json' : 'string'
        ];

        return $this->upsert('settings', $data, ['user_id', 'chat_id', 'setting_key']) !== false;
    }

    /**
     * Get a setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @param int|null $userId User ID (optional)
     * @param int|null $chatId Chat ID (optional)
     * @return mixed Setting value or default
     */
    public function getSetting(string $key, mixed $default = null, ?int $userId = null, ?int $chatId = null): mixed
    {
        $where = 'setting_key = ?';
        $params = [$key];

        if ($userId !== null) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
        } else {
            $where .= ' AND user_id IS NULL';
        }

        if ($chatId !== null) {
            $where .= ' AND chat_id = ?';
            $params[] = $chatId;
        } else {
            $where .= ' AND chat_id IS NULL';
        }

        $result = $this->selectOne('settings', ['setting_value', 'setting_type'], $where, $params);

        if ($result === null) {
            return $default;
        }

        $value = $result['setting_value'];
        
        if ($result['setting_type'] === 'json') {
            $decoded = json_decode($value, true);
            return ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }

        return $value;
    }

    /**
     * Delete a setting
     * 
     * @param string $key Setting key
     * @param int|null $userId User ID (optional)
     * @param int|null $chatId Chat ID (optional)
     * @return int Number of deleted rows
     */
    public function deleteSetting(string $key, ?int $userId = null, ?int $chatId = null): int
    {
        $where = 'setting_key = ?';
        $params = [$key];

        if ($userId !== null) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
        }

        if ($chatId !== null) {
            $where .= ' AND chat_id = ?';
            $params[] = $chatId;
        }

        return $this->delete('settings', $where, $params);
    }

    // ==================== Bot Settings Methods ====================

    /**
     * Register or update a bot
     * 
     * @param string $botToken Bot token
     * @param string $botUsername Bot username
     * @param string $botName Bot display name
     * @param array $settings Additional bot settings
     * @return int Bot database ID
     */
    public function saveBot(string $botToken, string $botUsername, string $botName, array $settings = []): int
    {
        $data = [
            'bot_token_hash' => hash('sha256', $botToken),
            'bot_username' => $botUsername,
            'bot_name' => $botName,
            'is_active' => 1,
            'settings_json' => json_encode($settings)
        ];

        return $this->upsert('bot_settings', $data, 'bot_token_hash') ?: 0;
    }

    /**
     * Get bot by token hash
     * 
     * @param string $botToken Bot token
     * @return array|null Bot data or null if not found
     */
    public function getBotByToken(string $botToken): ?array
    {
        $tokenHash = hash('sha256', $botToken);
        return $this->selectOne('bot_settings', [], 'bot_token_hash = ?', [$tokenHash]);
    }

    /**
     * Get bot by username
     * 
     * @param string $botUsername Bot username
     * @return array|null Bot data or null if not found
     */
    public function getBotByUsername(string $botUsername): ?array
    {
        return $this->selectOne('bot_settings', [], 'bot_username = ?', [$botUsername]);
    }

    /**
     * Get all registered bots
     * 
     * @param bool $activeOnly Only return active bots
     * @return array Array of bots
     */
    public function getAllBots(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'is_active = 1' : null;
        return $this->select('bot_settings', [], $where);
    }

    /**
     * Activate or deactivate a bot
     * 
     * @param string $botUsername Bot username
     * @param bool $isActive Active status
     * @return bool Success status
     */
    public function setBotActive(string $botUsername, bool $isActive): bool
    {
        return $this->update('bot_settings', ['is_active' => $isActive ? 1 : 0], 'bot_username = ?', [$botUsername]) > 0;
    }

    /**
     * Update bot webhook URL
     * 
     * @param string $botUsername Bot username
     * @param string|null $webhookUrl Webhook URL (null to remove)
     * @return bool Success status
     */
    public function setBotWebhook(string $botUsername, ?string $webhookUrl): bool
    {
        return $this->update('bot_settings', ['webhook_url' => $webhookUrl], 'bot_username = ?', [$botUsername]) > 0;
    }

    /**
     * Update bot last processed update ID
     * 
     * @param string $botUsername Bot username
     * @param int $updateId Last update ID
     * @return bool Success status
     */
    public function setBotLastUpdateId(string $botUsername, int $updateId): bool
    {
        return $this->update('bot_settings', ['last_update_id' => $updateId], 'bot_username = ?', [$botUsername]) > 0;
    }

    // ==================== Conversation Methods ====================

    /**
     * Save conversation state
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @param string $state Conversation state name
     * @param array|null $data Additional conversation data
     * @param int|null $expiresAt Expiration timestamp (null for no expiration)
     * @return bool Success status
     */
    public function saveConversation(
        int $userId,
        int $chatId,
        string $state,
        ?array $data = null,
        ?int $expiresAt = null
    ): bool {
        $conversationData = [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'state' => $state,
            'data' => $data !== null ? json_encode($data) : null,
            'expires_at' => $expiresAt !== null ? date('Y-m-d H:i:s', $expiresAt) : null
        ];

        // Delete existing conversation for this user/chat
        $this->delete('conversations', 'user_id = ? AND chat_id = ?', [$userId, $chatId]);

        return $this->insert('conversations', $conversationData) !== false;
    }

    /**
     * Get conversation state
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @return array|null Conversation data or null if not found/expired
     */
    public function getConversation(int $userId, int $chatId): ?array
    {
        $conversation = $this->selectOne(
            'conversations',
            [],
            'user_id = ? AND chat_id = ? AND (expires_at IS NULL OR expires_at > NOW())',
            [$userId, $chatId]
        );

        if ($conversation === null) {
            return null;
        }

        if ($conversation['data'] !== null) {
            $conversation['data'] = json_decode($conversation['data'], true);
        }

        return $conversation;
    }

    /**
     * Delete conversation
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @return int Number of deleted rows
     */
    public function deleteConversation(int $userId, int $chatId): int
    {
        return $this->delete('conversations', 'user_id = ? AND chat_id = ?', [$userId, $chatId]);
    }

    /**
     * Clean expired conversations
     * 
     * @return int Number of deleted rows
     */
    public function cleanExpiredConversations(): int
    {
        return $this->delete('conversations', 'expires_at IS NOT NULL AND expires_at <= NOW()');
    }

    // ==================== Log Methods ====================

    /**
     * Add a log entry
     * 
     * @param string $message Log message
     * @param string $level Log level (INFO, WARNING, ERROR, DEBUG)
     * @param string|null $category Log category
     * @param array|null $context Additional context data
     * @param int|null $userId User ID associated with the log
     * @param int|null $chatId Chat ID associated with the log
     * @return int|false Log entry ID or false on failure
     */
    public function log(
        string $message,
        string $level = 'INFO',
        ?string $category = null,
        ?array $context = null,
        ?int $userId = null,
        ?int $chatId = null
    ): int|false {
        $data = [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context !== null ? json_encode($context) : null,
            'user_id' => $userId,
            'chat_id' => $chatId
        ];

        return $this->insert('logs', $data);
    }

    /**
     * Get logs with filtering
     * 
     * @param string|null $level Filter by log level
     * @param string|null $category Filter by category
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public function getLogs(?string $level = null, ?string $category = null, int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if ($level !== null) {
            $where[] = 'level = ?';
            $params[] = $level;
        }

        if ($category !== null) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $whereClause = !empty($where) ? implode(' AND ', $where) : null;

        return $this->select('logs', [], $whereClause, $params, 'created_at DESC', $limit, $offset);
    }

    /**
     * Clear old logs
     * 
     * @param int $daysOld Delete logs older than this many days
     * @return int Number of deleted rows
     */
    public function clearOldLogs(int $daysOld = 30): int
    {
        return $this->delete('logs', 'created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$daysOld]);
    }

    // ==================== Cache Methods ====================

    /**
     * Set a cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int|null $ttl Time to live in seconds (null for no expiration)
     * @return bool Success status
     */
    public function setCache(string $key, mixed $value, ?int $ttl = null): bool
    {
        $data = [
            'cache_key' => $key,
            'cache_value' => is_scalar($value) ? (string)$value : json_encode($value),
            'expires_at' => $ttl !== null ? date('Y-m-d H:i:s', time() + $ttl) : null
        ];

        return $this->upsert('cache', $data, 'cache_key') !== false;
    }

    /**
     * Get a cache value
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found or expired
     * @return mixed Cache value or default
     */
    public function getCache(string $key, mixed $default = null): mixed
    {
        $result = $this->selectOne('cache', ['cache_value', 'expires_at'], 'cache_key = ?', [$key]);

        if ($result === null || ($result['expires_at'] !== null && strtotime($result['expires_at']) < time())) {
            return $default;
        }

        $value = $result['cache_value'];
        $decoded = json_decode($value, true);

        return ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }

    /**
     * Delete a cache entry
     * 
     * @param string $key Cache key
     * @return int Number of deleted rows
     */
    public function deleteCache(string $key): int
    {
        return $this->delete('cache', 'cache_key = ?', [$key]);
    }

    /**
     * Clean expired cache entries
     * 
     * @return int Number of deleted rows
     */
    public function cleanExpiredCache(): int
    {
        return $this->delete('cache', 'expires_at IS NOT NULL AND expires_at <= NOW()');
    }

    // ==================== Update Methods ====================

    /**
     * Store a Telegram update
     * 
     * @param int $updateId Telegram update ID
     * @param array $updateData Raw update data
     * @return int|false Database ID or false on failure
     */
    public function storeUpdate(int $updateId, array $updateData): int|false
    {
        $data = [
            'update_id' => $updateId,
            'update_data' => json_encode($updateData),
            'processed' => 0
        ];

        return $this->insert('updates', $data);
    }

    /**
     * Mark an update as processed
     * 
     * @param int $updateId Telegram update ID
     * @return bool Success status
     */
    public function markUpdateProcessed(int $updateId): bool
    {
        return $this->update('updates', ['processed' => 1, 'processed_at' => date('Y-m-d H:i:s')], 'update_id = ?', [$updateId]) > 0;
    }

    /**
     * Get unprocessed updates
     * 
     * @param int $limit Maximum number of updates to return
     * @return array Array of unprocessed updates
     */
    public function getUnprocessedUpdates(int $limit = 100): array
    {
        return $this->select('updates', [], 'processed = 0', [], 'update_id ASC', $limit);
    }

    // ==================== Broadcast Methods ====================

    /**
     * Create a new broadcast
     * 
     * @param string $messageText Message text to broadcast
     * @param int $totalRecipients Total number of recipients
     * @return int|false Broadcast ID or false on failure
     */
    public function createBroadcast(string $messageText, int $totalRecipients): int|false
    {
        $data = [
            'message_text' => $messageText,
            'total_recipients' => $totalRecipients,
            'status' => 'pending'
        ];

        return $this->insert('broadcasts', $data);
    }

    /**
     * Update broadcast progress
     * 
     * @param int $broadcastId Broadcast ID
     * @param int $successfulSends Number of successful sends
     * @param int $failedSends Number of failed sends
     * @param string|null $status Broadcast status
     * @return bool Success status
     */
    public function updateBroadcastProgress(int $broadcastId, int $successfulSends, int $failedSends, ?string $status = null): bool
    {
        $data = [
            'successful_sends' => $successfulSends,
            'failed_sends' => $failedSends
        ];

        if ($status !== null) {
            $data['status'] = $status;
        }

        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'processing') {
            $data['started_at'] = date('Y-m-d H:i:s');
        }

        return $this->update('broadcasts', $data, 'id = ?', [$broadcastId]) > 0;
    }

    /**
     * Get all broadcasts
     * 
     * @param int $limit Maximum number of broadcasts to return
     * @return array Array of broadcasts
     */
    public function getBroadcasts(int $limit = 50): array
    {
        return $this->select('broadcasts', [], null, [], 'created_at DESC', $limit);
    }

    // ==================== Cron Job Methods ====================

    /**
     * Register a cron job
     * 
     * @param string $jobName Job name/identifier
     * @param string $cronExpression Cron expression (e.g., "*/5 * * * *")
     * @return int|false Job ID or false on failure
     */
    public function registerCronJob(string $jobName, string $cronExpression): int|false
    {
        $data = [
            'job_name' => $jobName,
            'cron_expression' => $cronExpression,
            'is_active' => 1
        ];

        return $this->upsert('cron_jobs', $data, 'job_name') ?: 0;
    }

    /**
     * Update cron job last run time
     * 
     * @param string $jobName Job name
     * @param string $nextRun Next scheduled run time
     * @return bool Success status
     */
    public function updateCronJobRun(string $jobName, string $nextRun): bool
    {
        $data = [
            'last_run' => date('Y-m-d H:i:s'),
            'next_run' => $nextRun
        ];

        return $this->update('cron_jobs', $data, 'job_name = ?', [$jobName]) > 0;
    }

    /**
     * Get active cron jobs due to run
     * 
     * @return array Array of due cron jobs
     */
    public function getDueCronJobs(): array
    {
        return $this->select('cron_jobs', [], 'is_active = 1 AND (next_run IS NULL OR next_run <= NOW())');
    }

    /**
     * Get all cron jobs
     * 
     * @return array Array of all cron jobs
     */
    public function getAllCronJobs(): array
    {
        return $this->select('cron_jobs');
    }

    // ==================== Utility Methods ====================

    /**
     * Begin a database transaction
     * 
     * @return bool Success status
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a database transaction
     * 
     * @return bool Success status
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a database transaction
     * 
     * @return bool Success status
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Run a raw SQL query
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|bool Results for SELECT queries, true/false for others
     */
    public function query(string $sql, array $params = []): array|bool
    {
        $stmt = $this->execute($sql, $params);

        if (!$stmt) {
            return false;
        }

        $sqlUpper = strtoupper(trim($sql));
        if (str_starts_with($sqlUpper, 'SELECT')) {
            return $stmt->fetchAll();
        }

        return true;
    }

    /**
     * Get database statistics
     * 
     * @return array Statistics including table counts and database info
     */
    public function stats(): array
    {
        $stats = [];

        $tables = [
            'users' => 'Users',
            'chats' => 'Chats',
            'user_chats' => 'User-Chat Relationships',
            'settings' => 'Settings',
            'bot_settings' => 'Bot Configurations',
            'conversations' => 'Active Conversations',
            'logs' => 'Log Entries',
            'updates' => 'Stored Updates',
            'broadcasts' => 'Broadcasts',
            'cron_jobs' => 'Cron Jobs',
            'cache' => 'Cache Entries'
        ];

        foreach ($tables as $table => $label) {
            $stats[$table] = $this->count($table);
        }

        // Get database size
        $dbName = $this->config['database'];
        $result = $this->query("
            SELECT SUM(data_length + index_length) as size 
            FROM information_schema.TABLES 
            WHERE table_schema = ?
        ", [$dbName]);

        $stats['database_size'] = isset($result[0]['size']) ? (int)$result[0]['size'] : 0;
        $stats['database_name'] = $dbName;
        $stats['host'] = $this->config['host'];

        return $stats;
    }

    /**
     * Export database to SQL file (backup)
     * 
     * Creates a complete SQL dump of the database that can be imported via phpMyAdmin.
     * 
     * @param string $backupFile Path to backup file
     * @param bool $includeData Include table data (true) or just structure (false)
     * @return bool Success status
     */
    public function backup(string $backupFile, bool $includeData = true): bool
    {
        try {
            $dbName = $this->config['database'];
            $tables = [
                'users', 'chats', 'user_chats', 'settings', 'bot_settings',
                'conversations', 'logs', 'updates', 'broadcasts', 'cron_jobs', 'cache'
            ];

            $sql = "-- Telegram Bot Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: {$dbName}\n\n";
            $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $sql .= "SET AUTOCOMMIT = 0;\n";
            $sql .= "START TRANSACTION;\n\n";

            foreach ($tables as $table) {
                $tableName = $this->tableName($table);
                
                // Get table structure
                $result = $this->query("SHOW CREATE TABLE {$tableName}");
                if ($result && isset($result[0]['Create Table'])) {
                    $sql .= "-- Table structure for {$table}\n";
                    $sql .= "DROP TABLE IF EXISTS {$tableName};\n";
                    $sql .= $result[0]['Create Table'] . ";\n\n";
                }

                if ($includeData) {
                    // Get table data
                    $rows = $this->select($table);
                    if (!empty($rows)) {
                        $sql .= "-- Data for {$table}\n";
                        foreach ($rows as $row) {
                            $columns = implode(', ', array_keys($row));
                            $values = array_map(function($val) {
                                if ($val === null) {
                                    return 'NULL';
                                }
                                return "'" . addslashes($val) . "'";
                            }, array_values($row));
                            $sql .= "INSERT INTO {$tableName} ({$columns}) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $sql .= "\n";
                    }
                }
            }

            $sql .= "COMMIT;\n";

            return file_put_contents($backupFile, $sql) !== false;
        } catch (Exception $e) {
            error_log("Backup failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore database from SQL file
     * 
     * Executes an SQL file (created by backup method or exported from phpMyAdmin).
     * 
     * @param string $backupFile Path to SQL backup file
     * @return bool Success status
     */
    public function restore(string $backupFile): bool
    {
        try {
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found: {$backupFile}");
            }

            $sql = file_get_contents($backupFile);
            
            // Split by semicolons and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--') && !str_starts_with($stmt, 'SET') && !str_starts_with($stmt, 'START') && !str_starts_with($stmt, 'COMMIT')
            );

            $this->pdo->beginTransaction();

            foreach ($statements as $statement) {
                if (trim($statement)) {
                    $this->pdo->exec($statement);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Restore failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Truncate all tables (clear all data)
     * 
     * WARNING: This will delete all data! Use with caution.
     * 
     * @return bool Success status
     */
    public function truncateAll(): bool
    {
        try {
            $tables = [
                'cache', 'logs', 'updates', 'broadcasts', 'cron_jobs',
                'conversations', 'user_chats', 'settings', 'bot_settings', 'chats', 'users'
            ];

            $this->pdo->beginTransaction();

            // Disable foreign key checks temporarily
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($tables as $table) {
                $this->pdo->exec("TRUNCATE TABLE {$this->tableName($table)}");
            }

            // Re-enable foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Truncate failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of all tables in the database
     * 
     * @return array Array of table names
     */
    public function getTables(): array
    {
        $result = $this->query("SHOW TABLES");
        $tables = [];

        if ($result) {
            foreach ($result as $row) {
                $tables[] = reset($row);
            }
        }

        return $tables;
    }

    /**
     * Optimize all tables
     * 
     * @return bool Success status
     */
    public function optimize(): bool
    {
        try {
            $tables = $this->getTables();

            foreach ($tables as $table) {
                $this->pdo->exec("OPTIMIZE TABLE {$table}");
            }

            return true;
        } catch (Exception $e) {
            error_log("Optimize failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close database connection
     * 
     * @return void
     */
    public function close(): void
    {
        $this->pdo = null;
    }

    /**
     * Destructor - automatically closes connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
