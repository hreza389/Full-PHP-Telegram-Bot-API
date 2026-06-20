<?php

/**
 * Telegram Deep Linking System
 * 
 * Handles t.me/yourbot?start=param links for referrals,
 * content routing, and parameterized bot entry points.
 */
class TelegramDeepLinking {
    private TelegramBot $bot;
    private TelegramDB $db;
    private string $tableName = 'deep_links';
    private array $routes = [];

    public function __construct(TelegramBot $bot, TelegramDB $db) {
        $this->bot = $bot;
        $this->db = $db;
        $this->initTable();
    }

    /**
     * Initialize the deep links table
     */
    private function initTable(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                param TEXT NOT NULL,
                type TEXT DEFAULT 'static',
                target_type TEXT NOT NULL,
                target_id TEXT,
                data TEXT,
                usage_count INTEGER DEFAULT 0,
                max_usage INTEGER DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active INTEGER DEFAULT 1
            )
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS deep_link_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                link_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (link_id) REFERENCES {$this->tableName}(id)
            )
        ");
    }

    /**
     * Register a route handler
     * 
     * @param string $pattern Pattern to match (supports wildcards *)
     * @param callable $handler Handler function
     */
    public function registerRoute(string $pattern, callable $handler): void {
        $this->routes[$pattern] = $handler;
    }

    /**
     * Create a static deep link
     * 
     * @param string $param The start parameter
     * @param string $targetType Type of target (message, callback, custom)
     * @param string|null $targetId Target identifier
     * @param array|null $data Additional data to store
     * @param int|null $maxUsage Maximum number of uses (null = unlimited)
     * @param string|null $expiresAt Expiration date (Y-m-d H:i:s)
     * @param int|null $createdBy Creator user ID
     * @return string Generated link URL
     */
    public function createStaticLink(
        string $param,
        string $targetType,
        ?string $targetId = null,
        ?array $data = null,
        ?int $maxUsage = null,
        ?string $expiresAt = null,
        ?int $createdBy = null
    ): string {
        // Validate parameter (Telegram allows only A-Z, a-z, 0-9, _, -)
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $param)) {
            throw new Exception("Invalid parameter format. Use only A-Z, a-z, 0-9, _, - (max 64 chars)");
        }

        $dataJson = $data ? json_encode($data) : null;

        $this->db->query(
            "INSERT INTO {$this->tableName} 
            (param, type, target_type, target_id, data, max_usage, expires_at, created_by) 
            VALUES (?, 'static', ?, ?, ?, ?, ?, ?)",
            [$param, $targetType, $targetId, $dataJson, $maxUsage, $expiresAt, $createdBy]
        );

        return $this->generateUrl($param);
    }

    /**
     * Create a dynamic deep link with encoded data
     * 
     * @param array $data Data to encode in the link
     * @param string|null $prefix Optional prefix for the parameter
     * @return array ['url' => string, 'param' => string]
     */
    public function createDynamicLink(array $data, ?string $prefix = null): array {
        $encoded = base64_encode(json_encode($data));
        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], $encoded); // URL-safe
        
        $param = $prefix ? "{$prefix}_{$encoded}" : $encoded;
        
        // Truncate if too long (Telegram limit is 64 chars)
        if (strlen($param) > 64) {
            // For long data, store in DB and use short ID
            $shortId = substr(md5($encoded), 0, 8);
            $this->db->query(
                "INSERT INTO {$this->tableName} (param, type, target_type, data) 
                VALUES (?, 'dynamic', 'data', ?)",
                [$shortId, json_encode($data)]
            );
            $param = $prefix ? "{$prefix}_{$shortId}" : $shortId;
        }

        return [
            'url' => $this->generateUrl($param),
            'param' => $param,
            'data' => $data
        ];
    }

    /**
     * Generate a deep link URL
     */
    public function generateUrl(string $param): string {
        $botUsername = $this->bot->getUsername();
        if (!$botUsername) {
            throw new Exception("Bot username not available. Make sure the bot token is valid.");
        }
        return "https://t.me/{$botUsername}?start={$param}";
    }

    /**
     * Process a start parameter from /start command
     * 
     * @param string $param The start parameter
     * @param array $user User information
     * @param int $chatId Chat ID
     * @return mixed Result from handler or false if no route matched
     */
    public function processStartParam(string $param, array $user, int $chatId) {
        // Record usage
        $link = $this->getLinkByParam($param);
        
        if ($link) {
            // Check if link is active
            if (!$link['is_active']) {
                return ['success' => false, 'error' => 'Link is deactivated'];
            }

            // Check expiration
            if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
                return ['success' => false, 'error' => 'Link has expired'];
            }

            // Check usage limit
            if ($link['max_usage'] !== null && $link['usage_count'] >= $link['max_usage']) {
                return ['success' => false, 'error' => 'Link usage limit reached'];
            }

            // Record usage
            $this->recordUsage($link['id'], $user['id'], $chatId);
            
            // Increment usage count
            $this->db->query(
                "UPDATE {$this->tableName} SET usage_count = usage_count + 1 WHERE id = ?",
                [$link['id']]
            );

            // Decode data if dynamic link
            $data = null;
            if ($link['type'] === 'dynamic' && $link['target_type'] === 'data') {
                $decoded = str_replace(['-', '_'], ['+', '/'], $param);
                $padding = strlen($decoded) % 4;
                if ($padding) {
                    $decoded .= str_repeat('=', 4 - $padding);
                }
                $data = json_decode(base64_decode($decoded), true);
                
                // If decoding failed, try from stored data
                if (!$data && $link['data']) {
                    $data = json_decode($link['data'], true);
                }
            } elseif ($link['data']) {
                $data = json_decode($link['data'], true);
            }

            return [
                'success' => true,
                'link' => $link,
                'data' => $data,
                'target_type' => $link['target_type'],
                'target_id' => $link['target_id']
            ];
        }

        // Try to match registered routes
        foreach ($this->routes as $pattern => $handler) {
            if ($this->matchPattern($pattern, $param)) {
                $matches = $this->extractPatternMatches($pattern, $param);
                return call_user_func($handler, $param, $matches, $user, $chatId);
            }
        }

        // No route matched
        return ['success' => false, 'error' => 'No route matched', 'param' => $param];
    }

    /**
     * Match a pattern against a parameter
     */
    private function matchPattern(string $pattern, string $param): bool {
        // Convert wildcard pattern to regex
        $regex = str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/'));
        $regex = '/^' . $regex . '$/i';
        return (bool) preg_match($regex, $param);
    }

    /**
     * Extract matches from pattern
     */
    private function extractPatternMatches(string $pattern, string $param): array {
        $regex = str_replace(['\\*', '\\?'], ['(.*)', '(.)'], preg_quote($pattern, '/'));
        $regex = '/^' . $regex . '$/i';
        preg_match($regex, $param, $matches);
        return array_slice($matches, 1); // Remove full match
    }

    /**
     * Get link by parameter
     */
    public function getLinkByParam(string $param): ?array {
        $result = $this->db->query(
            "SELECT * FROM {$this->tableName} WHERE param = ? AND is_active = 1",
            [$param],
            true
        );
        return $result[0] ?? null;
    }

    /**
     * Record link usage
     */
    private function recordUsage(int $linkId, int $userId, int $chatId): void {
        $this->db->query(
            "INSERT INTO deep_link_usage (link_id, user_id, chat_id) VALUES (?, ?, ?)",
            [$linkId, $userId, $chatId]
        );
    }

    /**
     * Get all links
     */
    public function getAllLinks(int $limit = 50, int $offset = 0, ?string $status = null): array {
        $where = [];
        $params = [];

        if ($status !== null) {
            if ($status === 'active') {
                $where[] = "is_active = 1";
                $where[] = "(expires_at IS NULL OR expires_at > datetime('now'))";
                $where[] = "(max_usage IS NULL OR usage_count < max_usage)";
            } elseif ($status === 'expired') {
                $where[] = "expires_at IS NOT NULL AND expires_at <= datetime('now')";
            } elseif ($status === 'limit_reached') {
                $where[] = "max_usage IS NOT NULL AND usage_count >= max_usage";
            } elseif ($status === 'inactive') {
                $where[] = "is_active = 0";
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params = array_merge($params, [$limit, $offset]);

        return $this->db->query(
            "SELECT * FROM {$this->tableName} {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params,
            true
        );
    }

    /**
     * Deactivate a link
     */
    public function deactivateLink(int $linkId): bool {
        $this->db->query(
            "UPDATE {$this->tableName} SET is_active = 0 WHERE id = ?",
            [$linkId]
        );
        return $this->db->affectedRows() > 0;
    }

    /**
     * Activate a link
     */
    public function activateLink(int $linkId): bool {
        $this->db->query(
            "UPDATE {$this->tableName} SET is_active = 1 WHERE id = ?",
            [$linkId]
        );
        return $this->db->affectedRows() > 0;
    }

    /**
     * Delete a link
     */
    public function deleteLink(int $linkId): bool {
        // Delete usage records first
        $this->db->query(
            "DELETE FROM deep_link_usage WHERE link_id = ?",
            [$linkId]
        );
        
        // Delete link
        $this->db->query(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [$linkId]
        );
        
        return $this->db->affectedRows() > 0;
    }

    /**
     * Get link statistics
     */
    public function getLinkStats(int $linkId): array {
        $link = $this->getLinkById($linkId);
        if (!$link) {
            return [];
        }

        $usageCount = $this->db->query(
            "SELECT COUNT(*) as count FROM deep_link_usage WHERE link_id = ?",
            [$linkId],
            true
        )[0]['count'] ?? 0;

        $uniqueUsers = $this->db->query(
            "SELECT COUNT(DISTINCT user_id) as count FROM deep_link_usage WHERE link_id = ?",
            [$linkId],
            true
        )[0]['count'] ?? 0;

        $recentUsage = $this->db->query(
            "SELECT COUNT(*) as count FROM deep_link_usage 
            WHERE link_id = ? AND used_at >= datetime('now', '-24 hours')",
            [$linkId],
            true
        )[0]['count'] ?? 0;

        return [
            'link' => $link,
            'total_usage' => $usageCount,
            'unique_users' => $uniqueUsers,
            'last_24h_usage' => $recentUsage,
            'remaining_uses' => $link['max_usage'] !== null ? max(0, $link['max_usage'] - $usageCount) : null,
            'is_expired' => $link['expires_at'] !== null && strtotime($link['expires_at']) < time(),
            'is_active' => (bool) $link['is_active']
        ];
    }

    /**
     * Get link by ID
     */
    public function getLinkById(int $linkId): ?array {
        $result = $this->db->query(
            "SELECT * FROM {$this->tableName} WHERE id = ?",
            [$linkId],
            true
        );
        return $result[0] ?? null;
    }

    /**
     * Get usage history for a link
     */
    public function getLinkUsageHistory(int $linkId, int $limit = 100): array {
        return $this->db->query(
            "SELECT dlu.*, u.username, u.first_name, u.last_name 
            FROM deep_link_usage dlu
            LEFT JOIN users u ON dlu.user_id = u.id
            WHERE dlu.link_id = ?
            ORDER BY dlu.used_at DESC
            LIMIT ?",
            [$linkId, $limit],
            true
        );
    }

    /**
     * Create a referral link
     * 
     * @param int $referrerId User ID of the referrer
     * @param array $bonusData Bonus data to give to referrer
     * @return array Link information
     */
    public function createReferralLink(int $referrerId, array $bonusData = []): array {
        $param = "ref_{$referrerId}";
        $data = array_merge($bonusData, ['referrer_id' => $referrerId]);
        
        return $this->createDynamicLink($data, 'ref');
    }

    /**
     * Process a referral
     * 
     * @param int $referrerId Referrer user ID
     * @param int $newUserId New user ID
     * @return bool Success
     */
    public function processReferral(int $referrerId, int $newUserId): bool {
        // Check if this referral already exists
        $exists = $this->db->query(
            "SELECT id FROM deep_link_usage 
            WHERE user_id = ? AND link_id IN (
                SELECT id FROM {$this->tableName} WHERE param LIKE 'ref_%'
            )",
            [$newUserId],
            true
        );

        if (!empty($exists)) {
            return false; // Already referred
        }

        // Record the referral
        $link = $this->getLinkByParam("ref_{$referrerId}");
        if ($link) {
            $this->recordUsage($link['id'], $newUserId, $newUserId);
            return true;
        }

        return false;
    }

    /**
     * Cleanup expired links
     */
    public function cleanupExpiredLinks(): int {
        $this->db->query(
            "UPDATE {$this->tableName} SET is_active = 0 
            WHERE expires_at IS NOT NULL AND expires_at <= datetime('now') AND is_active = 1"
        );
        return $this->db->affectedRows();
    }
}
