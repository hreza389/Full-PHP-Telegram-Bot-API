<?php

/**
 * Telegram Bot Control Panel
 * 
 * A centralized web-based control panel for managing:
 * - Bot settings and configurations
 * - Database operations (backup, restore, statistics)
 * - System settings
 * - User and chat management
 * - Logs viewing
 * - Cron job management
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 * 
 * USAGE:
 * ------
 * 1. Configure DATABASE_CONFIG below
 * 2. Set ADMIN_PASSWORD for security
 * 3. Access via browser: http://yourdomain.com/control_panel.php
 * 4. Login with admin password
 */

// ==================== CONFIGURATION ====================

define('DATABASE_CONFIG', [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'telegram_bot',
    'username' => 'root',
    'password' => '', // CHANGE THIS!
    'charset' => 'utf8mb4'
]);

define('ADMIN_PASSWORD', 'admin123'); // CHANGE THIS TO A STRONG PASSWORD!

define('BACKUP_DIR', __DIR__ . '/backups');

// ==================== SESSION & AUTHENTICATION ====================

session_start();

function checkAuth(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth(): void
{
    if (!checkAuth()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
            if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
                $_SESSION['authenticated'] = true;
                header('Location: control_panel.php');
                exit;
            } else {
                $error = 'Invalid password';
            }
        }
        
        includeLoginScreen($error ?? null);
        exit;
    }
}

function includeLoginScreen(?string $error = null): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bot Control Panel - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            h1 { color: #333; margin-bottom: 10px; text-align: center; }
            p { color: #666; text-align: center; margin-bottom: 30px; }
            input[type="password"] {
                width: 100%;
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 5px;
                font-size: 16px;
                margin-bottom: 20px;
                transition: border-color 0.3s;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            button:hover { transform: translateY(-2px); }
            .error {
                background: #fee;
                color: #c33;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🤖 Bot Control Panel</h1>
            <p>Enter admin password to continue</p>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="Admin Password" required autofocus>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

// ==================== INCLUDE FILES ====================

requireAuth();

if (!file_exists(__DIR__ . '/TelegramDB.php')) {
    die('Error: TelegramDB.php not found');
}

require_once __DIR__ . '/TelegramDB.php';

// Initialize database
$db = new TelegramDB(DATABASE_CONFIG);

// Ensure backup directory exists
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

// ==================== ACTION HANDLERS ====================

$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';
$message = null;
$error = null;

switch ($action) {
    case 'logout':
        session_destroy();
        header('Location: control_panel.php');
        exit;
    
    case 'backup':
        try {
            $filename = BACKUP_DIR . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
            if ($db->backup($filename)) {
                $message = "Backup created successfully: {$filename}";
            } else {
                $error = "Backup failed";
            }
        } catch (Exception $e) {
            $error = "Backup error: " . $e->getMessage();
        }
        break;
    
    case 'restore':
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $tmpFile = $_FILES['backup_file']['tmp_name'];
                if ($db->restore($tmpFile)) {
                    $message = "Database restored successfully";
                } else {
                    $error = "Restore failed";
                }
            } catch (Exception $e) {
                $error = "Restore error: " . $e->getMessage();
            }
        } else {
            $error = "No file uploaded or upload error";
        }
        break;
    
    case 'optimize':
        try {
            if ($db->optimize()) {
                $message = "Database optimized successfully";
            } else {
                $error = "Optimization failed";
            }
        } catch (Exception $e) {
            $error = "Optimization error: " . $e->getMessage();
        }
        break;
    
    case 'truncate':
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            try {
                if ($db->truncateAll()) {
                    $message = "All tables truncated successfully";
                } else {
                    $error = "Truncate failed";
                }
            } catch (Exception $e) {
                $error = "Truncate error: " . $e->getMessage();
            }
        }
        break;
    
    case 'add_bot':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $token = $_POST['bot_token'] ?? '';
                $username = $_POST['bot_username'] ?? '';
                $name = $_POST['bot_name'] ?? '';
                
                if ($token && $username) {
                    $db->saveBot($token, $username, $name);
                    $message = "Bot added successfully";
                } else {
                    $error = "Token and username are required";
                }
            } catch (Exception $e) {
                $error = "Error adding bot: " . $e->getMessage();
            }
        }
        break;
    
    case 'toggle_bot':
        if (isset($_GET['bot_id'])) {
            $bot = $db->selectOne('bot_settings', [], 'id = ?', [$_GET['bot_id']]);
            if ($bot) {
                $newStatus = !$bot['is_active'];
                $db->setBotActive($bot['bot_username'], $newStatus);
                $message = "Bot " . ($newStatus ? "activated" : "deactivated");
            }
        }
        break;
    
    case 'delete_bot':
        if (isset($_GET['bot_id'])) {
            $db->delete('bot_settings', 'id = ?', [$_GET['bot_id']]);
            $message = "Bot deleted successfully";
        }
        break;
    
    case 'clear_logs':
        $days = intval($_GET['days'] ?? 30);
        $count = $db->clearOldLogs($days);
        $message = "Cleared {$count} log entries older than {$days} days";
        break;
    
    case 'clean_cache':
        $count = $db->cleanExpiredCache();
        $message = "Cleaned {$count} expired cache entries";
        break;
    
    case 'clean_conversations':
        $count = $db->cleanExpiredConversations();
        $message = "Cleaned {$count} expired conversations";
        break;
}

