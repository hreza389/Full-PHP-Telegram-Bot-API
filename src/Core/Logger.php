<?php

namespace TelegramBot\Core;

/**
 * Logger - Comprehensive logging system for Telegram bots
 * 
 * Supports file and database logging with log rotation,
 * sensitive data masking, and multiple log levels.
 * 
 * @package TelegramBot\Core
 * @version 2.0.0
 */
class Logger
{
    /** Log level constants */
    public const LEVEL_DEBUG = 0;
    public const LEVEL_INFO = 1;
    public const LEVEL_NOTICE = 2;
    public const LEVEL_WARNING = 3;
    public const LEVEL_ERROR = 4;
    public const LEVEL_CRITICAL = 5;
    public const LEVEL_ALERT = 6;
    public const LEVEL_EMERGENCY = 7;

    /**
     * @var string Log file path
     */
    private string $logFile;

    /**
     * @var int Minimum log level to record
     */
    private int $minLevel;

    /**
     * @var bool Enable/disable logging
     */
    private bool $enabled = true;

    /**
     * @var Database|null Database instance for DB logging
     */
    private ?Database\Database $db = null;

    /**
     * @var array Sensitive keys to mask in logs
     */
    private array $sensitiveKeys = [
        'token', 'password', 'secret', 'api_key', 'apikey',
        'auth', 'credential', 'private_key', 'access_token'
    ];

    /**
     * @var int Maximum log file size in bytes before rotation (10MB default)
     */
    private int $maxFileSize = 10485760;

    /**
     * @var int Number of rotated log files to keep
     */
    private int $maxFiles = 5;

