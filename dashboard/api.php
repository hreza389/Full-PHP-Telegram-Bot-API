<?php

/**
 * Telegram Bot Dashboard API
 * Backend API for the dashboard
 */

require_once __DIR__ . '/../TelegramBot.php';
require_once __DIR__ . '/../TelegramDB.php';
require_once __DIR__ . '/../TelegramBroadcast.php';
require_once __DIR__ . '/../TelegramDeepLinking.php';
require_once __DIR__ . '/../TelegramStats.php';
require_once __DIR__ . '/../TelegramCache.php';
require_once __DIR__ . '/../TelegramLogger.php';

// Configuration
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('DB_PATH', __DIR__ . '/../bot_database.sqlite');
define('ADMIN_USER_IDS', [123456789]); // Replace with your admin user IDs

// Initialize components
$db = new TelegramDB(DB_PATH);
$bot = new TelegramBot(BOT_TOKEN);
$broadcast = new TelegramBroadcast($bot, $db);
$deepLinking = new TelegramDeepLinking($bot, $db);
$stats = new TelegramStats($bot, $db);
$cache = new TelegramCache(__DIR__ . '/../cache');
$logger = new TelegramLogger(__DIR__ . '/../logs');

// Set JSON response header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            echo json_encode(['success' => true, 'stats' => getDashboardStats()]);
            break;
            
        case 'get_broadcasts':
            echo json_encode(['success' => true, 'broadcasts' => $broadcast->getAllJobs(50)]);
            break;
            
        case 'create_broadcast':
            $data = json_decode(file_get_contents('php://input'), true);
            $chatIds = getTargetChatIds($data['target'] ?? 'all', $data['chat_ids'] ?? '');
            $jobId = $broadcast->createJob(
                $chatIds,
                $data['text'] ?? '',
                $data['parse_mode'] ?? 'HTML',
                ['disable_notification' => $data['disable_notification'] ?? false]
            );
            echo json_encode(['success' => true, 'job_id' => $jobId]);
            break;
            
        case 'get_deeplinks':
            echo json_encode(['success' => true, 'links' => $deepLinking->getAllLinks(100)]);
            break;
            
        case 'create_deeplink':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data['type'] === 'referral') {
                // Create referral link (would need referrer ID in real implementation)
                $linkData = $deepLinking->createReferralLink(ADMIN_USER_IDS[0]);
                echo json_encode(['success' => true, 'url' => $linkData['url'], 'param' => $linkData['param']]);
            } elseif ($data['type'] === 'dynamic') {
                $linkData = $deepLinking->createDynamicLink(
                    json_decode($data['data'] ?? '{}', true),
                    'dyn'
                );
                echo json_encode(['success' => true, 'url' => $linkData['url'], 'param' => $linkData['param']]);
            } else {
                $url = $deepLinking->createStaticLink(
                    $data['param'] ?? uniqid(),
                    'custom',
                    null,
                    null,
                    $data['max_uses'] ?: null,
                    $data['expires_at'] ?: null
                );
                echo json_encode(['success' => true, 'url' => $url, 'param' => $data['param']]);
            }
            break;
            
        case 'toggle_deeplink':
            $data = json_decode(file_get_contents('php://input'), true);
            $success = $data['active'] ? $deepLinking->activateLink($data['link_id']) : $deepLinking->deactivateLink($data['link_id']);
            echo json_encode(['success' => $success]);
            break;
            
        case 'get_users':
            $search = $_GET['search'] ?? '';
            $users = getUsers($search);
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        case 'get_command_stats':
            echo json_encode(['success' => true, 'commands' => $stats->getTopCommands(50)]);
            break;
            
        case 'get_errors':
            echo json_encode(['success' => true, 'errors' => $stats->getRecentErrors(100)]);
            break;
            
        case 'save_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            // In a real implementation, save to config file
            $logger->info('Settings updated', $data);
            echo json_encode(['success' => true]);
            break;
            
        case 'export_data':
            echo $stats->exportToJson();
            break;
            
        case 'cleanup_data':
            $data = json_decode(file_get_contents('php://input'), true);
            $deleted = $stats->cleanupOldData($data['days'] ?? 90);
            echo json_encode(['success' => true, 'deleted' => $deleted]);
            break;
            
        case 'clear_cache':
            $cache->clear();
            echo json_encode(['success' => true]);
            break;
            
        case 'clear_errors':
            $db->query("DELETE FROM stats_errors WHERE created_at < datetime('now', '-30 days')");
            echo json_encode(['success' => true, 'deleted' => $db->affectedRows()]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    $logger->error('API Error', ['action' => $action, 'error' => $e->getMessage()]);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats(): array {
    global $stats;
    
    $overall = $stats->getOverallStats();
    
    return array_merge($overall, [
        'user_growth' => $stats->getUserGrowth(30),
        'message_types' => $stats->getMessageTypesDistribution(),
        'hourly_activity' => $stats->getHourlyActivity(),
        'errors' => $stats->getRecentErrors(10),
        'commands' => $stats->getTopCommands(10)
    ]);
}

/**
 * Get target chat IDs based on selection
 */
function getTargetChatIds(string $target, string $customIds = ''): array {
    global $db;
    
    if ($target === 'custom' && !empty($customIds)) {
        return array_map('trim', explode(',', $customIds));
    }
    
    if ($target === 'active') {
        $result = $db->query(
            "SELECT user_id FROM stats_users 
             WHERE last_seen >= datetime('now', '-24 hours') AND is_active = 1",
            [],
            true
        );
        return array_column($result, 'user_id');
    }
    
    // All users
    $result = $db->query(
        "SELECT user_id FROM stats_users WHERE is_active = 1",
        [],
        true
    );
    return array_column($result, 'user_id');
}

/**
 * Get users with optional search
 */
function getUsers(string $search = ''): array {
    global $db;
    
    if (!empty($search)) {
        $searchParam = "%{$search}%";
        return $db->query(
            "SELECT * FROM stats_users 
             WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
             ORDER BY last_seen DESC
             LIMIT 100",
            [$searchParam, $searchParam, $searchParam],
            true
        );
    }
    
    return $db->query(
        "SELECT * FROM stats_users ORDER BY last_seen DESC LIMIT 100",
        [],
        true
    );
}
