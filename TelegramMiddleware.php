<?php

/**
 * Telegram Middleware Pipeline
 * 
 * A powerful middleware system for processing updates through a chain
 * of handlers before they reach your bot logic.
 * 
 * @package TelegramBot
 * @author Telegram Bot Framework
 * @version 1.0.0
 */
class TelegramMiddleware
{
    /**
     * @var array Middleware stack
     */
    private array $middleware = [];

    /**
     * @var TelegramBot|null Bot instance
     */
    private ?TelegramBot $bot = null;

    /**
     * @var TelegramCache|null Cache instance
     */
    private ?TelegramCache $cache = null;

    /**
     * @var TelegramLogger|null Logger instance
     */
    private ?TelegramLogger $logger = null;

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
     * Add middleware to the stack
     * 
     * @param callable|array|string $middleware Middleware (callable, class name, or object)
     * @return self
     */
    public function use(callable|array|string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Add multiple middleware at once
     * 
     * @param array $middlewareList List of middleware
     * @return self
     */
    public function merge(array $middlewareList): self
    {
        $this->middleware = array_merge($this->middleware, $middlewareList);
        return $this;
    }

    /**
     * Prepend middleware to the stack
     * 
     * @param callable|array|string $middleware Middleware
     * @return self
     */
    public function prepend(callable|array|string $middleware): self
    {
        array_unshift($this->middleware, $middleware);
        return $this;
    }

    /**
     * Clear all middleware
     * 
     * @return self
     */
    public function clear(): self
    {
        $this->middleware = [];
        return $this;
    }

    /**
     * Get middleware count
     * 
     * @return int Number of middleware
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Process an update through the middleware stack
     * 
     * @param array $update Update data
     * @param callable $finalHandler Final handler to execute after all middleware
     * @return mixed Result from final handler or false if stopped
     */
    public function handle(array $update, callable $finalHandler): mixed
    {
        $stack = $this->middleware;
        $index = 0;

        $next = function() use (&$stack, &$index, &$next, $update, $finalHandler) {
            if ($index >= count($stack)) {
                // All middleware processed, call final handler
                return $finalHandler($update);
            }

            $middleware = $stack[$index];
            $index++;

            return $this->executeMiddleware($middleware, $update, $next);
        };

        return $next();
    }

    /**
     * Execute a single middleware
     * 
     * @param callable|array|string $middleware Middleware
     * @param array $update Update data
     * @param callable $next Next middleware
     * @return mixed Result
     */
    private function executeMiddleware(callable|array|string $middleware, array $update, callable $next): mixed
    {
        // Resolve middleware
        $handler = $this->resolveMiddleware($middleware);

        if ($handler === null) {
            // Invalid middleware, skip it
            return $next();
        }

        // Execute handler
        return $handler($update, $next);
    }

    /**
     * Resolve middleware to callable
     * 
     * @param callable|array|string $middleware Middleware
     * @return callable|null Resolved handler or null
     */
    private function resolveMiddleware(callable|array|string $middleware): ?callable
    {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }
        }

        if (is_array($middleware)) {
            if (isset($middleware[0]) && isset($middleware[1])) {
                if (is_object($middleware[0]) && method_exists($middleware[0], $middleware[1])) {
                    return $middleware;
                }
                if (is_string($middleware[0]) && class_exists($middleware[0])) {
                    $instance = new $middleware[0]();
                    if (method_exists($instance, $middleware[1])) {
                        return [$instance, $middleware[1]];
                    }
                }
            }
        }