// Get statistics
$stats = $db->stats();
$bots = $db->getAllBots(false);
$recentLogs = $db->getLogs(null, null, 50);
$cronJobs = $db->getAllCronJobs();

// ==================== HTML OUTPUT ====================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Control Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f6fa;
            color: #2d3436;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 24px; }
        .nav {
            display: flex;
            gap: 10px;
            background: white;
            padding: 15px 40px;
            border-bottom: 1px solid #e0e0e0;
            flex-wrap: wrap;
        }
        .nav a {
            color: #667eea;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav a:hover, .nav a.active {
            background: #667eea;
            color: white;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .stat-item:last-child { border-bottom: none; }
        .stat-value {
            font-weight: bold;
            color: #667eea;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn:hover { background: #5568d3; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #d68910; }
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        tr:hover { background: #f8f9fa; }
        .status-active {
            color: #27ae60;
            font-weight: bold;
        }
        .status-inactive {
            color: #e74c3c;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-info { background: #17a2b8; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .section { display: none; }
        .section.active { display: block; }
        .log-entry {
            padding: 10px;
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 0 5px 5px 0;
        }
        .log-level-ERROR { border-left-color: #e74c3c; }
        .log-level-WARNING { border-left-color: #f39c12; }
        .log-level-INFO { border-left-color: #667eea; }
        .log-level-DEBUG { border-left-color: #27ae60; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🤖 Telegram Bot Control Panel</h1>
        <div>
            <span style="margin-right: 20px;">Welcome, Admin</span>
            <a href="?action=logout" class="btn btn-danger">Logout</a>
        </div>
    </div>
    
    <div class="nav">
        <a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="?action=bots" class="<?= $action === 'bots' ? 'active' : '' ?>">🤖 Bots</a>
        <a href="?action=database" class="<?= $action === 'database' ? 'active' : '' ?>">💾 Database</a>
        <a href="?action=users" class="<?= $action === 'users' ? 'active' : '' ?>">👥 Users</a>
        <a href="?action=logs" class="<?= $action === 'logs' ? 'active' : '' ?>">📝 Logs</a>
        <a href="?action=settings" class="<?= $action === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- DASHBOARD SECTION -->
        <div class="section <?= $action === 'dashboard' ? 'active' : '' ?>" id="dashboard">
            <div class="grid">
                <div class="card">
                    <h2>📊 Database Statistics</h2>
                    <div class="stat-item">
                        <span>Total Users</span>
                        <span class="stat-value"><?= $stats['users'] ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Total Chats</span>
                        <span class="stat-value"><?= $stats['chats'] ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Active Bots</span>
                        <span class="stat-value"><?= count(array_filter($bots, fn($b) => $b['is_active'])) ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Settings</span>
                        <span class="stat-value"><?= $stats['settings'] ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Active Conversations</span>
                        <span class="stat-value"><?= $stats['conversations'] ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Database Size</span>
                        <span class="stat-value"><?= round($stats['database_size'] / 1024 / 1024, 2) ?> MB</span>
                    </div>
                </div>
                
                <div class="card">
                    <h2>🔧 Quick Actions</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="?action=backup" class="btn btn-success">💾 Create Backup</a>
                        <a href="?action=optimize" class="btn btn-warning">⚡ Optimize Database</a>
                        <a href="?action=clean_cache" class="btn">🗑️ Clean Expired Cache</a>
                        <a href="?action=clean_conversations" class="btn">🗑️ Clean Expired Conversations</a>
                    </div>
                </div>
                
                <div class="card">
                    <h2>🤖 Registered Bots</h2>
                    <?php if (empty($bots)): ?>
                        <p>No bots registered yet.</p>
                    <?php else: ?>
                        <?php foreach ($bots as $bot): ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                                <strong>@<?= htmlspecialchars($bot['bot_username']) ?></strong>
                                <span class="badge <?= $bot['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $bot['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="?action=bots" class="btn" style="margin-top: 15px;">Manage Bots</a>
                </div>
            </div>
            
            <div class="card">
                <h2>📝 Recent Logs</h2>
                <?php if (empty($recentLogs)): ?>
                    <p>No logs yet.</p>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="log-entry log-level-<?= htmlspecialchars($log['level']) ?>">
                            <small style="color: #999;"><?= $log['created_at'] ?></small>
                            <strong>[<?= htmlspecialchars($log['level']) ?>]</strong>
                            <?= htmlspecialchars($log['message']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="?action=logs" class="btn" style="margin-top: 15px;">View All Logs</a>
            </div>
        </div>
        
        <!-- BOTS SECTION -->
        <div class="section <?= $action === 'bots' ? 'active' : '' ?>" id="bots">
            <div class="card">
                <h2>➕ Add New Bot</h2>
                <form method="POST" action="?action=add_bot">
                    <div class="grid" style="grid-template-columns: 1fr 1fr 1fr auto;">
                        <div class="form-group">
                            <label>Bot Token</label>
                            <input type="text" name="bot_token" required placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        </div>
                        <div class="form-group">
                            <label>Bot Username</label>
                            <input type="text" name="bot_username" required placeholder="@mybot">
                        </div>
                        <div class="form-group">
                            <label>Bot Name</label>
                            <input type="text" name="bot_name" placeholder="My Bot">
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" class="btn btn-success">Add Bot</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>🤖 All Bots</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Webhook</th>
                            <th>Last Update</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bots as $bot): ?>
                            <tr>
                                <td><?= $bot['id'] ?></td>
                                <td>@<?= htmlspecialchars($bot['bot_username']) ?></td>
                                <td><?= htmlspecialchars($bot['bot_name']) ?></td>
                                <td>
                                    <span class="<?= $bot['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $bot['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= $bot['webhook_url'] ? htmlspecialchars($bot['webhook_url']) : '-' ?></td>
                                <td><?= $bot['last_update_id'] ?></td>
                                <td>
                                    <a href="?action=toggle_bot&bot_id=<?= $bot['id'] ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">
                                        <?= $bot['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </a>
                                    <a href="?action=delete_bot&bot_id=<?= $bot['id'] ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Delete this bot?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- DATABASE SECTION -->
        <div class="section <?= $action === 'database' ? 'active' : '' ?>" id="database">
            <div class="grid">
                <div class="card">
                    <h2>💾 Backup & Restore</h2>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <h4>Create Backup</h4>
                            <p style="color: #666; margin: 10px 0;">Download a complete SQL backup of your database.</p>
                            <a href="?action=backup" class="btn btn-success">Download Backup</a>
                        </div>
                        <hr style="border: none; border-top: 1px solid #e0e0e0;">
                        <div>
                            <h4>Restore from Backup</h4>
                            <p style="color: #666; margin: 10px 0;">Upload a SQL file to restore your database.</p>
                            <form method="POST" action="?action=restore" enctype="multipart/form-data">
                                <input type="file" name="backup_file" accept=".sql" required style="margin-bottom: 10px;">
                                <br>
                                <button type="submit" class="btn btn-warning" onclick="return confirm('This will overwrite your current database. Continue?')">Restore Database</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h2>⚡ Maintenance</h2>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <h4>Optimize Database</h4>
                            <p style="color: #666; margin: 10px 0;">Optimize all tables for better performance.</p>
                            <a href="?action=optimize" class="btn">Optimize Tables</a>
                        </div>
                        <hr style="border: none; border-top: 1px solid #e0e0e0;">
                        <div>
                            <h4>Danger Zone</h4>
                            <p style="color: #e74c3c; margin: 10px 0;">Warning: This will delete ALL data!</p>
                            <form method="POST" action="?action=truncate" onsubmit="return confirm('Are you ABSOLUTELY sure? This cannot be undone!')">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-danger">Truncate All Tables</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>📊 Detailed Statistics</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Users</td><td><?= $stats['users'] ?></td></tr>
                        <tr><td>Chats</td><td><?= $stats['chats'] ?></td></tr>
                        <tr><td>User-Chat Relationships</td><td><?= $stats['user_chats'] ?></td></tr>
                        <tr><td>Settings</td><td><?= $stats['settings'] ?></td></tr>
                        <tr><td>Bot Configurations</td><td><?= $stats['bot_settings'] ?></td></tr>
                        <tr><td>Conversations</td><td><?= $stats['conversations'] ?></td></tr>
                        <tr><td>Log Entries</td><td><?= $stats['logs'] ?></td></tr>
                        <tr><td>Stored Updates</td><td><?= $stats['updates'] ?></td></tr>
                        <tr><td>Broadcasts</td><td><?= $stats['broadcasts'] ?></td></tr>
                        <tr><td>Cron Jobs</td><td><?= $stats['cron_jobs'] ?></td></tr>
                        <tr><td>Cache Entries</td><td><?= $stats['cache'] ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- USERS SECTION -->
        <div class="section <?= $action === 'users' ? 'active' : '' ?>" id="users">
            <div class="card">
                <h2>👥 All Users</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Telegram ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Language</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $db->getAllUsers(100);
                        foreach ($users as $user):
                        ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= $user['telegram_id'] ?></td>
                                <td><?= $user['username'] ? '@' . htmlspecialchars($user['username']) : '-' ?></td>
                                <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                                <td><?= $user['language_code'] ?? '-' ?></td>
                                <td><?= $user['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- LOGS SECTION -->
        <div class="section <?= $action === 'logs' ? 'active' : '' ?>" id="logs">
            <div class="card">
                <h2>📝 System Logs</h2>
                <div style="margin-bottom: 20px;">
                    <a href="?action=clear_logs&days=7" class="btn btn-danger" onclick="return confirm('Clear logs older than 7 days?')">Clear 7+ Days</a>
                    <a href="?action=clear_logs&days=30" class="btn btn-warning" onclick="return confirm('Clear logs older than 30 days?')">Clear 30+ Days</a>
                    <a href="?action=clear_logs&days=90" class="btn" onclick="return confirm('Clear logs older than 90 days?')">Clear 90+ Days</a>
                </div>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="log-entry log-level-<?= htmlspecialchars($log['level']) ?>">
                        <small style="color: #999;"><?= $log['created_at'] ?></small>
                        <strong>[<?= htmlspecialchars($log['level']) ?>]</strong>
                        <?php if ($log['category']): ?>
                            <span class="badge badge-info"><?= htmlspecialchars($log['category']) ?></span>
                        <?php endif; ?>
                        <br>
                        <?= nl2br(htmlspecialchars($log['message'])) ?>
                        <?php if ($log['context']): ?>
                            <pre style="background: #f8f9fa; padding: 10px; margin-top: 10px; border-radius: 5px; overflow-x: auto;"><?= htmlspecialchars($log['context']) ?></pre>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- SETTINGS SECTION -->
        <div class="section <?= $action === 'settings' ? 'active' : '' ?>" id="settings">
            <div class="grid">
                <div class="card">
                    <h2>⚙️ System Settings</h2>
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" value="<?= DATABASE_CONFIG['host'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" value="<?= DATABASE_CONFIG['database'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Database Port</label>
                        <input type="text" value="<?= DATABASE_CONFIG['port'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Character Set</label>
                        <input type="text" value="<?= DATABASE_CONFIG['charset'] ?>" readonly>
                    </div>
                </div>
                
                <div class="card">
                    <h2>📅 Cron Jobs</h2>
                    <?php if (empty($cronJobs)): ?>
                        <p>No cron jobs registered.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Job Name</th>
                                    <th>Schedule</th>
                                    <th>Last Run</th>
                                    <th>Next Run</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cronJobs as $job): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($job['job_name']) ?></td>
                                        <td><code><?= htmlspecialchars($job['cron_expression']) ?></code></td>
                                        <td><?= $job['last_run'] ?? 'Never' ?></td>
                                        <td><?= $job['next_run'] ?? 'Not scheduled' ?></td>
                                        <td>
                                            <span class="<?= $job['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $job['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple navigation handling
        document.querySelectorAll('.nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href.startsWith('?action=')) {
                    const action = href.split('=')[1];
                    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                    document.getElementById(action).classList.add('active');
                    document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
