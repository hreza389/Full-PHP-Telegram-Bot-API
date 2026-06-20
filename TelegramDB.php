<?php

/**
 * Telegram Database Handler
 * 
 * A lightweight SQLite database wrapper for storing persistent data
 * like user information, settings, and bot state.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 */
class TelegramDB
{
    /**
     * @var PDO|null Database connection
     */
    private ?PDO $pdo = null;

    /**
     * @var string Database file path
     */
    private string $dbFile;

    /**
     * @var bool Enable query logging
     */
    private bool $debug = false;

    /**
     * Constructor
     * 
     * @param string $dbFile Path to SQLite database file
     * @throws RuntimeException If database cannot be opened
     */
    public function __construct(string $dbFile = '/tmp/telegram_bot.db')
    {
        $this->dbFile = $dbFile;

        // Ensure directory exists
        $dir = dirname($dbFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create database directory: {$dir}");
            }
        }

        $this->connect();
        $this->initializeTables();
    }

    /**
     * Connect to database
     * 
     * @return void
     * @throws RuntimeException If connection fails
     */
    private function connect(): void
    {
        try {
            $dsn = "sqlite:{$this->dbFile}";
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
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
     * Initialize default tables
     * 
     * @return void
     */
    private function initializeTables(): void
    {
        // Users table
        $this->execute("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY,
                telegram_id INTEGER UNIQUE NOT NULL,
                username TEXT,
                first_name TEXT,
                last_name TEXT,
                language_code TEXT,
                is_bot INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Chats table
        $this->execute("
            CREATE TABLE IF NOT EXISTS chats (
                id INTEGER PRIMARY KEY,
                telegram_id INTEGER UNIQUE NOT NULL,
                type TEXT,
                title TEXT,
                username TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // User-chat relationships
        $this->execute("
            CREATE TABLE IF NOT EXISTS user_chats (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                role TEXT DEFAULT 'member',
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(telegram_id),
                FOREIGN KEY (chat_id) REFERENCES chats(telegram_id),
                UNIQUE(user_id, chat_id)
            )
        ");

        // Settings table
        $this->execute("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY,
                user_id INTEGER,
                chat_id INTEGER,
                key TEXT NOT NULL,
                value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, chat_id, key)
            )
        ");

        // Conversations table
        $this->execute("
            CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                state TEXT NOT NULL,
                data TEXT,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(telegram_id)
            )
        ");

        // Logs table
        $this->execute("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY,
                level TEXT,
                message TEXT,
                context TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes
        $this->execute("CREATE INDEX IF NOT EXISTS idx_users_telegram_id ON users(telegram_id)");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_chats_telegram_id ON chats(telegram_id)");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key)");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_conversations_user_id ON conversations(user_id)");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_conversations_state ON conversations(state)");
    }

    /**
     * Execute a query
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
     * Insert a record
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int|false Last insert ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        if ($this->execute($sql, array_values($data))) {
            return (int)$this->pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Insert or update a record
     * 
     * @param string $table Table name
     * @param array $data Data to insert/update
     * @param array $updateColumns Columns to update on conflict (null for all except primary key)
     * @return int|false Last insert ID or false on failure
     */
    public function upsert(string $table, array $data, ?array $updateColumns = null): int|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        if ($updateColumns === null) {
            $updateColumns = array_diff(array_keys($data), ['id']);
        }

        $updateSet = implode(', ', array_map(fn($col) => "{$col} = excluded.{$col}", $updateColumns));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) 
                ON CONFLICT DO UPDATE SET {$updateSet}, updated_at = CURRENT_TIMESTAMP";

        if ($this->execute($sql, array_values($data))) {
            return (int)$this->pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Update records
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param string $where WHERE clause
     * @param array $whereParams WHERE parameters
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE {$table} SET {$setClause}, updated_at = CURRENT_TIMESTAMP WHERE {$where}";

        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->execute($sql, $params);

        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Delete records
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params WHERE parameters
     * @return int Number of deleted rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->execute($sql, $params);

        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Select records
     * 
     * @param string $table Table name
     * @param array $columns Columns to select (empty for all)
     * @param string|null $where WHERE clause
     * @param array $params WHERE parameters
     * @param string|null $orderBy ORDER BY clause
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

        $sql = "SELECT {$selectClause} FROM {$table}";

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
     * Select single record
     * 
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $where WHERE clause
     * @param array $params WHERE parameters
     * @return array|null Single record or null
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
     * Count records
     * 
     * @param string $table Table name
     * @param string|null $where WHERE clause
     * @param array $params WHERE parameters
     * @return int Count
     */
    public function count(string $table, ?string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$table}";

        if ($where !== null) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->execute($sql, $params);
        $result = $stmt ? $stmt->fetch() : null;

        return (int)($result['count'] ?? 0);
    }

    // ==================== User Methods ====================

    /**
     * Save or update user
     * 
     * @param array $userData User data from Telegram
     * @return int User ID
     */
    public function saveUser(array $userData): int
    {
        $data = [
            'telegram_id' => $userData['id'],
            'username' => $userData['username'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'language_code' => $userData['language_code'] ?? null,
            'is_bot' => $userData['is_bot'] ?? 0
        ];

        return $this->upsert('users', $data, ['telegram_id']) ?: 0;
    }

    /**
     * Get user by Telegram ID
     * 
     * @param int $telegramId Telegram user ID
     * @return array|null User data
     */
    public function getUser(int $telegramId): ?array
    {
        return $this->selectOne('users', [], 'telegram_id = ?', [$telegramId]);
    }

    /**
     * Get all users
     * 
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Users
     */
    public function getAllUsers(int $limit = 100, int $offset = 0): array
    {
        return $this->select('users', [], null, [], 'created_at DESC', $limit, $offset);
    }

    // ==================== Chat Methods ====================

    /**
     * Save or update chat
     * 
     * @param array $chatData Chat data from Telegram
     * @return int Chat ID
     */
    public function saveChat(array $chatData): int
    {
        $data = [
            'telegram_id' => $chatData['id'],
            'type' => $chatData['type'] ?? null,
            'title' => $chatData['title'] ?? null,
            'username' => $chatData['username'] ?? null
        ];

        return $this->upsert('chats', $data, ['telegram_id']) ?: 0;
    }

    /**
     * Get chat by Telegram ID
     * 
     * @param int $telegramId Telegram chat ID
     * @return array|null Chat data
     */
    public function getChat(int $telegramId): ?array
    {
        return $this->selectOne('chats', [], 'telegram_id = ?', [$telegramId]);
    }

    // ==================== Settings Methods ====================

    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param int|null $userId User ID (optional)
     * @param int|null $chatId Chat ID (optional)
     * @return bool Success
     */
    public function setSetting(string $key, mixed $value, ?int $userId = null, ?int $chatId = null): bool
    {
        $data = [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'key' => $key,
            'value' => is_scalar($value) ? (string)$value : json_encode($value)
        ];

        return $this->upsert('settings', $data, ['user_id', 'chat_id', 'key']) !== false;
    }

    /**
     * Get a setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @param int|null $userId User ID (optional)
     * @param int|null $chatId Chat ID (optional)
     * @return mixed Setting value or default
     */
    public function getSetting(string $key, mixed $default = null, ?int $userId = null, ?int $chatId = null): mixed
    {
        $where = 'key = ?';
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

        $result = $this->selectOne('settings', ['value'], $where, $params);

        if ($result === null) {
            return $default;
        }

        $value = $result['value'];
        $decoded = json_decode($value, true);

        return ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
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
        $where = 'key = ?';
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

    // ==================== Conversation Methods ====================

    /**
     * Save conversation state
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @param string $state Conversation state
     * @param array|null $data Additional data
     * @param int|null $expiresAt Expiration timestamp (null for no expiration)
     * @return bool Success
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
     * @return array|null Conversation data or null
     */
    public function getConversation(int $userId, int $chatId): ?array
    {
        $conversation = $this->selectOne(
            'conversations',
            [],
            'user_id = ? AND chat_id = ? AND (expires_at IS NULL OR expires_at > datetime("now"))',
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
        return $this->delete('conversations', 'expires_at IS NOT NULL AND expires_at <= datetime("now")');
    }

    // ==================== Utility Methods ====================

    /**
     * Begin transaction
     * 
     * @return bool Success
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     * 
     * @return bool Success
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     * 
     * @return bool Success
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Run raw query
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array|bool Results for SELECT, true/false for others
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
     * Get database stats
     * 
     * @return array Statistics
     */
    public function stats(): array
    {
        $stats = [];

        $tables = ['users', 'chats', 'user_chats', 'settings', 'conversations', 'logs'];

        foreach ($tables as $table) {
            $stats[$table] = $this->count($table);
        }

        $stats['file_size'] = file_exists($this->dbFile) ? filesize($this->dbFile) : 0;
        $stats['file_path'] = $this->dbFile;

        return $stats;
    }

    /**
     * Backup database
     * 
     * @param string $backupFile Backup file path
     * @return bool Success
     */
    public function backup(string $backupFile): bool
    {
        if (!file_exists($this->dbFile)) {
            return false;
        }

        return copy($this->dbFile, $backupFile);
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
}
