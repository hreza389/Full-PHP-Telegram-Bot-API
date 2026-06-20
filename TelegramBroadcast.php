<?php

/**
 * Telegram Broadcast System
 * 
 * Handles mass messaging with batch processing, progress tracking,
 * error logging, and pause/resume capabilities.
 */
class TelegramBroadcast {
    private TelegramBot $bot;
    private TelegramDB $db;
    private string $tableName = 'broadcast_jobs';
    private int $batchSize = 30; // Telegram API limit is ~30 msgs/sec
    private int $delayBetweenBatches = 1000; // ms

    public function __construct(TelegramBot $bot, TelegramDB $db) {
        $this->bot = $bot;
        $this->db = $db;
        $this->initTable();
    }

    /**
     * Initialize the broadcast jobs table
     */
    private function initTable(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id TEXT UNIQUE NOT NULL,
                message_text TEXT,
                parse_mode TEXT DEFAULT 'HTML',
                recipients TEXT NOT NULL,
                total_count INTEGER DEFAULT 0,
                processed_count INTEGER DEFAULT 0,
                success_count INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME,
                completed_at DATETIME,
                last_error TEXT,
                options TEXT
            )
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS broadcast_results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id TEXT NOT NULL,
                chat_id TEXT NOT NULL,
                status TEXT NOT NULL,
                message_id INTEGER,
                error_message TEXT,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Create a new broadcast job
     * 
     * @param array $chatIds List of chat IDs to send to
     * @param string $text Message text
     * @param string $parseMode Parse mode (HTML, Markdown, etc.)
     * @param array $options Additional options (reply_markup, etc.)
     * @return string Job ID
     */
    public function createJob(array $chatIds, string $text, string $parseMode = 'HTML', array $options = []): string {
        $jobId = uniqid('broadcast_');
        $recipients = json_encode($chatIds);
        $totalCount = count($chatIds);
        $optionsJson = json_encode($options);

        $this->db->query(
            "INSERT INTO {$this->tableName} 
            (job_id, message_text, parse_mode, recipients, total_count, status, options) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?)",
            [$jobId, $text, $parseMode, $recipients, $totalCount, $optionsJson]
        );

