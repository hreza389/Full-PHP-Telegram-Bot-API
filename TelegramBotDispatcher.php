<?php

require_once 'TelegramBot.php';
require_once 'TelegramResponse.php';
require_once 'TelegramTypes.php';

/**
 * Telegram Bot Event Dispatcher
 * 
 * Provides an event-driven architecture for handling bot updates.
 */
class TelegramBotDispatcher
{
    private TelegramBot $bot;
    private array $commandHandlers = [];
    private array $callbackHandlers = [];
    private array $messageHandlers = [];
    private array $eventHandlers = [];
    private ?array $currentUpdate = null;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
    }

    /**
     * Register a command handler
     * 
     * @param string $command Command name (without /)
     * @param callable $handler Handler function
     * @param array $options Options (aliases, adminOnly, etc.)
     */
    public function onCommand(string $command, callable $handler, array $options = []): self
    {
        $commands = [$command];
        if (isset($options['aliases']) && is_array($options['aliases'])) {
            $commands = array_merge($commands, $options['aliases']);
        }

        foreach ($commands as $cmd) {
            $this->commandHandlers[strtolower($cmd)] = [
                'handler' => $handler,
                'adminOnly' => $options['adminOnly'] ?? false,
                'description' => $options['description'] ?? ''
            ];
        }

        return $this;
    }

    /**
     * Register a callback query handler
     * 
     * @param string $pattern Pattern to match (supports * wildcard)
     * @param callable $handler Handler function
     */
    public function onCallback(string $pattern, callable $handler): self
    {
        $this->callbackHandlers[] = [
            'pattern' => $pattern,
            'handler' => $handler
        ];
        return $this;
    }

    /**
     * Register a message handler with filters
     * 
     * @param callable $handler Handler function
     * @param array $filters Filters (text, contains, type, etc.)
     */
    public function onMessage(callable $handler, array $filters = []): self
    {
        $this->messageHandlers[] = [
            'handler' => $handler,
            'filters' => $filters
        ];
        return $this;
    }

    /**
     * Register an event handler
     * 
     * @param string $event Event name (message, edited_message, channel_post, etc.)
     * @param callable $handler Handler function
     */
    public function onEvent(string $event, callable $handler): self
    {
        $this->eventHandlers[$event][] = $handler;
        return $this;
    }

    /**
     * Handle an update
     * 
     * @param array $update Update array from Telegram
     * @return bool True if handled, false otherwise
     */
    public function handle(array $update): bool
    {
        $this->currentUpdate = $update;

        // Check for callback query
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        // Check for inline query
        if (isset($update['inline_query'])) {
            return $this->handleInlineQuery($update['inline_query']);
        }

        // Check for pre-checkout query
        if (isset($update['pre_checkout_query'])) {
            return $this->handlePreCheckoutQuery($update['pre_checkout_query']);
        }

        // Check for shipping query
        if (isset($update['shipping_query'])) {
            return $this->handleShippingQuery($update['shipping_query']);
        }

        // Check for various message types
        $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? $update['edited_channel_post'] ?? null;
        
        if ($message) {
            return $this->handleMessage($message);
        }

        // Trigger generic event handlers
        if (isset($update['message'])) {
            $this->triggerEvent('message', $update);
        } elseif (isset($update['edited_message'])) {
            $this->triggerEvent('edited_message', $update);
        } elseif (isset($update['channel_post'])) {
            $this->triggerEvent('channel_post', $update);
        } elseif (isset($update['chat_member'])) {
            $this->triggerEvent('chat_member', $update);
        }

        return false;
    }

    /**
     * Handle incoming messages
     */
    private function handleMessage(array $message): bool
    {
        $handled = false;

        // Check for commands
        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            $handled = $this->handleCommand($message);
            if ($handled) {
                return true;
            }
        }

        // Check message handlers
        foreach ($this->messageHandlers as $handlerData) {
            if ($this->matchesFilters($message, $handlerData['filters'])) {
                call_user_func($handlerData['handler'], $message, $this->bot);
                $handled = true;
                break;
            }
        }

        // Trigger message event
        $this->triggerEvent('message', ['message' => $message]);

        return $handled;
    }

    /**
     * Handle commands
     */
    private function handleCommand(array $message): bool
    {
        $text = $message['text'];
        $parts = explode(' ', $text);
        $command = explode('@', $parts[0]);
        $commandName = strtolower(substr($command[0], 1)); // Remove /

        if (!isset($this->commandHandlers[$commandName])) {
            return false;
        }

        $handlerData = $this->commandHandlers[$commandName];

        // Check admin only
        if ($handlerData['adminOnly']) {
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            
            try {
                $member = $this->bot->getChatMember($chatId, $userId);
                $status = $member['result']['status'] ?? '';
                if (!in_array($status, ['creator', 'administrator'])) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }

        call_user_func($handlerData['handler'], $message, $this->bot);
        return true;
    }

    /**
     * Handle callback queries
     */
    private function handleCallbackQuery(array $callbackQuery): bool
    {
        $data = $callbackQuery['data'] ?? '';
        $handled = false;

        foreach ($this->callbackHandlers as $handlerData) {
            $pattern = $handlerData['pattern'];
            
            // Convert pattern to regex (* becomes .*)
            $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
            
            if (preg_match($regex, $data)) {
                call_user_func($handlerData['handler'], $callbackQuery, $this->bot);
                $handled = true;
                break;
            }
        }

        $this->triggerEvent('callback_query', ['callback_query' => $callbackQuery]);
        return $handled;
    }

    /**
     * Handle inline queries
     */
    private function handleInlineQuery(array $inlineQuery): bool
    {
        $this->triggerEvent('inline_query', ['inline_query' => $inlineQuery]);
        return true;
    }

    /**
     * Handle pre-checkout queries
     */
    private function handlePreCheckoutQuery(array $preCheckoutQuery): bool
    {
        $this->triggerEvent('pre_checkout_query', ['pre_checkout_query' => $preCheckoutQuery]);
        return true;
    }

    /**
     * Handle shipping queries
     */
    private function handleShippingQuery(array $shippingQuery): bool
    {
        $this->triggerEvent('shipping_query', ['shipping_query' => $shippingQuery]);
        return true;
    }

    /**
     * Check if a message matches filters
     */
    private function matchesFilters(array $message, array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        // Text filter
        if (isset($filters['text'])) {
            if (!isset($message['text']) || $message['text'] !== $filters['text']) {
                return false;
            }
        }

        // Contains filter
        if (isset($filters['contains'])) {
            if (!isset($message['text']) || strpos($message['text'], $filters['contains']) === false) {
                return false;
            }
        }

        // Type filter (photo, document, video, etc.)
        if (isset($filters['type'])) {
            $types = is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $hasType = false;
            foreach ($types as $type) {
                if (isset($message[$type])) {
                    $hasType = true;
                    break;
                }
            }
            if (!$hasType) {
                return false;
            }
        }

        // Chat type filter
        if (isset($filters['chat_type'])) {
            $chatType = $message['chat']['type'] ?? '';
            $allowedTypes = is_array($filters['chat_type']) ? $filters['chat_type'] : [$filters['chat_type']];
            if (!in_array($chatType, $allowedTypes)) {
                return false;
            }
        }

        // From user ID filter
        if (isset($filters['from'])) {
            $fromId = $message['from']['id'] ?? null;
            $allowedIds = is_array($filters['from']) ? $filters['from'] : [$filters['from']];
            if (!in_array($fromId, $allowedIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Trigger event handlers
     */
    private function triggerEvent(string $event, array $data): void
    {
        if (!isset($this->eventHandlers[$event])) {
            return;
        }

        foreach ($this->eventHandlers[$event] as $handler) {
            call_user_func($handler, $data, $this->bot);
        }
    }

    /**
     * Get the current update being processed
     */
    public function getCurrentUpdate(): ?array
    {
        return $this->currentUpdate;
    }

    /**
     * Run the bot with long polling
     * 
     * @param int $timeout Timeout in seconds
     * @param int $limit Maximum number of updates to retrieve
     */
    public function run(int $timeout = 30, int $limit = 100): void
    {
        echo "Bot started. Press Ctrl+C to stop.\n";
        
        $offset = 0;
        
        while (true) {
            try {
                $response = $this->bot->getUpdates($timeout, $limit, $offset);
                
                if ($response->isOk() && !empty($response->getResult())) {
                    foreach ($response->getResult() as $update) {
                        $this->handle($update);
                        $offset = $update['update_id'] + 1;
                    }
                }
            } catch (Exception $e) {
                error_log("Error: " . $e->getMessage());
                sleep(1);
            }
        }
    }
}
