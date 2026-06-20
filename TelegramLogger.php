<?php

/**
 * Telegram Logger
 * 
 * A comprehensive logging system for Telegram bots with support for
 * different log levels, file rotation, and sensitive data masking.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 */
class TelegramLogger
{
    /**
     * Log level constants
     */
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
     * @param string $logFile Path to log file
     * @param int $minLevel Minimum log level to record
     * @throws RuntimeException If log file cannot be created
     */
    public function __construct(
        string $logFile = '/tmp/telegram_bot.log',
        int $minLevel = self::LEVEL_INFO
    ) {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;

        // Ensure directory exists
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create log directory: {$dir}");
            }
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFile)) {
            if (!touch($logFile)) {
                throw new RuntimeException("Failed to create log file: {$logFile}");
            }
        }

        if (!is_writable($logFile)) {
            throw new RuntimeException("Log file is not writable: {$logFile}");
        }
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
     * Add sensitive key pattern for masking
     * 
     * @param string $key Key pattern to mask
     * @return self
     */
    public function addSensitiveKey(string $key): self
    {
        if (!in_array($key, $this->sensitiveKeys)) {
            $this->sensitiveKeys[] = $key;
        }
        return $this;
    }

    /**
     * Set maximum log file size
     * 
     * @param int $bytes Max size in bytes
     * @return self
     */
    public function setMaxFileSize(int $bytes): self
    {
        $this->maxFileSize = max(1024, $bytes);
        return $this;
    }

    /**
     * Set number of rotated files to keep
     * 
     * @param int $count Number of files
     * @return self
     */
    public function setMaxFiles(int $count): self
    {
        $this->maxFiles = max(1, min(100, $count));
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
     * @return bool Success status
     */
    private function write(int $level, string $message, array $context = []): bool
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
            return file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX) !== false;
        } catch (Exception $e) {
            error_log("TelegramLogger failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log DEBUG message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function debug(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log INFO message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function info(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log NOTICE message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function notice(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_NOTICE, $message, $context);
    }

    /**
     * Log WARNING message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function warning(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log ERROR message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function error(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log CRITICAL message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function critical(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log ALERT message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function alert(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_ALERT, $message, $context);
    }

    /**
     * Log EMERGENCY message
     * 
     * @param string $message Message
     * @param array $context Context
     * @return bool Success
     */
    public function emergency(string $message, array $context = []): bool
    {
        return $this->write(self::LEVEL_EMERGENCY, $message, $context);
    }

    /**
     * Log exception
     * 
     * @param Throwable $exception Exception to log
     * @param string $message Optional message
     * @return bool Success
     */
    public function logException(Throwable $exception, string $message = ''): bool
    {
        $context = [
            'exception' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $fullMessage = $message ?: $exception->getMessage();

        return $this->error($fullMessage, $context);
    }

    /**
     * Log incoming update
     * 
     * @param array $update Update data
     * @return bool Success
     */
    public function logUpdate(array $update): bool
    {
        return $this->debug('Incoming update', ['update' => $update]);
    }

    /**
     * Log outgoing request
     * 
     * @param string $method API method
     * @param array $params Request parameters
     * @return bool Success
     */
    public function logRequest(string $method, array $params): bool
    {
        return $this->debug('Outgoing request', [
            'method' => $method,
            'params' => $params
        ]);
    }

    /**
     * Log API response
     * 
     * @param bool $success Success status
     * @param array $response Response data
     * @return bool Success
     */
    public function logResponse(bool $success, array $response): bool
    {
        $level = $success ? self::LEVEL_DEBUG : self::LEVEL_ERROR;
        return $this->write($level, 'API response', [
            'success' => $success,
            'response' => $response
        ]);
    }

    /**
     * Clear log file
     * 
     * @return bool Success
     */
    public function clear(): bool
    {
        try {
            return @truncate($this->logFile) !== false;
        } catch (Exception $e) {
            return @file_put_contents($this->logFile, '') !== false;
        }
    }

    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to retrieve
     * @return array Log lines
     */
    public function tail(int $lines = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $file = new SplFileObject($this->logFile);
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
}
