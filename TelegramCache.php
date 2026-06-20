<?php

/**
 * Telegram Cache Handler
 * 
 * A simple file-based caching system for storing temporary data like
 * conversation states, user data, and rate limiting information.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 */
class TelegramCache
{
    /**
     * @var string Directory path for cache files
     */
    private string $cacheDir;

    /**
     * @var int Default TTL in seconds (1 hour)
     */
    private int $defaultTTL = 3600;

    /**
     * Constructor
     * 
     * @param string $cacheDir Directory to store cache files
     * @throws RuntimeException If cache directory cannot be created
     */
    public function __construct(string $cacheDir = '/tmp/telegram_cache')
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new RuntimeException("Failed to create cache directory: {$this->cacheDir}");
            }
        }
        
        if (!is_writable($this->cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: {$this->cacheDir}");
        }
    }

    /**
     * Set default TTL
     * 
     * @param int $seconds Default TTL in seconds
     * @return self
     */
    public function setDefaultTTL(int $seconds): self
    {
        $this->defaultTTL = max(0, $seconds);
        return $this;
    }

    /**
     * Get cache file path for a key
     * 
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . DIRECTORY_SEPARATOR . substr($hash, 0, 2) . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    /**
     * Ensure subdirectory exists
     * 
     * @param string $key Cache key
     * @return void
     */
    private function ensureSubdir(string $key): void
    {
        $hash = md5($key);
        $subdir = $this->cacheDir . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        
        if (!is_dir($subdir)) {
            mkdir($subdir, 0755, true);
        }
    }

    /**
     * Store data in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Data to store (will be serialized)
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $this->ensureSubdir($key);
            
            $filePath = $this->getFilePath($key);
            $ttl = $ttl ?? $this->defaultTTL;
            $expiresAt = $ttl > 0 ? time() + $ttl : null;
            
            $data = [
                'value' => $value,
                'expires_at' => $expiresAt,
                'created_at' => time()
            ];
            
            $serialized = serialize($data);
            
            // Atomic write using temp file
            $tempFile = $filePath . '.tmp.' . getmypid();
            if (file_put_contents($tempFile, $serialized, LOCK_EX) === false) {
                return false;
            }
            
            if (!rename($tempFile, $filePath)) {
                unlink($tempFile);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retrieve data from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist or is expired
     * @return mixed Cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $filePath = $this->getFilePath($key);
            
            if (!file_exists($filePath)) {
                return $default;
            }
            
            $serialized = file_get_contents($filePath);
            if ($serialized === false) {
                return $default;
            }
            
            $data = @unserialize($serialized);
            if ($data === false && $serialized !== 'b:0;') {
                // Corrupted cache, remove it
                @unlink($filePath);
                return $default;
            }
            
            // Check expiration
            if ($data['expires_at'] !== null && time() > $data['expires_at']) {
                @unlink($filePath);
                return $default;
            }
            
            return $data['value'];
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Check if key exists and is not expired
     * 
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public function has(string $key): bool
    {
        try {
            $filePath = $this->getFilePath($key);
            
            if (!file_exists($filePath)) {
                return false;
            }
            
            $serialized = file_get_contents($filePath);
            if ($serialized === false) {
                return false;
            }
            
            $data = @unserialize($serialized);
            if ($data === false && $serialized !== 'b:0;') {
                @unlink($filePath);
                return false;
            }
            
            if ($data['expires_at'] !== null && time() > $data['expires_at']) {
                @unlink($filePath);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Delete item from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        try {
            $filePath = $this->getFilePath($key);
            
            if (file_exists($filePath)) {
                return @unlink($filePath);
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clear all cache
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    @unlink($file->getPathname());
                }
            }
            
            // Remove subdirectories
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $dir) {
                if ($dir->isDir()) {
                    @rmdir($dir->getPathname());
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics including total items, size, etc.
     */
    public function stats(): array
    {
        $stats = [
            'total_items' => 0,
            'total_size' => 0,
            'expired_items' => 0,
            'valid_items' => 0
        ];
        
        try {
            if (!is_dir($this->cacheDir)) {
                return $stats;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                    $stats['total_items']++;
                    $stats['total_size'] += $file->getSize();
                    
                    $serialized = file_get_contents($file->getPathname());
                    $data = @unserialize($serialized);
                    
                    if ($data !== false || $serialized === 'b:0;') {
                        if ($data['expires_at'] !== null && time() > $data['expires_at']) {
                            $stats['expired_items']++;
                        } else {
                            $stats['valid_items']++;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }
        
        return $stats;
    }

    /**
     * Increment a numeric value
     * 
     * @param string $key Cache key
     * @param int $step Step value (default 1)
     * @param int|null $ttl TTL for new keys
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $step = 1, ?int $ttl = null): int|false
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }
        
        $newValue = (int)$current + $step;
        
        if ($this->set($key, $newValue, $ttl)) {
            return $newValue;
        }
        
        return false;
    }

    /**
     * Decrement a numeric value
     * 
     * @param string $key Cache key
     * @param int $step Step value (default 1)
     * @param int|null $ttl TTL for new keys
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $step = 1, ?int $ttl = null): int|false
    {
        return $this->increment($key, -$step, $ttl);
    }

    /**
     * Get or set with callback
     * 
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int|null $ttl TTL
     * @return mixed Cached or generated value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}