    /**
     * Constructor
     * 
     * @param string|null $logFile Path to log file (null = use config)
     * @param int $minLevel Minimum log level to record
     */
    public function __construct(?string $logFile = null, int $minLevel = self::LEVEL_INFO)
    {
        if ($logFile === null) {
            $logPath = Config::get('logging.path');
            $logFile = rtrim($logPath, '/') . '/bot_' . date('Y-m-d') . '.log';
        }

        $this->logFile = $logFile;
        $this->minLevel = $minLevel;

        // Ensure directory exists
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create log directory: {$dir}");
            }
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFile)) {
            if (!touch($logFile)) {
                throw new \RuntimeException("Failed to create log file: {$logFile}");
            }
        }
    }

    /**
     * Set database instance for dual logging
     * 
     * @param Database\Database $db Database instance
     * @return self
     */
    public function setDatabase(Database\Database $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Set minimum log level
     * 
     * @param int $level Minimum level
     * @return self
     */
    public function setMinLevel(int $level): self
    {
        $this->minLevel = max(0, min(7, $level));
        return $this;
    }

    /**
     * Enable logging
     * 
     * @return self
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disable logging
     * 
     * @return self
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Get level name
     * 
     * @param int $level Log level
     * @return string Level name
     */
    private function getLevelName(int $level): string
    {
        $names = [
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_NOTICE => 'NOTICE',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL',
            self::LEVEL_ALERT => 'ALERT',
            self::LEVEL_EMERGENCY => 'EMERGENCY'
        ];

        return $names[$level] ?? 'UNKNOWN';
    }

    /**
     * Mask sensitive data in array
     * 
     * @param mixed $data Data to mask
     * @return mixed Masked data
     */
    private function maskSensitiveData(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                foreach ($this->sensitiveKeys as $sensitive) {
                    if (stripos((string)$key, $sensitive) !== false) {
                        $data[$key] = '***MASKED***';
                    }
                }

                if (is_array($value)) {
                    $data[$key] = $this->maskSensitiveData($value);
                }
            }
        }

        return $data;
    }

    /**
     * Rotate log files if needed
     * 
     * @return void
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $size = filesize($this->logFile);
        if ($size < $this->maxFileSize) {
            return;
        }

        // Rotate files
        for ($i = $this->maxFiles - 1; $i >= 0; $i--) {
            $oldFile = $i === 0 ? $this->logFile : $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);

            if (file_exists($oldFile)) {
                if ($i === $this->maxFiles - 1) {
                    @unlink($oldFile);
                } else {
                    @rename($oldFile, $newFile);
                }
            }
        }

        // Create new empty log file
        @touch($this->logFile);
    }

    /**
     * Write log entry
     * 
     * @param int $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string|null $category Category
     * @return bool Success status
     */
    private function write(int $level, string $message, array $context = [], ?string $category = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($level < $this->minLevel) {
            return false;
        }

        try {
            // Rotate if needed
            $this->rotateIfNeeded();

            // Format timestamp
            $timestamp = date('Y-m-d H:i:s');

            // Mask sensitive data
            $maskedContext = $this->maskSensitiveData($context);

            // Build log line
            $logLine = sprintf(
                "[%s] [%s] %s",
                $timestamp,
                $this->getLevelName($level),
                $message
            );

            // Add context if present
            if (!empty($maskedContext)) {
                $logLine .= ' ' . json_encode($maskedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $logLine .= PHP_EOL;

            // Write to file
            $fileSuccess = file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX) !== false;

            // Also write to database if available
            $dbSuccess = true;
            if ($this->db && Config::get('logging.channels') && in_array('database', Config::get('logging.channels'))) {
                $this->db->addLog(
                    $this->getLevelName($level),
                    $message,
                    $context,
                    $context['user_id'] ?? null,
                    $context['chat_id'] ?? null,
                    $category
                );
            }

            return $fileSuccess && $dbSuccess;
        } catch (\Exception $e) {
            error_log("Logger failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log DEBUG message
     */
    public function debug(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_DEBUG, $message, $context, $category);
    }

    /**
     * Log INFO message
     */
    public function info(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_INFO, $message, $context, $category);
    }

    /**
     * Log NOTICE message
     */
    public function notice(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_NOTICE, $message, $context, $category);
    }

    /**
     * Log WARNING message
     */
    public function warning(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_WARNING, $message, $context, $category);
    }

    /**
     * Log ERROR message
     */
    public function error(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_ERROR, $message, $context, $category);
    }

    /**
     * Log CRITICAL message
     */
    public function critical(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_CRITICAL, $message, $context, $category);
    }

    /**
     * Log ALERT message
     */
    public function alert(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_ALERT, $message, $context, $category);
    }

    /**
     * Log EMERGENCY message
     */
    public function emergency(string $message, array $context = [], ?string $category = null): bool
    {
        return $this->write(self::LEVEL_EMERGENCY, $message, $context, $category);
    }

    /**
     * Log exception
     * 
     * @param \Throwable $exception Exception to log
     * @param string $message Optional message
     * @return bool Success
     */
    public function logException(\Throwable $exception, string $message = ''): bool
    {
        $context = [
            'exception' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $fullMessage = $message ?: $exception->getMessage();

        return $this->error($fullMessage, $context, 'exception');
    }

    /**
     * Log incoming update
     */
    public function logUpdate(array $update): bool
    {
        return $this->debug('Incoming update', ['update' => $update], 'telegram');
    }

    /**
     * Log outgoing request
     */
    public function logRequest(string $method, array $params): bool
    {
        return $this->debug('Outgoing request', [
            'method' => $method,
            'params' => $params
        ], 'telegram');
    }

    /**
     * Log API response
     */
    public function logResponse(bool $success, array $response): bool
    {
        $level = $success ? self::LEVEL_DEBUG : self::LEVEL_ERROR;
        return $this->write($level, 'API response', [
            'success' => $success,
            'response' => $response
        ], 'telegram');
    }

    /**
     * Get recent log entries from file
     * 
     * @param int $lines Number of lines to retrieve
     * @return array Log lines
     */
    public function tail(int $lines = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $file = new \SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $lines);
        $result = [];

        $file->seek($start);
        while (!$file->eof()) {
            $line = $file->current();
            if ($line !== null && trim($line) !== '') {
                $result[] = trim($line);
            }
            $file->next();
        }

        return $result;
    }

    /**
     * Clear log file
     * 
     * @return bool Success
     */
    public function clear(): bool
    {
        return @file_put_contents($this->logFile, '') !== false;
    }
}
