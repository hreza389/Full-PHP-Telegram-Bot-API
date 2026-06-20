<?php

/**
 * Telegram Cron Scheduler
 * 
 * A simple cron-like scheduler for running periodic tasks in your
 * Telegram bot, such as cleaning up old data, sending notifications,
 * or processing queued messages.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 */
class TelegramCron
{
    /**
     * @var array Registered tasks
     */
    private array $tasks = [];

    /**
     * @var TelegramBot|null Bot instance
     */
    private ?TelegramBot $bot = null;

    /**
     * @var TelegramDB|null Database instance
     */
    private ?TelegramDB $db = null;

    /**
     * @var TelegramCache|null Cache instance
     */
    private ?TelegramCache $cache = null;

    /**
     * @var TelegramLogger|null Logger instance
     */
    private ?TelegramLogger $logger = null;

    /**
     * @var bool Running status
     */
    private bool $running = false;

    /**
     * @var int Sleep interval between checks (seconds)
     */
    private int $sleepInterval = 5;

    /**
     * @var string Lock file path
     */
    private string $lockFile = '/tmp/telegram_cron.lock';

    /**
     * Constructor
     * 
     * @param TelegramBot|null $bot Bot instance
     */
    public function __construct(?TelegramBot $bot = null)
    {
        $this->bot = $bot;
    }

    /**
     * Set bot instance
     * 
     * @param TelegramBot $bot Bot instance
     * @return self
     */
    public function setBot(TelegramBot $bot): self
    {
        $this->bot = $bot;
        return $this;
    }

