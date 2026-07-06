<?php

namespace TelegramBot\Core;

/**
 * Configuration Manager
 * 
 * Centralized configuration management for the Telegram Bot Framework.
 * 
 * @package TelegramBot\Core
 * @version 2.0.0
 */
class Config
{
    /**
     * @var array Configuration data
     */
    private static array $config = [];

    /**
     * @var bool Whether config has been loaded
     */
    private static bool $loaded = false;

    /**
     * Load configuration from file
     * 
     * @param string $path Configuration file path
     * @return void
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }

        self::$config = require $path;
        self::$loaded = true;
    }

    /**
     * Get configuration value
     * 
     * @param string $key Dot-notation key (e.g., 'database.host')
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../../config/config.php');
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set configuration value
     * 
     * @param string $key Dot-notation key
     * @param mixed $value Value to set
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../../config/config.php');
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config[array_shift($keys)] = $value;
    }

    /**
     * Get all configuration
     * 
     * @return array All configuration
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../../config/config.php');
        }

        return self::$config;
    }

    /**
     * Check if configuration key exists
     * 
     * @param string $key Key to check
     * @return bool True if exists
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }
}