        return null;
    }

    // ==================== Built-in Middleware ====================

    /**
     * Logging middleware - logs all updates
     * 
     * @return callable Middleware closure
     */
    public static function logging(): callable
    {
        return function(array $update, callable $next) {
            global $telegramLogger;
            
            if (isset($telegramLogger) && $telegramLogger instanceof TelegramLogger) {
                $telegramLogger->logUpdate($update);
            }
            
            return $next();
        };
    }

    /**
     * Error handling middleware - catches exceptions
     * 
     * @param callable|null $errorHandler Custom error handler
     * @return callable Middleware closure
     */
    public static function errorHandler(?callable $errorHandler = null): callable
    {
        return function(array $update, callable $next) use ($errorHandler) {
            try {
                return $next();
            } catch (Throwable $e) {
                global $telegramLogger;
                
                if (isset($telegramLogger) && $telegramLogger instanceof TelegramLogger) {
                    $telegramLogger->logException($e, 'Middleware error');
                }
                
                if ($errorHandler !== null) {
                    return $errorHandler($e, $update);
                }
                
                // Default: rethrow
                throw $e;
            }
        };
    }

    /**
     * Rate limiting middleware - limits requests per user
     * 
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @param callable|null $onLimit Handler when limit is reached
     * @return callable Middleware closure
     */
    public static function rateLimit(
        int $maxRequests = 10,
        int $timeWindow = 60,
        ?callable $onLimit = null
    ): callable {
        return function(array $update, callable $next) use ($maxRequests, $timeWindow, $onLimit) {
            global $telegramCache;
            
            // Get user ID
            $userId = $update['message']['from']['id'] 
                    ?? $update['callback_query']['from']['id'] 
                    ?? $update['inline_query']['from']['id'] 
                    ?? null;
            
            if ($userId === null) {
                return $next();
            }
            
            $cacheKey = "rate_limit:user:{$userId}";
            
            if (!isset($telegramCache) || !$telegramCache instanceof TelegramCache) {
                // No cache available, skip rate limiting
                return $next();
            }
            
            $data = $telegramCache->get($cacheKey, ['count' => 0, 'reset_at' => time() + $timeWindow]);
            
            if (time() > $data['reset_at']) {
                // Reset window
                $data = ['count' => 1, 'reset_at' => time() + $timeWindow];
                $telegramCache->set($cacheKey, $data, $timeWindow);
                return $next();
            }
            
            if ($data['count'] >= $maxRequests) {
                // Limit reached
                if ($onLimit !== null) {
                    return $onLimit($update, $data);
                }
                
                // Default: skip processing
                return false;
            }
            
            // Increment counter
            $data['count']++;
            $telegramCache->set($cacheKey, $data, $timeWindow);
            
            return $next();
        };
    }

    /**
     * Admin check middleware - only allows admin users
     * 
     * @param array $adminIds List of admin user IDs
     * @param callable|null $onDenied Handler when access denied
     * @return callable Middleware closure
     */
    public static function adminCheck(array $adminIds, ?callable $onDenied = null): callable
    {
        return function(array $update, callable $next) use ($adminIds, $onDenied) {
            $userId = $update['message']['from']['id'] 
                    ?? $update['callback_query']['from']['id'] 
                    ?? $update['inline_query']['from']['id'] 
                    ?? null;
            
            if ($userId === null || !in_array($userId, $adminIds)) {
                if ($onDenied !== null) {
                    return $onDenied($update);
                }
                
                // Default: skip processing
                return false;
            }
            
            return $next();
        };
    }

    /**
     * Chat type filter middleware - only allows specific chat types
     * 
     * @param array $allowedTypes Allowed chat types ('private', 'group', 'supergroup', 'channel')
     * @return callable Middleware closure
     */
    public static function chatTypeFilter(array $allowedTypes): callable
    {
        return function(array $update, callable $next) use ($allowedTypes) {
            $chatType = $update['message']['chat']['type'] 
                      ?? $update['channel_post']['chat']['type'] 
                      ?? null;
            
            if ($chatType === null || !in_array($chatType, $allowedTypes)) {
                return false;
            }
            
            return $next();
        };
    }

    /**
     * Typing indicator middleware - shows typing status while processing
     * 
     * @param int $duration Duration to show typing (seconds)
     * @return callable Middleware closure
     */
    public static function typingIndicator(int $duration = 3): callable
    {
        return function(array $update, callable $next) use ($duration) {
            global $telegramBot;
            
            $chatId = $update['message']['chat']['id'] 
                    ?? $update['callback_query']['message']['chat']['id'] 
                    ?? null;
            
            if ($chatId !== null && isset($telegramBot) && $telegramBot instanceof TelegramBot) {
                // Send typing action in background
                try {
                    $telegramBot->sendChatAction($chatId, 'typing');
                    
                    // Simulate processing time (optional)
                    // usleep($duration * 1000000);
                } catch (Exception $e) {
                    // Ignore errors
                }
            }
            
            return $next();
        };
    }

    /**
     * Spam protection middleware - basic spam detection
     * 
     * @param int $floodTimeout Minimum time between messages (seconds)
     * @param callable|null $onSpam Handler when spam detected
     * @return callable Middleware closure
     */
    public static function spamProtection(
        int $floodTimeout = 2,
        ?callable $onSpam = null
    ): callable {
        return function(array $update, callable $next) use ($floodTimeout, $onSpam) {
            global $telegramCache;
            
            $userId = $update['message']['from']['id'] ?? null;
            
            if ($userId === null) {
                return $next();
            }
            
            if (!isset($telegramCache) || !$telegramCache instanceof TelegramCache) {
                return $next();
            }
            
            $cacheKey = "spam:last_message:{$userId}";
            $lastMessage = $telegramCache->get($cacheKey, 0);
            
            if (time() - $lastMessage < $floodTimeout) {
                if ($onSpam !== null) {
                    return $onSpam($update);
                }
                
                return false;
            }
            
            $telegramCache->set($cacheKey, time(), 300);
            
            return $next();
        };
    }

    /**
     * Blacklist middleware - blocks specific users
     * 
     * @param array $blacklistedIds List of blocked user IDs
     * @return callable Middleware closure
     */
    public static function blacklist(array $blacklistedIds): callable
    {
        return function(array $update, callable $next) use ($blacklistedIds) {
            $userId = $update['message']['from']['id'] 
                    ?? $update['callback_query']['from']['id'] 
                    ?? $update['inline_query']['from']['id'] 
                    ?? null;
            
            if ($userId !== null && in_array($userId, $blacklistedIds)) {
                return false;
            }
            
            return $next();
        };
    }

    /**
     * Whitelist middleware - only allows specific users
     * 
     * @param array $whitelistedIds List of allowed user IDs
     * @return callable Middleware closure
     */
    public static function whitelist(array $whitelistedIds): callable
    {
        return function(array $update, callable $next) use ($whitelistedIds) {
            $userId = $update['message']['from']['id'] 
                    ?? $update['callback_query']['from']['id'] 
                    ?? $update['inline_query']['from']['id'] 
                    ?? null;
            
            if ($userId === null || !in_array($userId, $whitelistedIds)) {
                return false;
            }
            
            return $next();
        };
    }

    /**
     * Message length limit middleware
     * 
     * @param int $maxLength Maximum message length
     * @return callable Middleware closure
     */
    public static function messageLengthLimit(int $maxLength = 4096): callable
    {
        return function(array $update, callable $next) use ($maxLength) {
            $text = $update['message']['text'] 
                  ?? $update['message']['caption'] 
                  ?? $update['callback_query']['data'] 
                  ?? '';
            
            if (strlen($text) > $maxLength) {
                return false;
            }
            
            return $next();
        };
    }

    /**
     * Maintenance mode middleware
     * 
     * @param bool $enabled Maintenance mode status
     * @param array $exemptIds User IDs exempt from maintenance
     * @param callable|null $onMaintenance Handler when in maintenance
     * @return callable Middleware closure
     */
    public static function maintenanceMode(
        bool $enabled = true,
        array $exemptIds = [],
        ?callable $onMaintenance = null
    ): callable {
        return function(array $update, callable $next) use ($enabled, $exemptIds, $onMaintenance) {
            if (!$enabled) {
                return $next();
            }
            
            $userId = $update['message']['from']['id'] 
                    ?? $update['callback_query']['from']['id'] 
                    ?? null;
            
            if ($userId !== null && in_array($userId, $exemptIds)) {
                return $next();
            }
            
            if ($onMaintenance !== null) {
                return $onMaintenance($update);
            }
            
            return false;
        };
    }
}
