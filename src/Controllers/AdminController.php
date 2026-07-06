<?php

namespace TelegramBot\Controllers;

use TelegramBot\Core\Config;
use TelegramBot\Database\Database;

/**
 * Admin Controller for Control Panel
 */
class AdminController
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getDashboard(): array
    {
        return [
            'stats' => $this->db->stats(),
            'bots' => $this->db->getAllBots(false),
            'adminUsers' => $this->db->getAllAdminUsers(),
        ];
    }

    public function manageBots(): array
    {
        return $this->db->getAllBots(false);
    }

    public function addBot(string $token, string $username, ?string $name = null): bool
    {
        return $this->db->saveBot($token, $username, $name) !== false;
    }

    public function toggleBot(int $botId, bool $active): bool
    {
        $bot = $this->db->selectOne('bot_settings', ['bot_username'], 'id = ?', [$botId]);
        if (!$bot) return false;
        return $this->db->setBotActive($bot['bot_username'], $active) > 0;
    }

    public function deleteBot(int $botId): bool
    {
        return $this->db->delete('bot_settings', 'id = ?', [$botId]) > 0;
    }

    public function manageAdminUsers(): array
    {
        return $this->db->getAllAdminUsers();
    }

    public function addAdminUser(string $username, string $password, ?string $email = null, bool $isSuperAdmin = false): bool
    {
        return $this->db->createAdminUser($username, $password, $email, $isSuperAdmin) !== false;
    }

    public function deleteAdminUser(int $userId): bool
    {
        return $this->db->deleteAdminUser($userId) > 0;
    }

    public function getLogs(?string $level = null, ?string $category = null, int $limit = 100): array
    {
        return $this->db->getLogs($level, $category, $limit);
    }

    public function clearOldLogs(int $days = 30): int
    {
        return $this->db->clearOldLogs($days);
    }

    public function backupDatabase(string $filename): bool
    {
        return $this->db->backup($filename);
    }

    public function restoreDatabase(string $filename): bool
    {
        return $this->db->restore($filename);
    }

    public function optimizeDatabase(): bool
    {
        return $this->db->optimize();
    }
}