        return $jobId;
    }

    /**
     * Execute a broadcast job
     * 
     * @param string $jobId Job ID to execute
     * @param bool $resume Whether to resume from last position
     * @return array Execution statistics
     */
    public function executeJob(string $jobId, bool $resume = false): array {
        $job = $this->getJob($jobId);
        if (!$job) {
            throw new Exception("Job not found: {$jobId}");
        }

        if ($job['status'] === 'completed' && !$resume) {
            throw new Exception("Job already completed");
        }

        // Update status
        if ($job['status'] !== 'running') {
            $this->updateJobStatus($jobId, 'running', null, null, date('Y-m-d H:i:s'));
        }

        $recipients = json_decode($job['recipients'], true);
        $processedCount = $job['processed_count'];
        $successCount = $job['success_count'];
        $errorCount = $job['error_count'];

        // Get already processed chats if resuming
        $processedChats = [];
        if ($resume) {
            $results = $this->db->query(
                "SELECT chat_id FROM broadcast_results WHERE job_id = ?",
                [$jobId],
                true
            );
            $processedChats = array_column($results, 'chat_id');
        }

        $batch = [];
        $batchStartTime = microtime(true);

        foreach ($recipients as $index => $chatId) {
            // Skip already processed chats when resuming
            if ($resume && in_array($chatId, $processedChats)) {
                continue;
            }

            $batch[] = $chatId;

            // Process batch when full
            if (count($batch) >= $this->batchSize) {
                $stats = $this->processBatch($jobId, $batch, $job);
                $successCount += $stats['success'];
                $errorCount += $stats['error'];
                $processedCount += count($batch);
                
                $this->updateJobProgress($jobId, $processedCount, $successCount, $errorCount);
                
                $batch = [];
                
                // Delay between batches
                $elapsed = (microtime(true) - $batchStartTime) * 1000;
                if ($elapsed < $this->delayBetweenBatches) {
                    usleep(($this->delayBetweenBatches - $elapsed) * 1000);
                }
                $batchStartTime = microtime(true);
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $stats = $this->processBatch($jobId, $batch, $job);
            $successCount += $stats['success'];
            $errorCount += $stats['error'];
            $processedCount += count($batch);
        }

        // Complete job
        $this->updateJobStatus($jobId, 'completed', $processedCount, $successCount, $errorCount, date('Y-m-d H:i:s'));

        return [
            'job_id' => $jobId,
            'total' => count($recipients),
            'processed' => $processedCount,
            'success' => $successCount,
            'error' => $errorCount,
            'status' => 'completed'
        ];
    }

    /**
     * Process a batch of messages
     */
    private function processBatch(string $jobId, array $chatIds, array $job): array {
        $success = 0;
        $error = 0;

        foreach ($chatIds as $chatId) {
            try {
                $options = json_decode($job['options'], true) ?: [];
                
                $result = $this->bot->sendMessage(
                    $chatId,
                    $job['message_text'],
                    $job['parse_mode'],
                    null,
                    null,
                    null,
                    null,
                    null,
                    $options['reply_markup'] ?? null,
                    $options['disable_web_page_preview'] ?? null,
                    $options['disable_notification'] ?? null,
                    $options['protect_content'] ?? null
                );

                if ($result && isset($result['message_id'])) {
                    $this->logResult($jobId, $chatId, 'success', $result['message_id']);
                    $success++;
                } else {
                    throw new Exception('No message_id in response');
                }
            } catch (Exception $e) {
                $this->logResult($jobId, $chatId, 'error', null, $e->getMessage());
                $error++;
                
                // Update last error
                $this->db->query(
                    "UPDATE {$this->tableName} SET last_error = ? WHERE job_id = ?",
                    [$e->getMessage(), $jobId]
                );
            }
        }

        return ['success' => $success, 'error' => $error];
    }

    /**
     * Log broadcast result
     */
    private function logResult(string $jobId, string $chatId, string $status, ?int $messageId, ?string $error = null): void {
        $this->db->query(
            "INSERT INTO broadcast_results (job_id, chat_id, status, message_id, error_message) 
            VALUES (?, ?, ?, ?, ?)",
            [$jobId, $chatId, $status, $messageId, $error]
        );
    }

    /**
     * Get job details
     */
    public function getJob(string $jobId): ?array {
        $result = $this->db->query(
            "SELECT * FROM {$this->tableName} WHERE job_id = ?",
            [$jobId],
            true
        );
        return $result[0] ?? null;
    }

    /**
     * Get all jobs
     */
    public function getAllJobs(int $limit = 50, int $offset = 0): array {
        return $this->db->query(
            "SELECT * FROM {$this->tableName} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset],
            true
        );
    }

    /**
     * Update job status
     */
    private function updateJobStatus(string $jobId, string $status, ?int $processed = null, ?int $success = null, ?string $startedAt = null, ?string $completedAt = null): void {
        $updates = ['status' => $status];
        if ($processed !== null) $updates['processed_count'] = $processed;
        if ($success !== null) $updates['success_count'] = $success;
        if ($startedAt !== null) $updates['started_at'] = $startedAt;
        if ($completedAt !== null) $updates['completed_at'] = $completedAt;

        $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updates)));
        $values = array_values($updates);
        $values[] = $jobId;

        $this->db->query(
            "UPDATE {$this->tableName} SET {$setClause} WHERE job_id = ?",
            $values
        );
    }

    /**
     * Update job progress
     */
    private function updateJobProgress(string $jobId, int $processed, int $success, int $error): void {
        $this->db->query(
            "UPDATE {$this->tableName} 
            SET processed_count = ?, success_count = ?, error_count = ? 
            WHERE job_id = ?",
            [$processed, $success, $error, $jobId]
        );
    }

    /**
     * Cancel a job
     */
    public function cancelJob(string $jobId): bool {
        $job = $this->getJob($jobId);
        if (!$job || $job['status'] === 'completed') {
            return false;
        }

        $this->updateJobStatus($jobId, 'cancelled');
        return true;
    }

    /**
     * Get job statistics
     */
    public function getJobStats(string $jobId): array {
        $job = $this->getJob($jobId);
        if (!$job) {
            return [];
        }

        $progress = $job['total_count'] > 0 
            ? round(($job['processed_count'] / $job['total_count']) * 100, 2) 
            : 0;

        return [
            'job_id' => $jobId,
            'status' => $job['status'],
            'total' => $job['total_count'],
            'processed' => $job['processed_count'],
            'success' => $job['success_count'],
            'error' => $job['error_count'],
            'progress_percent' => $progress,
            'created_at' => $job['created_at'],
            'started_at' => $job['started_at'],
            'completed_at' => $job['completed_at'],
            'last_error' => $job['last_error']
        ];
    }

    /**
     * Get failed recipients for a job
     */
    public function getFailedRecipients(string $jobId): array {
        return $this->db->query(
            "SELECT chat_id, error_message FROM broadcast_results 
            WHERE job_id = ? AND status = 'error'",
            [$jobId],
            true
        );
    }

    /**
     * Retry failed messages
     */
    public function retryFailed(string $jobId): string {
        $failed = $this->getFailedRecipients($jobId);
        if (empty($failed)) {
            throw new Exception("No failed messages to retry");
        }

        $chatIds = array_column($failed, 'chat_id');
        $originalJob = $this->getJob($jobId);
        
        return $this->createJob(
            $chatIds,
            $originalJob['message_text'],
            $originalJob['parse_mode'],
            json_decode($originalJob['options'], true) ?: []
        );
    }

    /**
     * Delete old jobs
     */
    public function cleanupOldJobs(int $days = 30): int {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Delete results first
        $this->db->query(
            "DELETE FROM broadcast_results WHERE job_id IN 
            (SELECT job_id FROM {$this->tableName} WHERE created_at < ?)",
            [$cutoff]
        );
        
        // Delete jobs
        $this->db->query(
            "DELETE FROM {$this->tableName} WHERE created_at < ?",
            [$cutoff]
        );

        return $this->db->lastInsertRowId();
    }
}
