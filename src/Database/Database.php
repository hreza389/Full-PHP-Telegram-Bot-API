<?php

namespace TelegramBot\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Telegram Database Handler - MySQL Version
 * 
 * A comprehensive MySQL database wrapper for storing persistent data
 * like user information, settings, bot state, backups, and management.
 * 
 * @package TelegramBot\Database
 * @version 2.0.0 (MySQL)
 */
class Database
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
     * Constructor
     * 
     * @param array $config Database configuration
     * @throws RuntimeException If connection fails
     */
    public function __construct(array $config = [])
    {
        $this->config = [
            'host' => $config['host'] ?? 'localhost',
            'port' => $config['port'] ?? 3306,
            'database' => $config['database'] ?? 'telegram_bot',
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'prefix' => $config['prefix'] ?? '',
        ];

        $this->connect();
        $this->initializeTables();
    }

    /**
     * Connect to MySQL database
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
     * @return void
     */
    private function initializeTables(): void
    {
        // Users table
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

        // Chats table
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

        // Settings table
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

        // Bot settings table
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('bot_settings')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bot_token_hash VARCHAR(64) UNIQUE NOT NULL,
                bot_token_encrypted TEXT,
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

        // Admin users table
        $this->execute("
            CREATE TABLE IF NOT EXISTS {$this->tableName('admin_users')} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                is_super_admin TINYINT DEFAULT 0,
                permissions JSON,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Conversations table
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

        // Logs table
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

        // Updates table
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

        // Broadcasts table
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

        // Cron jobs table
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

        // Cache table
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
     * @param array|string|null $updateColumns Columns to update on duplicate key
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

        $sql = "INSERT INTO {$this->tableName($table)} ({$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateSet}";

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
     * @param string $where WHERE clause
     * @param array $params WHERE parameters
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));

        $sql = "UPDATE {$this->tableName($table)} SET {$set} WHERE {$where}";

        $stmt = $this->execute($sql, array_merge($data, $params));
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Delete records from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
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
     * @param array $columns Columns to select
     * @param string|null $where WHERE clause
     * @param array $params WHERE parameters
     * @param string|null $orderBy ORDER BY clause
     * @param int|null $limit LIMIT clause
     * @param int|null $offset OFFSET clause
     * @return array Results
     */
    public function select(
        string $table,
        array $columns = ['*'],
        ?string $where = null,
        array $params = [],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->tableName($table)}";

        if ($where) {
            $sql .= " WHERE {$where}";
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset) {
            $sql .= " OFFSET {$offset}";
        }

        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Select one record from a table
     * 
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $where WHERE clause
     * @param array $params WHERE parameters
     * @return array|null Single record or null
     */
    public function selectOne(
        string $table,
        array $columns = ['*'],
        string $where,
        array $params = []
    ): ?array {
        $result = $this->select($table, $columns, $where, $params, null, 1);
        return $result[0] ?? null;
    }

    /**
     * Count records in a table
     * 
     * @param string $table Table name
     * @param string|null $where WHERE clause
     * @param array $params WHERE parameters
     * @return int Count
     */
    public function count(string $table, ?string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->tableName($table)}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->execute($sql, $params);
        $result = $stmt ? $stmt->fetch() : null;
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get database statistics
     * 
     * @return array Statistics
     */
    public function stats(): array
    {
        $tables = [
            'users' => $this->count('users'),
            'chats' => $this->count('chats'),
            'bot_settings' => $this->count('bot_settings'),
            'admin_users' => $this->count('admin_users'),
            'logs' => $this->count('logs'),
            'conversations' => $this->count('conversations'),
            'cache' => $this->count('cache'),
            'broadcasts' => $this->count('broadcasts'),
            'cron_jobs' => $this->count('cron_jobs'),
        ];

        $totalSize = $this->getDatabaseSize();

        return [
            'tables' => $tables,
            'total_records' => array_sum($tables),
            'database_size' => $totalSize,
        ];
    }

    /**
     * Get database size
     * 
     * @return string Database size in human readable format
     */
    public function getDatabaseSize(): string
    {
        $sql = "SELECT SUM(data_length + index_length) as size 
                FROM information_schema.TABLES 
                WHERE table_schema = :database";
        
        $stmt = $this->execute($sql, ['database' => $this->config['database']]);
        $result = $stmt ? $stmt->fetch() : null;
        
        $bytes = (int)($result['size'] ?? 0);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Backup database to SQL file
     * 
     * @param string $filename Output filename
     * @return bool Success
     */
    public function backup(string $filename): bool
    {
        try {
            $tables = [];
            $result = $this->execute("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sql = "-- Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                // Add drop statement
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n\n";

                // Add create table statement
                $stmt = $this->execute("SHOW CREATE TABLE `{$table}`");
                $createTable = $stmt->fetch(PDO::FETCH_NUM);
                $sql .= $createTable[1] . ";\n\n";

                // Add data
                $stmt = $this->execute("SELECT * FROM `{$table}`");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $values = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        return "'" . addslashes($val) . "'";
                    }, $row);
                    $sql .= "INSERT INTO `{$table}` (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            return file_put_contents($filename, $sql) !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Restore database from SQL file
     * 
     * @param string $filename SQL file path
     * @return bool Success
     */
    public function restore(string $filename): bool
    {
        try {
            if (!file_exists($filename)) {
                return false;
            }

            $sql = file_get_contents($filename);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s) && !str_starts_with($s, '--') && !str_starts_with($s, 'SET')
            );

            foreach ($statements as $statement) {
                $this->execute($statement);
            }

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Optimize all tables
     * 
     * @return bool Success
     */
    public function optimize(): bool
    {
        try {
            $result = $this->execute("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $this->execute("OPTIMIZE TABLE `{$row[0]}`");
            }
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Truncate all tables
     * 
     * @return bool Success
     */
    public function truncateAll(): bool
    {
        try {
            $this->execute("SET FOREIGN_KEY_CHECKS=0");
            $result = $this->execute("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $this->execute("TRUNCATE TABLE `{$row[0]}`");
            }
            $this->execute("SET FOREIGN_KEY_CHECKS=1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ==================== USER MANAGEMENT ====================

    /**
     * Save or update user
     * 
     * @param array $userData User data
     * @return int|false User ID
     */
    public function saveUser(array $userData): int|false
    {
        return $this->upsert('users', [
            'telegram_id' => $userData['id'],
            'username' => $userData['username'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'language_code' => $userData['language_code'] ?? null,
            'is_bot' => $userData['is_bot'] ?? 0,
        ], ['username', 'first_name', 'last_name', 'language_code']);
    }

    /**
     * Get user by Telegram ID
     * 
     * @param int $telegramId Telegram user ID
     * @return array|null User data
     */
    public function getUser(int $telegramId): ?array
    {
        return $this->selectOne('users', ['*'], 'telegram_id = ?', [$telegramId]);
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
        return $this->select('users', ['*'], null, [], 'created_at DESC', $limit, $offset);
    }

    // ==================== CHAT MANAGEMENT ====================

    /**
     * Save or update chat
     * 
     * @param array $chatData Chat data
     * @return int|false Chat ID
     */
    public function saveChat(array $chatData): int|false
    {
        return $this->upsert('chats', [
            'telegram_id' => $chatData['id'],
            'type' => $chatData['type'] ?? 'private',
            'title' => $chatData['title'] ?? null,
            'username' => $chatData['username'] ?? null,
        ], ['type', 'title', 'username']);
    }

    /**
     * Get chat by Telegram ID
     * 
     * @param int $telegramId Telegram chat ID
     * @return array|null Chat data
     */
    public function getChat(int $telegramId): ?array
    {
        return $this->selectOne('chats', ['*'], 'telegram_id = ?', [$telegramId]);
    }

    // ==================== BOT MANAGEMENT ====================

    /**
     * Save or update bot
     * 
     * @param string $token Bot token
     * @param string $username Bot username
     * @param string|null $name Bot name
     * @return int|false Bot ID
     */
    public function saveBot(string $token, string $username, ?string $name = null): int|false
    {
        return $this->upsert('bot_settings', [
            'bot_token_hash' => hash('sha256', $token),
            'bot_token_encrypted' => $token, // In production, encrypt this!
            'bot_username' => $username,
            'bot_name' => $name ?? $username,
            'is_active' => 1,
        ], ['bot_name', 'is_active']);
    }

    /**
     * Get bot by username
     * 
     * @param string $username Bot username
     * @return array|null Bot data
     */
    public function getBot(string $username): ?array
    {
        return $this->selectOne('bot_settings', ['*'], 'bot_username = ?', [$username]);
    }

    /**
     * Get all bots
     * 
     * @param bool $activeOnly Only active bots
     * @return array Bots
     */
    public function getAllBots(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'is_active = 1' : null;
        return $this->select('bot_settings', ['id', 'bot_username', 'bot_name', 'is_active', 'created_at'], $where);
    }

    /**
     * Set bot active status
     * 
     * @param string $username Bot username
     * @param bool $isActive Active status
     * @return int Affected rows
     */
    public function setBotActive(string $username, bool $isActive): int
    {
        return $this->update('bot_settings', ['is_active' => $isActive ? 1 : 0], 'bot_username = ?', [$username]);
    }

    /**
     * Get bot token
     * 
     * @param string $username Bot username
     * @return string|null Bot token
     */
    public function getBotToken(string $username): ?string
    {
        $bot = $this->selectOne('bot_settings', ['bot_token_encrypted'], 'bot_username = ?', [$username]);
        return $bot['bot_token_encrypted'] ?? null;
    }

    // ==================== ADMIN USER MANAGEMENT ====================

    /**
     * Create admin user
     * 
     * @param string $username Username
     * @param string $password Password
     * @param string|null $email Email
     * @param bool $isSuperAdmin Is super admin
     * @return int|false Admin user ID
     */
    public function createAdminUser(string $username, string $password, ?string $email = null, bool $isSuperAdmin = false): int|false
    {
        return $this->insert('admin_users', [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'is_super_admin' => $isSuperAdmin ? 1 : 0,
        ]);
    }

    /**
     * Verify admin user credentials
     * 
     * @param string $username Username
     * @param string $password Password
     * @return array|null User data if valid
     */
    public function verifyAdminUser(string $username, string $password): ?array
    {
        $user = $this->selectOne('admin_users', ['*'], 'username = ?', [$username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $this->update('admin_users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            return $user;
        }
        
        return null;
    }

    /**
     * Get admin user by username
     * 
     * @param string $username Username
     * @return array|null User data
     */
    public function getAdminUser(string $username): ?array
    {
        return $this->selectOne('admin_users', ['*'], 'username = ?', [$username]);
    }

    /**
     * Get all admin users
     * 
     * @return array Admin users
     */
    public function getAllAdminUsers(): array
    {
        return $this->select('admin_users', ['id', 'username', 'email', 'is_super_admin', 'last_login', 'created_at']);
    }

    /**
     * Delete admin user
     * 
     * @param int $userId User ID
     * @return int Deleted rows
     */
    public function deleteAdminUser(int $userId): int
    {
        return $this->delete('admin_users', 'id = ?', [$userId]);
    }

    // ==================== SETTINGS MANAGEMENT ====================

    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param int|null $userId User ID
     * @param int|null $chatId Chat ID
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function getSetting(string $key, ?int $userId = null, ?int $chatId = null, mixed $default = null): mixed
    {
        $where = 'setting_key = ?';
        $params = [$key];

        if ($userId !== null) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
        } elseif ($chatId !== null) {
            $where .= ' AND chat_id = ?';
            $params[] = $chatId;
        }

        $result = $this->selectOne('settings', ['setting_value', 'setting_type'], $where, $params);
        
        if (!$result) {
            return $default;
        }

        return $this->decodeSettingValue($result['setting_value'], $result['setting_type']);
    }

    /**
     * Set setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param int|null $userId User ID
     * @param int|null $chatId Chat ID
     * @return int|false Setting ID
     */
    public function setSetting(string $key, mixed $value, ?int $userId = null, ?int $chatId = null): int|false
    {
        list($encodedValue, $type) = $this->encodeSettingValue($value);

        return $this->upsert('settings', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'setting_key' => $key,
            'setting_value' => $encodedValue,
            'setting_type' => $type,
        ], ['setting_value', 'setting_type']);
    }

    /**
     * Encode setting value
     * 
     * @param mixed $value Value to encode
     * @return array [encoded_value, type]
     */
    private function encodeSettingValue(mixed $value): array
    {
        if (is_bool($value)) {
            return [$value ? '1' : '0', 'boolean'];
        } elseif (is_int($value)) {
            return [(string)$value, 'integer'];
        } elseif (is_float($value)) {
            return [(string)$value, 'float'];
        } elseif (is_array($value) || is_object($value)) {
            return [json_encode($value), 'json'];
        } else {
            return [(string)$value, 'string'];
        }
    }

    /**
     * Decode setting value
     * 
     * @param string $value Encoded value
     * @param string $type Value type
     * @return mixed Decoded value
     */
    private function decodeSettingValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $value === '1',
            'integer' => (int)$value,
            'float' => (float)$value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    // ==================== LOGS MANAGEMENT ====================

    /**
     * Add log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @param int|null $userId User ID
     * @param int|null $chatId Chat ID
     * @param string|null $category Category
     * @return int|false Log ID
     */
    public function addLog(
        string $level,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?int $chatId = null,
        ?string $category = null
    ): int|false {
        return $this->insert('logs', [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => json_encode($context),
            'user_id' => $userId,
            'chat_id' => $chatId,
        ]);
    }

    /**
     * Get logs
     * 
     * @param string|null $level Filter by level
     * @param string|null $category Filter by category
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Logs
     */
    public function getLogs(
        ?string $level = null,
        ?string $category = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];

        if ($level) {
            $where[] = 'level = ?';
            $params[] = $level;
        }

        if ($category) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $whereClause = !empty($where) ? implode(' AND ', $where) : null;

        return $this->select('logs', ['*'], $whereClause, $params, 'created_at DESC', $limit, $offset);
    }

    /**
     * Clear old logs
     * 
     * @param int $days Days to keep
     * @return int Deleted rows
     */
    public function clearOldLogs(int $days = 30): int
    {
        return $this->delete('logs', 'created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
    }

    // ==================== CACHE MANAGEMENT ====================

    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached value or default
     */
    public function getCache(string $key, mixed $default = null): mixed
    {
        $result = $this->selectOne('cache', ['cache_value', 'expires_at'], 'cache_key = ?', [$key]);
        
        if (!$result) {
            return $default;
        }

        if ($result['expires_at'] && strtotime($result['expires_at']) < time()) {
            $this->delete('cache', 'cache_key = ?', [$key]);
            return $default;
        }

        return unserialize($result['cache_value']);
    }

    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int|null $ttl Time to live in seconds
     * @return bool Success
     */
    public function setCache(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expiresAt = $ttl ? date('Y-m-d H:i:s', time() + $ttl) : null;
        $encoded = serialize($value);

        return $this->upsert('cache', [
            'cache_key' => $key,
            'cache_value' => $encoded,
            'expires_at' => $expiresAt,
        ]) !== false;
    }

    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return int Deleted rows
     */
    public function deleteCache(string $key): int
    {
        return $this->delete('cache', 'cache_key = ?', [$key]);
    }

    /**
     * Clean expired cache entries
     * 
     * @return int Deleted rows
     */
    public function cleanExpiredCache(): int
    {
        return $this->delete('cache', 'expires_at IS NOT NULL AND expires_at < NOW()');
    }

    // ==================== CONVERSATION MANAGEMENT ====================

    /**
     * Start conversation
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @param string $state Conversation state
     * @param array $data Conversation data
     * @param int|null $expiresIn Expiration in seconds
     * @return int|false Conversation ID
     */
    public function startConversation(
        int $userId,
        int $chatId,
        string $state,
        array $data = [],
        ?int $expiresIn = null
    ): int|false {
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

        return $this->insert('conversations', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'state' => $state,
            'data' => json_encode($data),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Get conversation
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @return array|null Conversation data
     */
    public function getConversation(int $userId, int $chatId): ?array
    {
        $conv = $this->selectOne('conversations', ['*'], 'user_id = ? AND chat_id = ?', [$userId, $chatId]);
        
        if ($conv) {
            if ($conv['expires_at'] && strtotime($conv['expires_at']) < time()) {
                $this->delete('conversations', 'id = ?', [$conv['id']]);
                return null;
            }
            $conv['data'] = json_decode($conv['data'], true);
        }
        
        return $conv;
    }

    /**
     * Update conversation state
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @param string $state New state
     * @param array|null $data New data
     * @return int Affected rows
     */
    public function updateConversation(
        int $userId,
        int $chatId,
        string $state,
        ?array $data = null
    ): int {
        $updateData = ['state' => $state];
        if ($data !== null) {
            $updateData['data'] = json_encode($data);
        }
        return $this->update('conversations', $updateData, 'user_id = ? AND chat_id = ?', [$userId, $chatId]);
    }

    /**
     * End conversation
     * 
     * @param int $userId User ID
     * @param int $chatId Chat ID
     * @return int Deleted rows
     */
    public function endConversation(int $userId, int $chatId): int
    {
        return $this->delete('conversations', 'user_id = ? AND chat_id = ?', [$userId, $chatId]);
    }

    /**
     * Clean expired conversations
     * 
     * @return int Deleted rows
     */
    public function cleanExpiredConversations(): int
    {
        return $this->delete('conversations', 'expires_at IS NOT NULL AND expires_at < NOW()');
    }

    // ==================== CRON JOB MANAGEMENT ====================

    /**
     * Register cron job
     * 
     * @param string $name Job name
     * @param string $expression Cron expression
     * @param bool $isActive Is active
     * @return int|false Job ID
     */
    public function registerCronJob(string $name, string $expression, bool $isActive = true): int|false
    {
        return $this->insert('cron_jobs', [
            'job_name' => $name,
            'cron_expression' => $expression,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    /**
     * Get all cron jobs
     * 
     * @return array Cron jobs
     */
    public function getAllCronJobs(): array
    {
        return $this->select('cron_jobs', ['*'], null, [], 'job_name ASC');
    }

    /**
     * Update cron job last run
     * 
     * @param string $name Job name
     * @param string $nextRun Next run time
     * @return int Affected rows
     */
    public function updateCronJobRun(string $name, string $nextRun): int
    {
        return $this->update('cron_jobs', [
            'last_run' => date('Y-m-d H:i:s'),
            'next_run' => $nextRun,
        ], 'job_name = ?', [$name]);
    }

    /**
     * Set cron job active status
     * 
     * @param string $name Job name
     * @param bool $isActive Active status
     * @return int Affected rows
     */
    public function setCronJobActive(string $name, bool $isActive): int
    {
        return $this->update('cron_jobs', ['is_active' => $isActive ? 1 : 0], 'job_name = ?', [$name]);
    }

    // ==================== TRANSACTION SUPPORT ====================

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
     * Execute within transaction
     * 
     * @param callable $callback Callback to execute
     * @return mixed Callback result
     * @throws PDOException On transaction failure
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
