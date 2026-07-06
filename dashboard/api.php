<?php

/**
 * Telegram Bot Dashboard API
 * Backend API for the dashboard - MySQL Version
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Core/Config.php';
require_once __DIR__ . '/../src/Core/Logger.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Bot/TelegramBot.php';

use App\Core\Config;
use App\Core\Logger;
use App\Database\Database;
use App\Bot\TelegramBot;

// Initialize Configuration
$config = new Config(__DIR__ . '/../config/config.php');

// Initialize Logger
$logger = new Logger($config->get('logging'));

// Initialize Database (MySQL)
try {
    $db = new Database($config->get('database'));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            echo json_encode(['success' => true, 'stats' => getDashboardStats($db)]);
            break;
            
        case 'get_bots':
            $bots = $db->getAllBots();
            echo json_encode(['success' => true, 'bots' => $bots ?: []]);
            break;
            
        case 'add_bot':
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? '';
            $username = $data['username'] ?? '';
            
            if (empty($token)) {
                throw new Exception('Bot token is required');
            }
            
            // Validate token by getting bot info
            $botClient = new TelegramBot($token, $logger);
            $botInfo = $botClient->getMe();
            
            if (!$botInfo['ok']) {
                throw new Exception('Invalid bot token');
            }
            
            $botUsername = $botInfo['result']['username'] ?: $username;
            $botId = $db->saveBot($token, $botUsername);
            
            $logger->info('Bot added via dashboard', ['bot_id' => $botId, 'username' => $botUsername]);
            echo json_encode(['success' => true, 'bot_id' => $botId]);
            break;
            
        case 'toggle_bot':
            $data = json_decode(file_get_contents('php://input'), true);
            $botId = $data['bot_id'] ?? 0;
            $isActive = $data['is_active'] ?? false;
            
            $db->updateBotStatus($botId, ['is_active' => $isActive ? 1 : 0]);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_bot':
            $data = json_decode(file_get_contents('php://input'), true);
            $botId = $data['bot_id'] ?? 0;
            
            $db->deleteBot($botId);
            $logger->info('Bot deleted via dashboard', ['bot_id' => $botId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'set_webhook':
            $data = json_decode(file_get_contents('php://input'), true);
            $url = $data['url'] ?? '';
            $secretToken = $data['secret_token'] ?? '';
            $allowedUpdates = $data['allowed_updates'] ?? [];
            
            if (empty($url)) {
                throw new Exception('Webhook URL is required');
            }
            
            $activeBot = $db->getActiveBot();
            if (!$activeBot) {
                throw new Exception('No active bot configured');
            }
            
            $botClient = new TelegramBot($activeBot['token'], $logger);
            $result = $botClient->setWebhook($url, !empty($secretToken) ? $secretToken : null, $allowedUpdates);
            
            if ($result['ok']) {
                $db->updateBotStatus($activeBot['id'], ['webhook_status' => 'active']);
                $logger->info('Webhook set via dashboard', ['url' => $url]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($result['description'] ?? 'Failed to set webhook');
            }
            break;
            
        case 'delete_webhook':
            $activeBot = $db->getActiveBot();
            if (!$activeBot) {
                throw new Exception('No active bot configured');
            }
            
            $botClient = new TelegramBot($activeBot['token'], $logger);
            $result = $botClient->deleteWebhook();
            
            if ($result['ok']) {
                $db->updateBotStatus($activeBot['id'], ['webhook_status' => 'inactive']);
                $logger->info('Webhook deleted via dashboard');
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($result['description'] ?? 'Failed to delete webhook');
            }
            break;
            
        case 'get_webhook_info':
            $activeBot = $db->getActiveBot();
            if (!$activeBot) {
                echo json_encode(['ok' => false, 'error' => 'No active bot configured']);
                break;
            }
            
            $botClient = new TelegramBot($activeBot['token'], $logger);
            $result = $botClient->getWebhookInfo();
            echo json_encode($result);
            break;
            
        case 'get_payment_settings':
            $settings = $db->getSetting('payment_settings') ?: [];
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'save_payment_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $settings = [
                'provider' => $data['provider'] ?? 'stripe',
                'provider_token' => $data['provider_token'] ?? '',
                'currency' => $data['currency'] ?? 'USD'
            ];
            
            $db->saveSetting('payment_settings', $settings);
            $config->updateSetting('payment', $settings);
            $logger->info('Payment settings updated via dashboard', ['provider' => $settings['provider']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'send_test_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $chatId = $data['chat_id'] ?? '';
            
            if (empty($chatId)) {
                throw new Exception('Chat ID is required');
            }
            
            $activeBot = $db->getActiveBot();
            if (!$activeBot) {
                throw new Exception('No active bot configured');
            }
            
            $paymentSettings = $db->getSetting('payment_settings') ?: [];
            $botClient = new TelegramBot($activeBot['token'], $logger);
            
            $result = $botClient->sendInvoice($chatId, [
                'title' => $data['title'] ?? 'Test Product',
                'description' => $data['description'] ?? 'Test Description',
                'payload' => 'test_' . time(),
                'provider_token' => $paymentSettings['provider_token'] ?? '',
                'currency' => $paymentSettings['currency'] ?? 'USD',
                'prices' => [['label' => 'Test Item', 'amount' => $data['amount'] ?? 999]]
            ]);
            
            if ($result['ok']) {
                $logger->info('Test invoice sent', ['chat_id' => $chatId]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($result['description'] ?? 'Failed to send invoice');
            }
            break;
            
        case 'get_transactions':
            $transactions = $db->query("SELECT * FROM successful_payments ORDER BY created_at DESC LIMIT 50", [], true);
            echo json_encode(['success' => true, 'transactions' => $transactions ?: []]);
            break;
            
        case 'get_admins':
            $admins = $db->query("SELECT * FROM admin_users ORDER BY created_at DESC", [], true);
            echo json_encode(['success' => true, 'admins' => $admins ?: []]);
            break;
            
        case 'add_admin':
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? 0;
            $username = $data['username'] ?? '';
            $fullName = $data['full_name'] ?? '';
            $permissions = $data['permissions'] ?? [];
            
            if (empty($userId)) {
                throw new Exception('User ID is required');
            }
            
            $db->addAdminUser($userId, $username, $fullName, $permissions);
            $logger->info('Admin user added via dashboard', ['user_id' => $userId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'remove_admin':
            $data = json_decode(file_get_contents('php://input'), true);
            $adminId = $data['admin_id'] ?? 0;
            
            $db->query("DELETE FROM admin_users WHERE id = ?", [$adminId]);
            $logger->info('Admin user removed via dashboard', ['admin_id' => $adminId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_logs':
            $level = $_GET['level'] ?? 'all';
            $search = $_GET['search'] ?? '';
            
            $logs = $db->getLogs(100, $level !== 'all' ? $level : null);
            
            // Filter by search if provided
            if (!empty($search)) {
                $logs = array_filter($logs, function($log) use ($search) {
                    return stripos($log['message'], $search) !== false;
                });
            }
            
            echo json_encode([
                'success' => true, 
                'logs' => array_values($logs),
                'log_path' => $config->get('logging')['path']
            ]);
            break;
            
        case 'clear_old_logs':
            $db->cleanupLogs(7); // Keep only last 7 days
            $logger->info('Old logs cleared via dashboard');
            echo json_encode(['success' => true]);
            break;
            
        case 'get_broadcasts':
            echo json_encode(['success' => true, 'broadcasts' => []]); // Implement if needed
            break;
            
        case 'create_broadcast':
            // Implement broadcast functionality
            echo json_encode(['success' => true]);
            break;
            
        case 'get_deeplinks':
            echo json_encode(['success' => true, 'links' => []]); // Implement if needed
            break;
            
        case 'create_deeplink':
            // Implement deep link functionality
            echo json_encode(['success' => true]);
            break;
            
        case 'toggle_deeplink':
            // Implement deep link toggle
            echo json_encode(['success' => true]);
            break;
            
        case 'get_users':
            $search = $_GET['search'] ?? '';
            $users = getUsers($db, $search);
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        case 'get_command_stats':
            echo json_encode(['success' => true, 'commands' => []]); // Implement if needed
            break;
            
        case 'get_errors':
            echo json_encode(['success' => true, 'errors' => []]); // Implement if needed
            break;
            
        case 'save_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            foreach ($data as $key => $value) {
                $db->saveSetting($key, $value);
            }
            $logger->info('Settings updated via dashboard');
            echo json_encode(['success' => true]);
            break;
            
        case 'export_data':
            // Implement data export
            echo json_encode(['success' => true]);
            break;
            
        case 'cleanup_data':
            $data = json_decode(file_get_contents('php://input'), true);
            $days = $data['days'] ?? 90;
            $db->cleanupOldData($days);
            echo json_encode(['success' => true, 'deleted' => 0]);
            break;
            
        case 'clear_cache':
            // Implement cache clearing
            echo json_encode(['success' => true]);
            break;
            
        case 'clear_errors':
            $db->query("DELETE FROM logs WHERE level = 'ERROR' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            echo json_encode(['success' => true, 'deleted' => 0]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    $logger->error('API Error', ['action' => $action, 'error' => $e->getMessage()]);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats($db): array {
    $stats = $db->getStatistics();
    
    return [
        'total_users' => $stats['users'] ?? 0,
        'active_users_24h' => $stats['active_users_24h'] ?? 0,
        'total_messages' => $stats['updates'] ?? 0,
        'success_rate' => 100, // Calculate based on error rate
        'user_growth' => [], // Implement growth calculation
        'message_types' => [], // Implement message type distribution
        'hourly_activity' => [], // Implement hourly activity
        'errors' => [], // Implement recent errors
        'commands' => [] // Implement top commands
    ];
}

/**
 * Get users with optional search
 */
function getUsers($db, string $search = ''): array {
    if (!empty($search)) {
        $searchParam = "%{$search}%";
        return $db->query(
            "SELECT * FROM users 
             WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
             ORDER BY created_at DESC
             LIMIT 100",
            [$searchParam, $searchParam, $searchParam],
            true
        );
    }
    
    return $db->query(
        "SELECT * FROM users ORDER BY created_at DESC LIMIT 100",
        [],
        true
    );
}
