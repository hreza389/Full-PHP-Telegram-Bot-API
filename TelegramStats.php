<?php

/**
 * Telegram Statistics & Analytics System
 * 
 * Tracks user growth, message volume, command statistics,
 * error rates, and provides comprehensive analytics.
 */
class TelegramStats {
    private TelegramBot $bot;
    private TelegramDB $db;
    private array $tables = [
        'stats_users',
        'stats_messages',
        'stats_commands',
        'stats_errors',
        'stats_daily'
    ];

    public function __construct(TelegramBot $bot, TelegramDB $db) {
        $this->bot = $bot;
        $this->db = $db;
        $this->initTables();
    }

    /**
     * Initialize all statistics tables
     */
    private function initTables(): void {
        // Users tracking
        $this->db->query("
            CREATE TABLE IF NOT EXISTS stats_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                username TEXT,
                first_name TEXT,
                last_name TEXT,
                language_code TEXT,
                is_bot INTEGER DEFAULT 0,
                first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                message_count INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1
            )
        ");

        // Messages tracking
        $this->db->query("
            CREATE TABLE IF NOT EXISTS stats_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER,
                chat_id INTEGER NOT NULL,
                user_id INTEGER,
                message_type TEXT NOT NULL,
                text_length INTEGER DEFAULT 0,
                has_entities INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Commands tracking
        $this->db->query("
            CREATE TABLE IF NOT EXISTS stats_commands (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                command TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                chat_type TEXT,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                execution_time_ms INTEGER DEFAULT 0,
                success INTEGER DEFAULT 1,
                error_message TEXT
            )
        ");

        // Errors tracking
        $this->db->query("
            CREATE TABLE IF NOT EXISTS stats_errors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                error_type TEXT NOT NULL,
                error_message TEXT NOT NULL,
                file TEXT,
                line INTEGER,
                user_id INTEGER,
                chat_id INTEGER,
                context TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Daily aggregates
        $this->db->query("
            CREATE TABLE IF NOT EXISTS stats_daily (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE UNIQUE NOT NULL,
                new_users INTEGER DEFAULT 0,
                total_users INTEGER DEFAULT 0,
                active_users INTEGER DEFAULT 0,
                messages_sent INTEGER DEFAULT 0,
                messages_received INTEGER DEFAULT 0,
                commands_executed INTEGER DEFAULT 0,
                errors_count INTEGER DEFAULT 0,
                broadcast_sent INTEGER DEFAULT 0,
                broadcast_success INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes for performance
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_users_last_seen ON stats_users(last_seen)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_messages_created ON stats_messages(created_at)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_commands_executed ON stats_commands(executed_at)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_errors_created ON stats_errors(created_at)");
    }

    /**
     * Track a new or returning user
     */
    public function trackUser(array $user): int {
        $userId = $user['id'];
        $now = date('Y-m-d H:i:s');

        // Check if user exists
        $existing = $this->db->query(
            "SELECT id FROM stats_users WHERE user_id = ?",
            [$userId],
            true
        );

        if (empty($existing)) {
            // New user
            $this->db->query(
                "INSERT INTO stats_users 
                (user_id, username, first_name, last_name, language_code, is_bot, first_seen, last_seen) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $user['username'] ?? null,
                    $user['first_name'] ?? null,
                    $user['last_name'] ?? null,
                    $user['language_code'] ?? null,
                    $user['is_bot'] ? 1 : 0,
                    $now,
                    $now
                ]
            );

            // Update daily stats
            $this->incrementDailyStat('new_users');
            $this->recalculateDailyTotalUsers();

            return $this->db->lastInsertRowId();
        } else {
            // Returning user
            $this->db->query(
                "UPDATE stats_users 
                SET last_seen = ?, 
                    username = COALESCE(?, username),
                    first_name = COALESCE(?, first_name),
                    last_name = COALESCE(?, last_name),
                    language_code = COALESCE(?, language_code),
                    is_active = 1
                WHERE user_id = ?",
                [
                    $now,
                    $user['username'] ?? null,
                    $user['first_name'] ?? null,
                    $user['last_name'] ?? null,
                    $user['language_code'] ?? null,
                    $userId
                ]
            );

            return $existing[0]['id'];
        }
    }

    /**
     * Track a received message
     */
    public function trackMessageReceived(array $message, array $from): void {
        $messageType = $this->detectMessageType($message);
        $textLength = isset($message['text']) ? strlen($message['text']) : 0;
        $hasEntities = isset($message['entities']) && !empty($message['entities']) ? 1 : 0;

        $this->db->query(
            "INSERT INTO stats_messages 
            (message_id, chat_id, user_id, message_type, text_length, has_entities) 
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $message['message_id'],
                $message['chat']['id'],
                $from['id'],
                $messageType,
                $textLength,
                $hasEntities
            ]
        );

        // Increment user message count
        $this->db->query(
            "UPDATE stats_users SET message_count = message_count + 1 WHERE user_id = ?",
            [$from['id']]
        );

        // Update daily stats
        $this->incrementDailyStat('messages_received');
    }

    /**
     * Track a sent message (broadcast, response, etc.)
     */
    public function trackMessageSent(int $chatId, string $messageType): void {
        $this->incrementDailyStat('messages_sent');
    }

    /**
     * Track command execution
     */
    public function trackCommand(
        string $command,
        int $userId,
        int $chatId,
        string $chatType,
        float $executionTimeMs,
        bool $success,
        ?string $error = null
    ): void {
        $this->db->query(
            "INSERT INTO stats_commands 
            (command, user_id, chat_id, chat_type, execution_time_ms, success, error_message) 
            VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $command,
                $userId,
                $chatId,
                $chatType,
                round($executionTimeMs),
                $success ? 1 : 0,
                $error
            ]
        );

        // Update daily stats
        $this->incrementDailyStat('commands_executed');

        // Track error if failed
        if (!$success && $error) {
            $this->trackError('command_error', $error, null, null, $userId, $chatId, json_encode(['command' => $command]));
        }
    }

    /**
     * Track an error
     */
    public function trackError(
        string $type,
        string $message,
        ?string $file = null,
        ?int $line = null,
        ?int $userId = null,
        ?int $chatId = null,
        ?string $context = null
    ): void {
        $this->db->query(
            "INSERT INTO stats_errors 
            (error_type, error_message, file, line, user_id, chat_id, context) 
            VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$type, $message, $file, $line, $userId, $chatId, $context]
        );

        // Update daily stats
        $this->incrementDailyStat('errors_count');
    }

    /**
     * Track broadcast job
     */
    public function trackBroadcast(int $sent, int $success, int $failed): void {
        $this->db->query(
            "UPDATE stats_daily 
            SET broadcast_sent = broadcast_sent + ?, 
                broadcast_success = broadcast_success + ?,
                updated_at = CURRENT_TIMESTAMP 
            WHERE date = DATE('now')",
            [$sent, $success]
        );

        // If no row exists, insert one
        if ($this->db->affectedRows() === 0) {
            $this->db->query(
                "INSERT INTO stats_daily 
                (date, broadcast_sent, broadcast_success) 
                VALUES (DATE('now'), ?, ?)",
                [$sent, $success]
            );
        }
    }

    /**
     * Detect message type
     */
    private function detectMessageType(array $message): string {
        if (isset($message['text'])) {
            return 'text';
        } elseif (isset($message['photo'])) {
            return 'photo';
        } elseif (isset($message['video'])) {
            return 'video';
        } elseif (isset($message['audio'])) {
            return 'audio';
        } elseif (isset($message['voice'])) {
            return 'voice';
        } elseif (isset($message['document'])) {
            return 'document';
        } elseif (isset($message['sticker'])) {
            return 'sticker';
        } elseif (isset($message['animation'])) {
            return 'animation';
        } elseif (isset($message['video_note'])) {
            return 'video_note';
        } elseif (isset($message['contact'])) {
            return 'contact';
        } elseif (isset($message['location'])) {
            return 'location';
        } elseif (isset($message['venue'])) {
            return 'venue';
        } elseif (isset($message['poll'])) {
            return 'poll';
        } elseif (isset($message['dice'])) {
            return 'dice';
        } elseif (isset($message['invoice'])) {
            return 'invoice';
        } elseif (isset($message['successful_payment'])) {
            return 'payment';
        }

        return 'unknown';
    }

    /**
     * Increment a daily stat
     */
    private function incrementDailyStat(string $column, int $amount = 1): void {
        $this->db->query(
            "INSERT INTO stats_daily (date, {$column}) 
            VALUES (DATE('now'), ?)
            ON CONFLICT(date) DO UPDATE SET 
                {$column} = {$column} + ?, 
                updated_at = CURRENT_TIMESTAMP",
            [$amount, $amount]
        );
    }

    /**
     * Recalculate total users for today
     */
    private function recalculateDailyTotalUsers(): void {
        $total = $this->db->query(
            "SELECT COUNT(*) as count FROM stats_users WHERE is_active = 1",
            [],
            true
        )[0]['count'] ?? 0;

        $this->db->query(
            "INSERT INTO stats_daily (date, total_users) 
            VALUES (DATE('now'), ?)
            ON CONFLICT(date) DO UPDATE SET 
                total_users = ?, 
                updated_at = CURRENT_TIMESTAMP",
            [$total, $total]
        );
    }

    /**
     * Get overall statistics
     */
    public function getOverallStats(): array {
        $totalUsers = $this->db->query(
            "SELECT COUNT(*) as count FROM stats_users WHERE is_active = 1",
            [],
            true
        )[0]['count'] ?? 0;

        $activeUsers24h = $this->db->query(
            "SELECT COUNT(DISTINCT user_id) as count FROM stats_users 
            WHERE last_seen >= datetime('now', '-24 hours') AND is_active = 1",
            [],
            true
        )[0]['count'] ?? 0;

        $activeUsers7d = $this->db->query(
            "SELECT COUNT(DISTINCT user_id) as count FROM stats_users 
            WHERE last_seen >= datetime('now', '-7 days') AND is_active = 1",
            [],
            true
        )[0]['count'] ?? 0;

        $totalMessages = $this->db->query(
            "SELECT COUNT(*) as count FROM stats_messages",
            [],
            true
        )[0]['count'] ?? 0;

        $totalCommands = $this->db->query(
            "SELECT COUNT(*) as count FROM stats_commands",
            [],
            true
        )[0]['count'] ?? 0;

        $totalErrors = $this->db->query(
            "SELECT COUNT(*) as count FROM stats_errors",
            [],
            true
        )[0]['count'] ?? 0;

        $avgCommandTime = $this->db->query(
            "SELECT AVG(execution_time_ms) as avg FROM stats_commands WHERE success = 1",
            [],
            true
        )[0]['avg'] ?? 0;

        return [
            'total_users' => $totalUsers,
            'active_users_24h' => $activeUsers24h,
            'active_users_7d' => $activeUsers7d,
            'total_messages' => $totalMessages,
            'total_commands' => $totalCommands,
            'total_errors' => $totalErrors,
            'avg_command_time_ms' => round($avgCommandTime, 2),
            'success_rate' => $totalCommands > 0 
                ? round(($totalCommands - $totalErrors) / $totalCommands * 100, 2) 
                : 100
        ];
    }

    /**
     * Get daily statistics for a date range
     */
    public function getDailyStats(string $startDate, string $endDate): array {
        return $this->db->query(
            "SELECT * FROM stats_daily 
            WHERE date BETWEEN ? AND ? 
            ORDER BY date DESC",
            [$startDate, $endDate],
            true
        );
    }

    /**
     * Get top commands
     */
    public function getTopCommands(int $limit = 10): array {
        return $this->db->query(
            "SELECT command, 
                    COUNT(*) as executions,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successes,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failures,
                    AVG(execution_time_ms) as avg_time
             FROM stats_commands 
             GROUP BY command 
             ORDER BY executions DESC 
             LIMIT ?",
            [$limit],
            true
        );
    }

    /**
     * Get recent errors
     */
    public function getRecentErrors(int $limit = 50): array {
        return $this->db->query(
            "SELECT * FROM stats_errors 
            ORDER BY created_at DESC 
            LIMIT ?",
            [$limit],
            true
        );
    }

    /**
     * Get error summary by type
     */
    public function getErrorSummary(): array {
        return $this->db->query(
            "SELECT error_type, 
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence
             FROM stats_errors 
             GROUP BY error_type 
             ORDER BY count DESC",
            [],
            true
        );
    }

    /**
     * Get user growth over time
     */
    public function getUserGrowth(int $days = 30): array {
        return $this->db->query(
            "SELECT date, new_users, total_users 
             FROM stats_daily 
             WHERE date >= DATE('now', '-' || ? || ' days')
             ORDER BY date ASC",
            [$days],
            true
        );
    }

    /**
     * Get message types distribution
     */
    public function getMessageTypesDistribution(): array {
        return $this->db->query(
            "SELECT message_type, COUNT(*) as count 
             FROM stats_messages 
             GROUP BY message_type 
             ORDER BY count DESC",
            [],
            true
        );
    }

    /**
     * Get top active users
     */
    public function getTopActiveUsers(int $limit = 20): array {
        return $this->db->query(
            "SELECT user_id, username, first_name, last_name, 
                    message_count, last_seen 
             FROM stats_users 
             WHERE is_active = 1 
             ORDER BY message_count DESC 
             LIMIT ?",
            [$limit],
            true
        );
    }

    /**
     * Get hourly activity for today
     */
    public function getHourlyActivity(): array {
        return $this->db->query(
            "SELECT strftime('%H', created_at) as hour, COUNT(*) as count 
             FROM stats_messages 
             WHERE DATE(created_at) = DATE('now')
             GROUP BY hour 
             ORDER BY hour",
            [],
            true
        );
    }

    /**
     * Get chat type distribution for commands
     */
    public function getChatTypeDistribution(): array {
        return $this->db->query(
            "SELECT chat_type, COUNT(*) as count 
             FROM stats_commands 
             GROUP BY chat_type 
             ORDER BY count DESC",
            [],
            true
        );
    }

    /**
     * Clean up old statistics data
     */
    public function cleanupOldData(int $days = 90): int {
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Clean errors
        $this->db->query(
            "DELETE FROM stats_errors WHERE created_at < ?",
            [$cutoff . ' 00:00:00']
        );

        // Clean messages
        $this->db->query(
            "DELETE FROM stats_messages WHERE created_at < ?",
            [$cutoff . ' 00:00:00']
        );

        // Clean commands
        $this->db->query(
            "DELETE FROM stats_commands WHERE executed_at < ?",
            [$cutoff . ' 00:00:00']
        );

        // Clean daily stats older than specified days
        $this->db->query(
            "DELETE FROM stats_daily WHERE date < ?",
            [$cutoff]
        );

        return $this->db->affectedRows();
    }

    /**
     * Export statistics to JSON
     */
    public function exportToJson(): string {
        $data = [
            'overall' => $this->getOverallStats(),
            'daily' => $this->getDailyStats(date('Y-m-d', strtotime('-30 days')), date('Y-m-d')),
            'top_commands' => $this->getTopCommands(20),
            'error_summary' => $this->getErrorSummary(),
            'user_growth' => $this->getUserGrowth(30),
            'message_types' => $this->getMessageTypesDistribution(),
            'hourly_activity' => $this->getHourlyActivity(),
            'exported_at' => date('Y-m-d H:i:s')
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