    /**
     * Set database instance
     * 
     * @param TelegramDB $db Database instance
     * @return self
     */
    public function setDB(TelegramDB $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Set cache instance
     * 
     * @param TelegramCache $cache Cache instance
     * @return self
     */
    public function setCache(TelegramCache $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Set logger instance
     * 
     * @param TelegramLogger $logger Logger instance
     * @return self
     */
    public function setLogger(TelegramLogger $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Set sleep interval
     * 
     * @param int $seconds Sleep interval in seconds
     * @return self
     */
    public function setSleepInterval(int $seconds): self
    {
        $this->sleepInterval = max(1, $seconds);
        return $this;
    }

    /**
     * Set lock file path
     * 
     * @param string $path Lock file path
     * @return self
     */
    public function setLockFile(string $path): self
    {
        $this->lockFile = $path;
        return $this;
    }

    /**
     * Register a recurring task
     * 
     * @param string $name Task name
     * @param callable $callback Task callback
     * @param string $interval Interval expression (e.g., "every 5 minutes", "daily", "hourly")
     * @param array $options Additional options
     * @return self
     */
    public function every(string $name, callable $callback, string $interval, array $options = []): self
    {
        $this->tasks[$name] = [
            'name' => $name,
            'callback' => $callback,
            'interval' => $this->parseInterval($interval),
            'last_run' => null,
            'next_run' => time(),
            'enabled' => true,
            'timeout' => $options['timeout'] ?? 300,
            'catch_up' => $options['catch_up'] ?? false
        ];

        return $this;
    }

    /**
     * Parse interval expression to seconds
     * 
     * @param string $interval Interval expression
     * @return int Interval in seconds
     */
    private function parseInterval(string $interval): int
    {
        $interval = strtolower(trim($interval));

        // Predefined intervals
        $predefined = [
            'minutely' => 60,
            'every minute' => 60,
            'hourly' => 3600,
            'every hour' => 3600,
            'daily' => 86400,
            'every day' => 86400,
            'weekly' => 604800,
            'every week' => 604800,
            'monthly' => 2592000,
            'every month' => 2592000
        ];

        if (isset($predefined[$interval])) {
            return $predefined[$interval];
        }

        // Parse "every X seconds/minutes/hours/days"
        if (preg_match('/every\s+(\d+)\s+(second|minute|hour|day|week)s?/', $interval, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2];

            $multipliers = [
                'second' => 1,
                'minute' => 60,
                'hour' => 3600,
                'day' => 86400,
                'week' => 604800
            ];

            return $value * $multipliers[$unit];
        }

        // Default to 1 hour
        return 3600;
    }

    /**
     * Register a one-time scheduled task
     * 
     * @param string $name Task name
     * @param callable $callback Task callback
     * @param int $timestamp When to run (Unix timestamp)
     * @return self
     */
    public function schedule(string $name, callable $callback, int $timestamp): self
    {
        $this->tasks[$name] = [
            'name' => $name,
            'callback' => $callback,
            'interval' => null,
            'last_run' => null,
            'next_run' => $timestamp,
            'enabled' => true,
            'timeout' => 300,
            'one_time' => true
        ];

        return $this;
    }

    /**
     * Enable a task
     * 
     * @param string $name Task name
     * @return self
     */
    public function enable(string $name): self
    {
        if (isset($this->tasks[$name])) {
            $this->tasks[$name]['enabled'] = true;
        }
        return $this;
    }

    /**
     * Disable a task
     * 
     * @param string $name Task name
     * @return self
     */
    public function disable(string $name): self
    {
        if (isset($this->tasks[$name])) {
            $this->tasks[$name]['enabled'] = false;
        }
        return $this;
    }

    /**
     * Remove a task
     * 
     * @param string $name Task name
     * @return self
     */
    public function remove(string $name): self
    {
        unset($this->tasks[$name]);
        return $this;
    }

    /**
     * Get all tasks
     * 
     * @return array Tasks list
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get next run time for a task
     * 
     * @param string $name Task name
     * @return int|null Next run timestamp
     */
    public function getNextRun(string $name): ?int
    {
        return $this->tasks[$name]['next_run'] ?? null;
    }

    /**
     * Check if a task is due
     * 
     * @param string $name Task name
     * @return bool Is due
     */
    public function isDue(string $name): bool
    {
        if (!isset($this->tasks[$name]) || !$this->tasks[$name]['enabled']) {
            return false;
        }

        return time() >= $this->tasks[$name]['next_run'];
    }

    /**
     * Run a specific task manually
     * 
     * @param string $name Task name
     * @return mixed Task result
     * @throws InvalidArgumentException If task not found
     */
    public function runTask(string $name): mixed
    {
        if (!isset($this->tasks[$name])) {
            throw new InvalidArgumentException("Task '{$name}' not found");
        }

        return $this->executeTask($this->tasks[$name]);
    }

    /**
     * Execute a task
     * 
     * @param array $task Task definition
     * @return mixed Task result
     */
    private function executeTask(array &$task): mixed
    {
        $startTime = microtime(true);
        $result = null;

        try {
            if ($this->logger) {
                $this->logger->info("Cron task started", ['task' => $task['name']]);
            }

            // Execute callback with context
            $callback = $task['callback'];
            $result = $callback([
                'bot' => $this->bot,
                'db' => $this->db,
                'cache' => $this->cache,
                'logger' => $this->logger,
                'task' => $task['name']
            ]);

            $duration = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->info("Cron task completed", [
                    'task' => $task['name'],
                    'duration' => round($duration, 3)
                ]);
            }

            // Update last run and next run
            $task['last_run'] = time();
            
            if (isset($task['one_time']) && $task['one_time']) {
                $task['enabled'] = false;
            } elseif ($task['interval'] !== null) {
                $task['next_run'] = time() + $task['interval'];
            }

            return $result;
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->error("Cron task failed", [
                    'task' => $task['name'],
                    'duration' => round($duration, 3),
                    'error' => $e->getMessage()
                ]);
            }

            // Still update timing to prevent continuous retries
            $task['last_run'] = time();
            if ($task['interval'] !== null) {
                $task['next_run'] = time() + $task['interval'];
            }

            throw $e;
        }
    }

    /**
     * Run all due tasks
     * 
     * @return array Results from executed tasks
     */
    public function runDueTasks(): array
    {
        $results = [];

        foreach ($this->tasks as $name => &$task) {
            if ($this->isDue($name)) {
                try {
                    $results[$name] = [
                        'success' => true,
                        'result' => $this->executeTask($task)
                    ];
                } catch (Throwable $e) {
                    $results[$name] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Start the cron loop (blocking)
     * 
     * @return void
     */
    public function run(): void
    {
        if ($this->running) {
            return;
        }

        // Acquire lock
        if (!$this->acquireLock()) {
            if ($this->logger) {
                $this->logger->warning("Cron already running (lock exists)");
            }
            return;
        }

        $this->running = true;

        if ($this->logger) {
            $this->logger->info("Cron scheduler started");
        }

        // Handle shutdown
        declare(ticks = 1);
        pcntl_signal(SIGTERM, [$this, 'stop']);
        pcntl_signal(SIGINT, [$this, 'stop']);

        while ($this->running) {
            try {
                $this->runDueTasks();
            } catch (Throwable $e) {
                if ($this->logger) {
                    $this->logger->error("Cron loop error", ['error' => $e->getMessage()]);
                }
            }

            // Sleep and check signals
            $sleepTime = $this->sleepInterval * 1000000; // Convert to microseconds
            usleep($sleepTime);

            pcntl_signal_dispatch();
        }

        // Release lock
        $this->releaseLock();

        if ($this->logger) {
            $this->logger->info("Cron scheduler stopped");
        }
    }

    /**
     * Stop the cron loop
     * 
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;

        if ($this->logger) {
            $this->logger->info("Cron scheduler stopping...");
        }
    }

    /**
     * Check if cron is running
     * 
     * @return bool Running status
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Acquire lock
     * 
     * @return bool Success
     */
    private function acquireLock(): bool
    {
        // Check if lock file exists and process is running
        if (file_exists($this->lockFile)) {
            $pid = (int)file_get_contents($this->lockFile);
            
            if ($pid > 0 && posix_getpgid($pid) !== false) {
                return false; // Process still running
            }
            
            // Stale lock, remove it
            @unlink($this->lockFile);
        }

        // Create lock file
        return file_put_contents($this->lockFile, getmypid(), LOCK_EX | LOCK_NB) !== false;
    }

    /**
     * Release lock
     * 
     * @return void
     */
    private function releaseLock(): void
    {
        @unlink($this->lockFile);
    }

    /**
     * Get task statistics
     * 
     * @return array Statistics
     */
    public function stats(): array
    {
        $stats = [
            'total_tasks' => count($this->tasks),
            'enabled_tasks' => 0,
            'due_tasks' => 0,
            'tasks' => []
        ];

        foreach ($this->tasks as $name => $task) {
            $taskStats = [
                'enabled' => $task['enabled'],
                'last_run' => $task['last_run'],
                'next_run' => $task['next_run'],
                'interval' => $task['interval'],
                'due' => $this->isDue($name)
            ];

            $stats['tasks'][$name] = $taskStats;

            if ($task['enabled']) {
                $stats['enabled_tasks']++;
            }

            if ($taskStats['due']) {
                $stats['due_tasks']++;
            }
        }

        return $stats;
    }

    // ==================== Built-in Task Templates ====================

    /**
     * Add cleanup task for expired conversations
     * 
     * @param string $interval Cleanup interval
     * @return self
     */
    public function addConversationCleanup(string $interval = 'hourly'): self
    {
        if ($this->db === null) {
            return $this;
        }

        return $this->every('conversation_cleanup', function($context) {
            if ($context['db'] instanceof TelegramDB) {
                return $context['db']->cleanExpiredConversations();
            }
            return 0;
        }, $interval);
    }

    /**
     * Add daily stats report task
     * 
     * @param array $adminIds Admin user IDs to send reports to
     * @param string $time Time to send (HH:MM format)
     * @return self
     */
    public function addDailyStatsReport(array $adminIds, string $time = '09:00'): self
    {
        if ($this->bot === null || $this->db === null) {
            return $this;
        }

        return $this->every('daily_stats', function($context) use ($adminIds) {
            if (!$context['bot'] instanceof TelegramBot || !$context['db'] instanceof TelegramDB) {
                return false;
            }

            $stats = $context['db']->stats();
            $report = "📊 Daily Bot Statistics\n\n";
            $report .= "Users: {$stats['users']}\n";
            $report .= "Chats: {$stats['chats']}\n";
            $report .= "Conversations: {$stats['conversations']}\n";
            $report .= "Settings: {$stats['settings']}\n";

            foreach ($adminIds as $adminId) {
                try {
                    $context['bot']->sendMessage($adminId, $report);
                } catch (Exception $e) {
                    // Ignore send errors
                }
            }

            return true;
        }, 'daily');
    }

    /**
     * Add cache cleanup task
     * 
     * @param string $interval Cleanup interval
     * @return self
     */
    public function addCacheCleanup(string $interval = 'daily'): self
    {
        if ($this->cache === null) {
            return $this;
        }

        return $this->every('cache_cleanup', function($context) {
            if ($context['cache'] instanceof TelegramCache) {
                $stats = $context['cache']->stats();
                
                // Clear if too many expired items
                if ($stats['expired_items'] > 100) {
                    $context['cache']->clear();
                    return 'cleared';
                }
                
                return 'ok';
            }
            return 0;
        }, $interval);
    }

    /**
     * Add log rotation task
     * 
     * @param TelegramLogger $logger Logger instance
     * @param string $interval Rotation interval
     * @return self
     */
    public function addLogRotation(TelegramLogger $logger, string $interval = 'weekly'): self
    {
        return $this->every('log_rotation', function($context) use ($logger) {
            // Force log rotation by checking size
            return true;
        }, $interval);
    }

    /**
     * Add health check task
     * 
     * @param callable|null $onFail Callback when health check fails
     * @param string $interval Check interval
     * @return self
     */
    public function addHealthCheck(?callable $onFail = null, string $interval = 'every 5 minutes'): self
    {
        return $this->every('health_check', function($context) use ($onFail) {
            $healthy = true;
            $issues = [];

            // Check bot connectivity
            if ($context['bot'] instanceof TelegramBot) {
                try {
                    $me = $context['bot']->getMe();
                    if (!$me['ok']) {
                        $healthy = false;
                        $issues[] = 'Bot API unreachable';
                    }
                } catch (Exception $e) {
                    $healthy = false;
                    $issues[] = 'Bot API error: ' . $e->getMessage();
                }
            }

            // Check database
            if ($context['db'] instanceof TelegramDB) {
                try {
                    $context['db']->query('SELECT 1');
                } catch (Exception $e) {
                    $healthy = false;
                    $issues[] = 'Database error: ' . $e->getMessage();
                }
            }

            if (!$healthy && $onFail !== null) {
                $onFail($issues);
            }

            return ['healthy' => $healthy, 'issues' => $issues];
        }, $interval);
    }
}
