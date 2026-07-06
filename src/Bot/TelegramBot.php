<?php

namespace TelegramBot\Bot;

/**
 * Modern Telegram Bot API Client
 * 
 * A comprehensive, modern implementation of the Telegram Bot API
 * with support for all methods, webhook management, payments, and more.
 * 
 * @package TelegramBot\Bot
 * @version 2.0.0
 * @author Telegram Bot Framework
 */
class TelegramBot
{
    /**
     * @var string Bot API token
     */
    private string $token;

    /**
     * @var string Base API URL
     */
    private string $baseUrl = 'https://api.telegram.org/bot';

    /**
     * @var array|null Latest update data
     */
    private ?array $update = null;

    /**
     * @var bool Enable error logging
     */
    private bool $logErrors;

    /**
     * @var array Proxy configuration
     */
    private array $proxy;

    /**
     * @var \TelegramBot\Core\Logger|null Logger instance
     */
    private $logger;

    // ========================================================================
    // UPDATE TYPE CONSTANTS
    // ========================================================================
    
    public const UPDATE_MESSAGE = 'message';
    public const UPDATE_EDITED_MESSAGE = 'edited_message';
    public const UPDATE_CHANNEL_POST = 'channel_post';
    public const UPDATE_EDITED_CHANNEL_POST = 'edited_channel_post';
    public const UPDATE_INLINE_QUERY = 'inline_query';
    public const UPDATE_CHOSEN_INLINE_RESULT = 'chosen_inline_result';
    public const UPDATE_CALLBACK_QUERY = 'callback_query';
    public const UPDATE_SHIPPING_QUERY = 'shipping_query';
    public const UPDATE_PRE_CHECKOUT_QUERY = 'pre_checkout_query';
    public const UPDATE_POLL = 'poll';
    public const UPDATE_POLL_ANSWER = 'poll_answer';
    public const UPDATE_MY_CHAT_MEMBER = 'my_chat_member';
    public const UPDATE_CHAT_MEMBER = 'chat_member';
    public const UPDATE_CHAT_JOIN_REQUEST = 'chat_join_request';

    // Message type constants
    public const MESSAGE_TYPE_TEXT = 'text';
    public const MESSAGE_TYPE_PHOTO = 'photo';
    public const MESSAGE_TYPE_VIDEO = 'video';
    public const MESSAGE_TYPE_AUDIO = 'audio';
    public const MESSAGE_TYPE_VOICE = 'voice';
    public const MESSAGE_TYPE_DOCUMENT = 'document';
    public const MESSAGE_TYPE_STICKER = 'sticker';
    public const MESSAGE_TYPE_ANIMATION = 'animation';
    public const MESSAGE_TYPE_VIDEO_NOTE = 'video_note';
    public const MESSAGE_TYPE_CONTACT = 'contact';
    public const MESSAGE_TYPE_LOCATION = 'location';
    public const MESSAGE_TYPE_VENUE = 'venue';
    public const MESSAGE_TYPE_DICE = 'dice';
    public const MESSAGE_TYPE_INVOICE = 'invoice';
    public const MESSAGE_TYPE_SUCCESSFUL_PAYMENT = 'successful_payment';

    // Parse mode constants
    public const PARSE_MARKDOWN = 'Markdown';
    public const PARSE_MARKDOWN_V2 = 'MarkdownV2';
    public const PARSE_HTML = 'HTML';

    // ========================================================================
    // CONSTRUCTOR & INITIALIZATION
    // ========================================================================

    /**
     * Create a Telegram bot instance
     * 
     * @param string $token Bot API token
     * @param bool $logErrors Enable error logging
     * @param array $proxy Proxy configuration ['url' => '', 'port' => 0, 'type' => 'http', 'auth' => '']
     */
    public function __construct(string $token, bool $logErrors = true, array $proxy = [])
    {
        $this->token = $token;
        $this->logErrors = $logErrors;
        $this->proxy = $proxy;
        
        // Initialize logger if available
        if (class_exists('\TelegramBot\Core\Logger')) {
            try {
                $this->logger = new \TelegramBot\Core\Logger();
            } catch (\Exception $e) {
                // Logger initialization failed, continue without it
            }
        }
    }

    /**
     * Set the current update data
     * 
     * @param array $update Update array from Telegram
     * @return self
     */
    public function setUpdate(array $update): self
    {
        $this->update = $update;
        return $this;
    }

    /**
     * Get the current update data
     * 
     * @return array|null
     */
    public function getUpdate(): ?array
    {
        return $this->update;
    }

    // ========================================================================
    // CORE API METHODS
    // ========================================================================

    /**
     * Make request to Telegram API
     * 
     * @param string $method API method name
     * @param array $data Request parameters
     * @param bool $post Use POST request
     * @return array|null Decoded response or null on failure
     */
    public function request(string $method, array $data = [], bool $post = true): ?array
    {
        $url = $this->baseUrl . $this->token . '/' . $method;
        
        if ($this->logger) {
            $this->logger->logRequest($method, $data);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, $post);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post ? $data : http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Proxy configuration
        if (!empty($this->proxy['url'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['url']);
            if (!empty($this->proxy['port'])) {
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
            }
            if (!empty($this->proxy['type'])) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy['type'] === 'socks5' ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP);
            }
            if (!empty($this->proxy['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['auth']);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            if ($this->logErrors && $this->logger) {
                $this->logger->error('cURL error: ' . $error, ['method' => $method]);
            }
            return null;
        }

        $result = json_decode($response, true);

        if ($this->logger) {
            $this->logger->logResponse($result['ok'] ?? false, $result);
        }

        // Log errors if request failed
        if (($result['ok'] ?? false) === false && $this->logErrors && $this->logger) {
            $this->logger->error('Telegram API error', [
                'method' => $method,
                'error_code' => $result['error_code'] ?? null,
                'description' => $result['description'] ?? null
            ]);
        }

        return $result ?: null;
    }

    /**
     * Test the bot token
     * 
     * @return array|null Bot information or null on failure
     */
    public function getMe(): ?array
    {
        return $this->request('getMe');
    }

    /**
     * Log out from cloud session
     * 
     * @return bool Success
     */
    public function logOut(): bool
    {
        $result = $this->request('logOut');
        return $result['ok'] ?? false;
    }

    /**
     * Close bot instance
     * 
     * @return bool Success
     */
    public function close(): bool
    {
        $result = $this->request('close');
        return $result['ok'] ?? false;
    }

    // ========================================================================
    // MESSAGE SENDING METHODS
    // ========================================================================

    /**
     * Send text message
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param array $options Additional options
     * @return array|null Response or null on failure
     */
    public function sendMessage($chatId, string $text, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $text
        ], $options);

        return $this->request('sendMessage', $data);
    }

    /**
     * Forward message
     * 
     * @param int|string $chatId Destination chat
     * @param int|string $fromChatId Source chat
     * @param int $messageId Message ID to forward
     * @param array $options Additional options
     * @return array|null Response
     */
    public function forwardMessage($chatId, $fromChatId, int $messageId, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ], $options);

        return $this->request('forwardMessage', $data);
    }

    /**
     * Copy message
     * 
     * @param int|string $chatId Destination chat
     * @param int|string $fromChatId Source chat
     * @param int $messageId Message ID to copy
     * @param array $options Additional options
     * @return array|null Response with message_id
     */
    public function copyMessage($chatId, $fromChatId, int $messageId, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ], $options);

        return $this->request('copyMessage', $data);
    }

    /**
     * Send photo
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $photo Photo file_id, URL, or file
     * @param array $options Caption, parse_mode, etc.
     * @return array|null Response
     */
    public function sendPhoto($chatId, $photo, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo
        ], $options);

        return $this->request('sendPhoto', $data);
    }

    /**
     * Send audio
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $audio Audio file
     * @param array $options Duration, performer, title, etc.
     * @return array|null Response
     */
    public function sendAudio($chatId, $audio, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'audio' => $audio
        ], $options);

        return $this->request('sendAudio', $data);
    }

    /**
     * Send document
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $document Document file
     * @param array $options Caption, filename, etc.
     * @return array|null Response
     */
    public function sendDocument($chatId, $document, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'document' => $document
        ], $options);

        return $this->request('sendDocument', $data);
    }

    /**
     * Send video
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $video Video file
     * @param array $options Duration, width, height, caption, etc.
     * @return array|null Response
     */
    public function sendVideo($chatId, $video, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'video' => $video
        ], $options);

        return $this->request('sendVideo', $data);
    }

    /**
     * Send animation (GIF)
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $animation Animation file
     * @param array $options Duration, width, height, caption, etc.
     * @return array|null Response
     */
    public function sendAnimation($chatId, $animation, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'animation' => $animation
        ], $options);

        return $this->request('sendAnimation', $data);
    }

    /**
     * Send voice message
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $voice Voice file
     * @param array $options Duration, caption, etc.
     * @return array|null Response
     */
    public function sendVoice($chatId, $voice, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'voice' => $voice
        ], $options);

        return $this->request('sendVoice', $data);
    }

    /**
     * Send video note
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $videoNote Video note file
     * @param array $options Duration, length, thumbnail, etc.
     * @return array|null Response
     */
    public function sendVideoNote($chatId, $videoNote, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'video_note' => $videoNote
        ], $options);

        return $this->request('sendVideoNote', $data);
    }

    /**
     * Send media group (multiple photos/videos)
     * 
     * @param int|string $chatId Chat ID
     * @param array $media Array of media items
     * @param array $options Additional options
     * @return array|null Response
     */
    public function sendMediaGroup($chatId, array $media, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'media' => json_encode($media)
        ], $options);

        return $this->request('sendMediaGroup', $data);
    }

    /**
     * Send location
     * 
     * @param int|string $chatId Chat ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param array $options Horizontal accuracy, live period, etc.
     * @return array|null Response
     */
    public function sendLocation($chatId, float $latitude, float $longitude, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ], $options);

        return $this->request('sendLocation', $data);
    }

    /**
     * Edit message live location
     * 
     * @param array $options Chat_id/message_id or inline_message_id, lat, lon
     * @return array|null Response
     */
    public function editMessageLiveLocation(array $options): ?array
    {
        return $this->request('editMessageLiveLocation', $options);
    }

    /**
     * Stop message live location
     * 
     * @param array $options Chat_id/message_id or inline_message_id
     * @return array|null Response
     */
    public function stopMessageLiveLocation(array $options): ?array
    {
        return $this->request('stopMessageLiveLocation', $options);
    }

    /**
     * Send venue
     * 
     * @param int|string $chatId Chat ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param string $title Venue name
     * @param string $address Venue address
     * @param array $options Foursquare ID, type, etc.
     * @return array|null Response
     */
    public function sendVenue($chatId, float $latitude, float $longitude, string $title, string $address, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address
        ], $options);

        return $this->request('sendVenue', $data);
    }

    /**
     * Send contact
     * 
     * @param int|string $chatId Chat ID
     * @param string $phoneNumber Contact phone number
     * @param string $firstName Contact first name
     * @param array $options Last name, vcard, etc.
     * @return array|null Response
     */
    public function sendContact($chatId, string $phoneNumber, string $firstName, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName
        ], $options);

        return $this->request('sendContact', $data);
    }

    /**
     * Send poll
     * 
     * @param int|string $chatId Chat ID
     * @param string $question Poll question
     * @param array $options Poll options, type, etc.
     * @return array|null Response
     */
    public function sendPoll($chatId, string $question, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'question' => $question
        ], $options);

        return $this->request('sendPoll', $data);
    }

    /**
     * Send dice
     * 
     * @param int|string $chatId Chat ID
     * @param string $emoji Emoji type (🎲, 🎯, 🏀, ⚽, 🎳, 🎰)
     * @param array $options Additional options
     * @return array|null Response
     */
    public function sendDice($chatId, string $emoji = '🎲', array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'emoji' => $emoji
        ], $options);

        return $this->request('sendDice', $data);
    }

    /**
     * Send sticker
     * 
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $sticker Sticker file_id or file
     * @param array $options Emoji, reply markup, etc.
     * @return array|null Response
     */
    public function sendSticker($chatId, $sticker, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'sticker' => $sticker
        ], $options);

        return $this->request('sendSticker', $data);
    }

    /**
     * Send chat action (typing, uploading, etc.)
     * 
     * @param int|string $chatId Chat ID
     * @param string $action Action type
     * @return array|null Response
     */
    public function sendChatAction($chatId, string $action): ?array
    {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }

    // ========================================================================
    // MESSAGE EDITING METHODS
    // ========================================================================

    /**
     * Edit message text
     * 
     * @param array $options Text, chat_id, message_id, inline_message_id, etc.
     * @return array|null Response
     */
    public function editMessageText(array $options): ?array
    {
        return $this->request('editMessageText', $options);
    }

    /**
     * Edit message caption
     * 
     * @param array $options Caption, chat_id, message_id, etc.
     * @return array|null Response
     */
    public function editMessageCaption(array $options): ?array
    {
        return $this->request('editMessageCaption', $options);
    }

    /**
     * Edit message media
     * 
     * @param array $options Media, chat_id, message_id, etc.
     * @return array|null Response
     */
    public function editMessageMedia(array $options): ?array
    {
        return $this->request('editMessageMedia', $options);
    }

    /**
     * Edit message reply markup
     * 
     * @param array $options Reply markup, chat_id, message_id, etc.
     * @return array|null Response
     */
    public function editMessageReplyMarkup(array $options): ?array
    {
        return $this->request('editMessageReplyMarkup', $options);
    }

    /**
     * Stop poll
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageId Message ID
     * @param array $options Reply markup
     * @return array|null Response
     */
    public function stopPoll($chatId, int $messageId, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ], $options);

        return $this->request('stopPoll', $data);
    }

    /**
     * Delete message
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageId Message ID to delete
     * @return bool Success
     */
    public function deleteMessage($chatId, int $messageId): bool
    {
        $result = $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        return $result['ok'] ?? false;
    }

    // ========================================================================
    // KEYBOARD BUILDERS
    // ========================================================================

    /**
     * Build inline keyboard
     * 
     * @param array $rows Array of button rows
     * @return array Inline keyboard markup
     */
    public function buildInlineKeyboard(array $rows): array
    {
        $keyboard = [];
        foreach ($rows as $row) {
            $keyboard[] = $row;
        }
        return ['inline_keyboard' => $keyboard];
    }

    /**
     * Build inline keyboard button
     * 
     * @param string $text Button text
     * @param string|null $url URL for button
     * @param string|null $callbackData Callback data
     * @param string|null $switchInlineQuery Switch inline query
     * @param string|null $pay Pay button
     * @return array Button configuration
     */
    public function inlineButton(
        string $text,
        ?string $url = null,
        ?string $callbackData = null,
        ?string $switchInlineQuery = null,
        ?string $pay = null
    ): array {
        $button = ['text' => $text];
        
        if ($url !== null) $button['url'] = $url;
        if ($callbackData !== null) $button['callback_data'] = $callbackData;
        if ($switchInlineQuery !== null) $button['switch_inline_query'] = $switchInlineQuery;
        if ($pay !== null) $button['pay'] = $pay;

        return $button;
    }

    /**
     * Build reply keyboard
     * 
     * @param array $rows Array of button rows
     * @param bool $oneTimeKeyboard Hide keyboard after use
     * @param bool $resizeKeyboard Resize keyboard vertically
     * @param bool $selective Show to specific users only
     * @param string|null $placeholder Placeholder text
     * @return array Reply keyboard markup
     */
    public function buildReplyKeyboard(
        array $rows,
        bool $oneTimeKeyboard = false,
        bool $resizeKeyboard = true,
        bool $selective = false,
        ?string $placeholder = null
    ): array {
        $keyboard = ['keyboard' => $rows];
        
        if ($oneTimeKeyboard) $keyboard['one_time_keyboard'] = true;
        if ($resizeKeyboard) $keyboard['resize_keyboard'] = true;
        if ($selective) $keyboard['selective'] = true;
        if ($placeholder !== null) $keyboard['input_field_placeholder'] = $placeholder;

        return $keyboard;
    }

    /**
     * Build reply keyboard button
     * 
     * @param string $text Button text
     * @param bool $requestContact Request contact
     * @param bool $requestLocation Request location
     * @return array Button configuration
     */
    public function keyboardButton(
        string $text,
        bool $requestContact = false,
        bool $requestLocation = false
    ): array {
        $button = ['text' => $text];
        
        if ($requestContact) $button['request_contact'] = true;
        if ($requestLocation) $button['request_location'] = true;

        return $button;
    }

    /**
     * Build force reply
     * 
     * @param bool $selective Force reply for specific users
     * @param string|null $placeholder Placeholder text
     * @return array Force reply markup
     */
    public function forceReply(bool $selective = false, ?string $placeholder = null): array
    {
        $reply = ['force_reply' => true];
        
        if ($selective) $reply['selective'] = true;
        if ($placeholder !== null) $reply['input_field_placeholder'] = $placeholder;

        return $reply;
    }

    /**
     * Build reply keyboard hide
     * 
     * @param bool $selective Hide for specific users
     * @return array Reply keyboard hide markup
     */
    public function replyKeyboardHide(bool $selective = false): array
    {
        return [
            'remove_keyboard' => true,
            'selective' => $selective
        ];
    }

    // ========================================================================
    // WEBHOOK MANAGEMENT
    // ========================================================================

    /**
     * Set webhook
     * 
     * @param string $url HTTPS webhook URL
     * @param array $options Certificate, IP whitelist, max connections, allowed updates
     * @return bool Success
     */
    public function setWebhook(string $url, array $options = []): bool
    {
        $data = array_merge(['url' => $url], $options);
        $result = $this->request('setWebhook', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Delete webhook
     * 
     * @return bool Success
     */
    public function deleteWebhook(): bool
    {
        $result = $this->request('deleteWebhook');
        return $result['ok'] ?? false;
    }

    /**
     * Get webhook info
     * 
     * @return array|null Webhook information
     */
    public function getWebhookInfo(): ?array
    {
        return $this->request('getWebhookInfo', [], false);
    }

    // ========================================================================
    // GET UPDATES
    // ========================================================================

    /**
     * Get updates
     * 
     * @param int $offset Offset for updates
     * @param int $limit Maximum number of updates
     * @param int $timeout Long polling timeout
     * @param array|null $allowedUpdates Types of updates to receive
     * @return array|null Array of updates
     */
    public function getUpdates(
        int $offset = 0,
        int $limit = 100,
        int $timeout = 0,
        ?array $allowedUpdates = null
    ): ?array {
        $data = [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout
        ];

        if ($allowedUpdates !== null) {
            $data['allowed_updates'] = json_encode($allowedUpdates);
        }

        $result = $this->request('getUpdates', $data);
        return $result['result'] ?? null;
    }

    // ========================================================================
    // CHAT METHODS
    // ========================================================================

    /**
     * Ban chat member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID to ban
     * @param array $options Until date, revoke messages
     * @return bool Success
     */
    public function banChatMember($chatId, int $userId, array $options = []): bool
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId
        ], $options);

        $result = $this->request('banChatMember', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Unban chat member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID to unban
     * @param bool $onlyIfBanned Only unban if banned
     * @return bool Success
     */
    public function unbanChatMember($chatId, int $userId, bool $onlyIfBanned = false): bool
    {
        $result = $this->request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Restrict chat member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @param array $permissions Permissions object
     * @param array $options Until date, use independent chat permissions
     * @return bool Success
     */
    public function restrictChatMember($chatId, int $userId, array $permissions, array $options = []): bool
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions)
        ], $options);

        $result = $this->request('restrictChatMember', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Promote chat member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @param array $options Permission flags
     * @return bool Success
     */
    public function promoteChatMember($chatId, int $userId, array $options = []): bool
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId
        ], $options);

        $result = $this->request('promoteChatMember', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Set chat administrator custom title
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @param string $customTitle Custom title
     * @return bool Success
     */
    public function setChatAdministratorCustomTitle($chatId, int $userId, string $customTitle): bool
    {
        $result = $this->request('setChatAdministratorCustomTitle', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Ban chat sender chat
     * 
     * @param int|string $chatId Chat ID
     * @param int $senderChatId Sender chat ID
     * @return bool Success
     */
    public function banChatSenderChat($chatId, int $senderChatId): bool
    {
        $result = $this->request('banChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Unban chat sender chat
     * 
     * @param int|string $chatId Chat ID
     * @param int $senderChatId Sender chat ID
     * @return bool Success
     */
    public function unbanChatSenderChat($chatId, int $senderChatId): bool
    {
        $result = $this->request('unbanChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set chat permissions
     * 
     * @param int|string $chatId Chat ID
     * @param array $permissions Permissions object
     * @return bool Success
     */
    public function setChatPermissions($chatId, array $permissions): bool
    {
        $result = $this->request('setChatPermissions', [
            'chat_id' => $chatId,
            'permissions' => json_encode($permissions)
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Export chat invite link
     * 
     * @param int|string $chatId Chat ID
     * @return string|null Invite link or null on failure
     */
    public function exportChatInviteLink($chatId): ?string
    {
        $result = $this->request('exportChatInviteLink', ['chat_id' => $chatId]);
        return $result['result'] ?? null;
    }

    /**
     * Create chat invite link
     * 
     * @param int|string $chatId Chat ID
     * @param array $options Name, expire date, member limit, etc.
     * @return array|null Invite link info
     */
    public function createChatInviteLink($chatId, array $options = []): ?array
    {
        $data = array_merge(['chat_id' => $chatId], $options);
        $result = $this->request('createChatInviteLink', $data);
        return $result['result'] ?? null;
    }

    /**
     * Edit chat invite link
     * 
     * @param int|string $chatId Chat ID
     * @param string $inviteLink Invite link to edit
     * @param array $options New settings
     * @return array|null Updated invite link info
     */
    public function editChatInviteLink($chatId, string $inviteLink, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ], $options);

        $result = $this->request('editChatInviteLink', $data);
        return $result['result'] ?? null;
    }

    /**
     * Revoke chat invite link
     * 
     * @param int|string $chatId Chat ID
     * @param string $inviteLink Invite link to revoke
     * @return array|null Revoked invite link info
     */
    public function revokeChatInviteLink($chatId, string $inviteLink): ?array
    {
        $result = $this->request('revokeChatInviteLink', [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ]);
        return $result['result'] ?? null;
    }

    /**
     * Approve chat join request
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function approveChatJoinRequest($chatId, int $userId): bool
    {
        $result = $this->request('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Decline chat join request
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function declineChatJoinRequest($chatId, int $userId): bool
    {
        $result = $this->request('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set chat photo
     * 
     * @param int|string $chatId Chat ID
     * @param \CURLFile $photo Photo file
     * @return bool Success
     */
    public function setChatPhoto($chatId, \CURLFile $photo): bool
    {
        $result = $this->request('setChatPhoto', [
            'chat_id' => $chatId,
            'photo' => $photo
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Delete chat photo
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function deleteChatPhoto($chatId): bool
    {
        $result = $this->request('deleteChatPhoto', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Set chat title
     * 
     * @param int|string $chatId Chat ID
     * @param string $title New title
     * @return bool Success
     */
    public function setChatTitle($chatId, string $title): bool
    {
        $result = $this->request('setChatTitle', [
            'chat_id' => $chatId,
            'title' => $title
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set chat description
     * 
     * @param int|string $chatId Chat ID
     * @param string $description New description
     * @return bool Success
     */
    public function setChatDescription($chatId, string $description): bool
    {
        $result = $this->request('setChatDescription', [
            'chat_id' => $chatId,
            'description' => $description
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Pin chat message
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageId Message ID to pin
     * @param array $options Disable notification
     * @return bool Success
     */
    public function pinChatMessage($chatId, int $messageId, array $options = []): bool
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ], $options);

        $result = $this->request('pinChatMessage', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Unpin chat message
     * 
     * @param int|string $chatId Chat ID
     * @param int|null $messageId Message ID to unpin (null = all)
     * @return bool Success
     */
    public function unpinChatMessage($chatId, ?int $messageId = null): bool
    {
        $data = ['chat_id' => $chatId];
        if ($messageId !== null) {
            $data['message_id'] = $messageId;
        }

        $result = $this->request('unpinChatMessage', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Unpin all chat messages
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function unpinAllChatMessages($chatId): bool
    {
        $result = $this->request('unpinAllChatMessages', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Leave chat
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function leaveChat($chatId): bool
    {
        $result = $this->request('leaveChat', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Get chat info
     * 
     * @param int|string $chatId Chat ID
     * @return array|null Chat information
     */
    public function getChat($chatId): ?array
    {
        $result = $this->request('getChat', ['chat_id' => $chatId]);
        return $result['result'] ?? null;
    }

    /**
     * Get chat administrators
     * 
     * @param int|string $chatId Chat ID
     * @return array|null List of administrators
     */
    public function getChatAdministrators($chatId): ?array
    {
        $result = $this->request('getChatAdministrators', ['chat_id' => $chatId]);
        return $result['result'] ?? null;
    }

    /**
     * Get chat member count
     * 
     * @param int|string $chatId Chat ID
     * @return int|null Member count
     */
    public function getChatMemberCount($chatId): ?int
    {
        $result = $this->request('getChatMemberCount', ['chat_id' => $chatId]);
        return $result['result'] ?? null;
    }

    /**
     * Get chat member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @return array|null Member information
     */
    public function getChatMember($chatId, int $userId): ?array
    {
        $result = $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
        return $result['result'] ?? null;
    }

    /**
     * Set chat sticker set
     * 
     * @param int|string $chatId Chat ID
     * @param string $stickerSetName Sticker set name
     * @return bool Success
     */
    public function setChatStickerSet($chatId, string $stickerSetName): bool
    {
        $result = $this->request('setChatStickerSet', [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Delete chat sticker set
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function deleteChatStickerSet($chatId): bool
    {
        $result = $this->request('deleteChatStickerSet', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Get forum topic icon sticker colors
     * 
     * @return array|null Color list
     */
    public function getForumTopicIconStickers(): ?array
    {
        return $this->request('getForumTopicIconStickers', [], false);
    }

    /**
     * Create forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param string $name Topic name
     * @param array $options Icon color, icon custom emoji ID
     * @return array|null Topic info
     */
    public function createForumTopic($chatId, string $name, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'name' => $name
        ], $options);

        $result = $this->request('createForumTopic', $data);
        return $result['result'] ?? null;
    }

    /**
     * Edit forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageThreadId Thread ID
     * @param array $options Name, icon custom emoji ID
     * @return bool Success
     */
    public function editForumTopic($chatId, int $messageThreadId, array $options = []): bool
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId
        ], $options);

        $result = $this->request('editForumTopic', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Close forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageThreadId Thread ID
     * @return bool Success
     */
    public function closeForumTopic($chatId, int $messageThreadId): bool
    {
        $result = $this->request('closeForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Reopen forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageThreadId Thread ID
     * @return bool Success
     */
    public function reopenForumTopic($chatId, int $messageThreadId): bool
    {
        $result = $this->request('reopenForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Delete forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageThreadId Thread ID
     * @return bool Success
     */
    public function deleteForumTopic($chatId, int $messageThreadId): bool
    {
        $result = $this->request('deleteForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Unpin all forum topic messages
     * 
     * @param int|string $chatId Chat ID
     * @param int $messageThreadId Thread ID
     * @return bool Success
     */
    public function unpinAllForumTopicMessages($chatId, int $messageThreadId): bool
    {
        $result = $this->request('unpinAllForumTopicMessages', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Edit general forum topic
     * 
     * @param int|string $chatId Chat ID
     * @param string $name New name
     * @return bool Success
     */
    public function editGeneralForumTopic($chatId, string $name): bool
    {
        $result = $this->request('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Close general forum topic
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function closeGeneralForumTopic($chatId): bool
    {
        $result = $this->request('closeGeneralForumTopic', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Reopen general forum topic
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function reopenGeneralForumTopic($chatId): bool
    {
        $result = $this->request('reopenGeneralForumTopic', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Hide general forum topic
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function hideGeneralForumTopic($chatId): bool
    {
        $result = $this->request('hideGeneralForumTopic', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    /**
     * Unhide general forum topic
     * 
     * @param int|string $chatId Chat ID
     * @return bool Success
     */
    public function unhideGeneralForumTopic($chatId): bool
    {
        $result = $this->request('unhideGeneralForumTopic', ['chat_id' => $chatId]);
        return $result['ok'] ?? false;
    }

    // ========================================================================
    // PAYMENT METHODS
    // ========================================================================

    /**
     * Send invoice
     * 
     * @param int|string $chatId Chat ID
     * @param string $title Product title
     * @param string $description Product description
     * @param string $payload Bot-defined invoice payload
     * @param string $providerToken Payment provider token
     * @param string $currency Three-letter ISO 4217 currency code
     * @param array $prices Price breakdown
     * @param array $options Additional options
     * @return array|null Response
     */
    public function sendInvoice(
        $chatId,
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices,
        array $options = []
    ): ?array {
        $data = array_merge([
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => json_encode($prices)
        ], $options);

        return $this->request('sendInvoice', $data);
    }

    /**
     * Create invoice link
     * 
     * @param string $title Product title
     * @param string $description Product description
     * @param string $payload Bot-defined invoice payload
     * @param string $providerToken Payment provider token
     * @param string $currency Currency code
     * @param array $prices Price breakdown
     * @param array $options Additional options
     * @return string|null Invoice URL
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices,
        array $options = []
    ): ?string {
        $data = array_merge([
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => json_encode($prices)
        ], $options);

        $result = $this->request('createInvoiceLink', $data);
        return $result['result'] ?? null;
    }

    /**
     * Answer shipping query
     * 
     * @param string $shippingQueryId Shipping query ID
     * @param bool $ok Success status
     * @param array $options Shipping options or error message
     * @return bool Success
     */
    public function answerShippingQuery(string $shippingQueryId, bool $ok, array $options = []): bool
    {
        $data = array_merge([
            'shipping_query_id' => $shippingQueryId,
            'ok' => $ok
        ], $options);

        $result = $this->request('answerShippingQuery', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Answer pre-checkout query
     * 
     * @param string $preCheckoutQueryId Pre-checkout query ID
     * @param bool $ok Success status
     * @param string|null $errorMessage Error message if not ok
     * @return bool Success
     */
    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, ?string $errorMessage = null): bool
    {
        $data = [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok
        ];

        if (!$ok && $errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        $result = $this->request('answerPreCheckoutQuery', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Refund star payment
     * 
     * @param int $userId User ID
     * @param int $telegramPaymentChargeId Telegram payment charge ID
     * @return bool Success
     */
    public function refundStarPayment(int $userId, int $telegramPaymentChargeId): bool
    {
        $result = $this->request('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId
        ]);
        return $result['ok'] ?? false;
    }

    // ========================================================================
    // INLINE QUERY METHODS
    // ========================================================================

    /**
     * Answer inline query
     * 
     * @param string $inlineQueryId Inline query ID
     * @param array $results Results array
     * @param array $options Cache time, is personal, next offset, button
     * @return bool Success
     */
    public function answerInlineQuery(string $inlineQueryId, array $results, array $options = []): bool
    {
        $data = array_merge([
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results)
        ], $options);

        $result = $this->request('answerInlineQuery', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Answer callback query
     * 
     * @param string $callbackQueryId Callback query ID
     * @param array $options Text, show alert, cache time, url
     * @return bool Success
     */
    public function answerCallbackQuery(string $callbackQueryId, array $options = []): bool
    {
        $data = array_merge(['callback_query_id' => $callbackQueryId], $options);
        $result = $this->request('answerCallbackQuery', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Set user menu button
     * 
     * @param array $options User ID, menu button
     * @return bool Success
     */
    public function setUserMenuButton(array $options = []): bool
    {
        $result = $this->request('setUserMenuButton', $options);
        return $result['ok'] ?? false;
    }

    /**
     * Get user menu button
     * 
     * @param array $options User ID
     * @return array|null Menu button info
     */
    public function getUserMenuButton(array $options = []): ?array
    {
        $result = $this->request('getUserMenuButton', $options);
        return $result['result'] ?? null;
    }

    /**
     * Set my default administrator rights
     * 
     * @param array $options Rights, for channels
     * @return bool Success
     */
    public function setMyDefaultAdministratorRights(array $options = []): bool
    {
        $result = $this->request('setMyDefaultAdministratorRights', $options);
        return $result['ok'] ?? false;
    }

    /**
     * Get my default administrator rights
     * 
     * @param array $options For channels
     * @return array|null Rights info
     */
    public function getMyDefaultAdministratorRights(array $options = []): ?array
    {
        $result = $this->request('getMyDefaultAdministratorRights', $options);
        return $result['result'] ?? null;
    }

    /**
     * Set my commands
     * 
     * @param array $commands Commands array
     * @param array $options Scope, language code
     * @return bool Success
     */
    public function setMyCommands(array $commands, array $options = []): bool
    {
        $data = array_merge([
            'commands' => json_encode($commands)
        ], $options);

        $result = $this->request('setMyCommands', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Delete my commands
     * 
     * @param array $options Scope, language code
     * @return bool Success
     */
    public function deleteMyCommands(array $options = []): bool
    {
        $result = $this->request('deleteMyCommands', $options);
        return $result['ok'] ?? false;
    }

    /**
     * Get my commands
     * 
     * @param array $options Scope, language code
     * @return array|null Commands list
     */
    public function getMyCommands(array $options = []): ?array
    {
        $result = $this->request('getMyCommands', $options);
        return $result['result'] ?? null;
    }

    /**
     * Set my name
     * 
     * @param string $name Bot name
     * @param array $options Language code
     * @return bool Success
     */
    public function setMyName(string $name, array $options = []): bool
    {
        $data = array_merge(['name' => $name], $options);
        $result = $this->request('setMyName', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Get my name
     * 
     * @param array $options Language code
     * @return array|null Name info
     */
    public function getMyName(array $options = []): ?array
    {
        $result = $this->request('getMyName', $options);
        return $result['result'] ?? null;
    }

    /**
     * Set my description
     * 
     * @param string $description Bot description
     * @param array $options Language code
     * @return bool Success
     */
    public function setMyDescription(string $description, array $options = []): bool
    {
        $data = array_merge(['description' => $description], $options);
        $result = $this->request('setMyDescription', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Get my description
     * 
     * @param array $options Language code
     * @return array|null Description info
     */
    public function getMyDescription(array $options = []): ?array
    {
        $result = $this->request('getMyDescription', $options);
        return $result['result'] ?? null;
    }

    /**
     * Set my short name
     * 
     * @param string $shortName Bot short name
     * @param array $options Language code
     * @return bool Success
     */
    public function setMyShortName(string $shortName, array $options = []): bool
    {
        $data = array_merge(['short_name' => $shortName], $options);
        $result = $this->request('setMyShortName', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Get my short name
     * 
     * @param array $options Language code
     * @return array|null Short name info
     */
    public function getMyShortName(array $options = []): ?array
    {
        $result = $this->request('getMyShortName', $options);
        return $result['result'] ?? null;
    }

    // ========================================================================
    // STICKER METHODS
    // ========================================================================

    /**
     * Get sticker set
     * 
     * @param string $name Sticker set name
     * @return array|null Sticker set info
     */
    public function getStickerSet(string $name): ?array
    {
        $result = $this->request('getStickerSet', ['name' => $name]);
        return $result['result'] ?? null;
    }

    /**
     * Get custom emoji stickers
     * 
     * @param array $options Custom emoji IDs
     * @return array|null Stickers list
     */
    public function getCustomEmojiStickers(array $options = []): ?array
    {
        $result = $this->request('getCustomEmojiStickers', $options);
        return $result['result'] ?? null;
    }

    /**
     * Upload sticker file
     * 
     * @param int $userId User ID
     * @param \CURLFile $sticker Sticker file
     * @param string $stickerType Sticker type (regular, mask, custom_emoji)
     * @return array|null File info
     */
    public function uploadStickerFile(int $userId, \CURLFile $sticker, string $stickerType = 'regular'): ?array
    {
        $result = $this->request('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => $sticker,
            'sticker_type' => $stickerType
        ]);
        return $result['result'] ?? null;
    }

    /**
     * Create new sticker set
     * 
     * @param int $userId User ID
     * @param string $name Sticker set name
     * @param string $title Sticker set title
     * @param array $stickers Stickers array
     * @param array $options Sticker type, needs repainting
     * @return bool Success
     */
    public function createNewStickerSet(int $userId, string $name, string $title, array $stickers, array $options = []): bool
    {
        $data = array_merge([
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'stickers' => json_encode($stickers)
        ], $options);

        $result = $this->request('createNewStickerSet', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Add sticker to set
     * 
     * @param string $name Sticker set name
     * @param int $userId User ID
     * @param array $sticker Sticker info
     * @return bool Success
     */
    public function addStickerToSet(string $name, int $userId, array $sticker): bool
    {
        $result = $this->request('addStickerToSet', [
            'name' => $name,
            'user_id' => $userId,
            'sticker' => json_encode($sticker)
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker position in set
     * 
     * @param string $sticker Sticker file_id
     * @param int $position Position
     * @return bool Success
     */
    public function setStickerPositionInSet(string $sticker, int $position): bool
    {
        $result = $this->request('setStickerPositionInSet', [
            'sticker' => $sticker,
            'position' => $position
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Delete sticker from set
     * 
     * @param string $sticker Sticker file_id
     * @return bool Success
     */
    public function deleteStickerFromSet(string $sticker): bool
    {
        $result = $this->request('deleteStickerFromSet', ['sticker' => $sticker]);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker emoji in set
     * 
     * @param string $sticker Sticker file_id
     * @param string $emoji Emoji
     * @return bool Success
     */
    public function setStickerEmojiInSet(string $sticker, string $emoji): bool
    {
        $result = $this->request('setStickerEmojiInSet', [
            'sticker' => $sticker,
            'emoji' => $emoji
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker keywords
     * 
     * @param string $sticker Sticker file_id
     * @param array $keywords Keywords array
     * @return bool Success
     */
    public function setStickerKeywords(string $sticker, array $keywords = []): bool
    {
        $data = ['sticker' => $sticker];
        if (!empty($keywords)) {
            $data['keywords'] = json_encode($keywords);
        }

        $result = $this->request('setStickerKeywords', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker mask position
     * 
     * @param string $sticker Sticker file_id
     * @param array $maskPosition Mask position
     * @return bool Success
     */
    public function setStickerMaskPosition(string $sticker, array $maskPosition): bool
    {
        $result = $this->request('setStickerMaskPosition', [
            'sticker' => $sticker,
            'mask_position' => json_encode($maskPosition)
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker set title
     * 
     * @param string $name Sticker set name
     * @param string $title New title
     * @return bool Success
     */
    public function setStickerSetTitle(string $name, string $title): bool
    {
        $result = $this->request('setStickerSetTitle', [
            'name' => $name,
            'title' => $title
        ]);
        return $result['ok'] ?? false;
    }

    /**
     * Set sticker set thumbnail
     * 
     * @param string $name Sticker set name
     * @param int $userId User ID
     * @param \CURLFile|null $thumbnail Thumbnail file
     * @param string $format Thumbnail format
     * @return bool Success
     */
    public function setStickerSetThumbnail(string $name, int $userId, ?\CURLFile $thumbnail = null, string $format = 'static'): bool
    {
        $data = [
            'name' => $name,
            'user_id' => $userId,
            'format' => $format
        ];
        
        if ($thumbnail !== null) {
            $data['thumbnail'] = $thumbnail;
        }

        $result = $this->request('setStickerSetThumbnail', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Set custom emoji sticker set thumbnail
     * 
     * @param string $name Sticker set name
     * @param string|null $customEmojiId Custom emoji ID
     * @return bool Success
     */
    public function setCustomEmojiStickerSetThumbnail(string $name, ?string $customEmojiId = null): bool
    {
        $data = ['name' => $name];
        if ($customEmojiId !== null) {
            $data['custom_emoji_id'] = $customEmojiId;
        }

        $result = $this->request('setCustomEmojiStickerSetThumbnail', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Delete sticker set
     * 
     * @param string $name Sticker set name
     * @return bool Success
     */
    public function deleteStickerSet(string $name): bool
    {
        $result = $this->request('deleteStickerSet', ['name' => $name]);
        return $result['ok'] ?? false;
    }

    // ========================================================================
    // FILE METHODS
    // ========================================================================

    /**
     * Get file info
     * 
     * @param string $fileId File ID
     * @return array|null File information
     */
    public function getFile(string $fileId): ?array
    {
        $result = $this->request('getFile', ['file_id' => $fileId]);
        return $result['result'] ?? null;
    }

    /**
     * Get file download URL
     * 
     * @param string $fileId File ID
     * @return string|null Download URL
     */
    public function getFileUrl(string $fileId): ?string
    {
        $file = $this->getFile($fileId);
        if ($file && isset($file['file_path'])) {
            return $this->baseUrl . $this->token . '/file/' . $file['file_path'];
        }
        return null;
    }

    /**
     * Download file
     * 
     * @param string $fileId File ID
     * @param string $localPath Local path to save
     * @return bool Success
     */
    public function downloadFile(string $fileId, string $localPath): bool
    {
        $url = $this->getFileUrl($fileId);
        if (!$url) {
            return false;
        }

        $content = file_get_contents($url);
        if ($content === false) {
            return false;
        }

        return file_put_contents($localPath, $content) !== false;
    }

    // ========================================================================
    // GAME METHODS
    // ========================================================================

    /**
     * Set game score
     * 
     * @param int $userId User ID
     * @param int $score Score value
     * @param array $options Chat ID, message ID, inline message ID, etc.
     * @return array|null Response
     */
    public function setGameScore(int $userId, int $score, array $options = []): ?array
    {
        $data = array_merge([
            'user_id' => $userId,
            'score' => $score
        ], $options);

        return $this->request('setGameScore', $data);
    }

    /**
     * Get game high scores
     * 
     * @param int $userId User ID
     * @param array $options Chat ID, message ID, or inline message ID
     * @return array|null High scores list
     */
    public function getGameHighScores(int $userId, array $options = []): ?array
    {
        $data = array_merge(['user_id' => $userId], $options);
        $result = $this->request('getGameHighScores', $data);
        return $result['result'] ?? null;
    }

    // ========================================================================
    // USER PROFILE METHODS
    // ========================================================================

    /**
     * Get user profile photos
     * 
     * @param int $userId User ID
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array|null Profile photos
     */
    public function getUserProfilePhotos(int $userId, int $offset = 0, int $limit = 100): ?array
    {
        $result = $this->request('getUserProfilePhotos', [
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit
        ]);
        return $result['result'] ?? null;
    }

    // ========================================================================
    // HELPER METHODS FOR EXTRACTING DATA FROM UPDATES
    // ========================================================================

    /**
     * Get message text
     * 
     * @return string|null Message text
     */
    public function text(): ?string
    {
        if (!$this->update) return null;
        
        if (isset($this->update['message']['text'])) {
            return $this->update['message']['text'];
        }
        
        if (isset($this->update['edited_message']['text'])) {
            return $this->update['edited_message']['text'];
        }
        
        if (isset($this->update['callback_query']['message']['text'])) {
            return $this->update['callback_query']['message']['text'];
        }
        
        return null;
    }

    /**
     * Get message caption
     * 
     * @return string|null Message caption
     */
    public function caption(): ?string
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        if ($message && isset($message['caption'])) {
            return $message['caption'];
        }
        
        return null;
    }

    /**
     * Get chat ID
     * 
     * @return int|null Chat ID
     */
    public function chatId(): ?int
    {
        if (!$this->update) return null;
        
        if (isset($this->update['message']['chat']['id'])) {
            return $this->update['message']['chat']['id'];
        }
        
        if (isset($this->update['edited_message']['chat']['id'])) {
            return $this->update['edited_message']['chat']['id'];
        }
        
        if (isset($this->update['callback_query']['message']['chat']['id'])) {
            return $this->update['callback_query']['message']['chat']['id'];
        }
        
        if (isset($this->update['channel_post']['chat']['id'])) {
            return $this->update['channel_post']['chat']['id'];
        }
        
        if (isset($this->update['inline_query']['from']['id'])) {
            return $this->update['inline_query']['from']['id'];
        }
        
        return null;
    }

    /**
     * Get message ID
     * 
     * @return int|null Message ID
     */
    public function messageId(): ?int
    {
        if (!$this->update) return null;
        
        if (isset($this->update['message']['message_id'])) {
            return $this->update['message']['message_id'];
        }
        
        if (isset($this->update['edited_message']['message_id'])) {
            return $this->update['edited_message']['message_id'];
        }
        
        if (isset($this->update['callback_query']['message']['message_id'])) {
            return $this->update['callback_query']['message']['message_id'];
        }
        
        return null;
    }

    /**
     * Get user ID
     * 
     * @return int|null User ID
     */
    public function userId(): ?int
    {
        if (!$this->update) return null;
        
        if (isset($this->update['message']['from']['id'])) {
            return $this->update['message']['from']['id'];
        }
        
        if (isset($this->update['edited_message']['from']['id'])) {
            return $this->update['edited_message']['from']['id'];
        }
        
        if (isset($this->update['callback_query']['from']['id'])) {
            return $this->update['callback_query']['from']['id'];
        }
        
        if (isset($this->update['inline_query']['from']['id'])) {
            return $this->update['inline_query']['from']['id'];
        }
        
        return null;
    }

    /**
     * Get callback query ID
     * 
     * @return string|null Callback query ID
     */
    public function callbackId(): ?string
    {
        if (!$this->update) return null;
        return $this->update['callback_query']['id'] ?? null;
    }

    /**
     * Get callback data
     * 
     * @return string|null Callback data
     */
    public function callbackData(): ?string
    {
        if (!$this->update) return null;
        return $this->update['callback_query']['data'] ?? null;
    }

    /**
     * Get inline query ID
     * 
     * @return string|null Inline query ID
     */
    public function inlineQueryId(): ?string
    {
        if (!$this->update) return null;
        return $this->update['inline_query']['id'] ?? null;
    }

    /**
     * Get inline query
     * 
     * @return string|null Inline query text
     */
    public function inlineQuery(): ?string
    {
        if (!$this->update) return null;
        return $this->update['inline_query']['query'] ?? null;
    }

    /**
     * Get chosen inline result
     * 
     * @return array|null Chosen inline result
     */
    public function chosenInlineResult(): ?array
    {
        return $this->update['chosen_inline_result'] ?? null;
    }

    /**
     * Get shipping query
     * 
     * @return array|null Shipping query
     */
    public function shippingQuery(): ?array
    {
        return $this->update['shipping_query'] ?? null;
    }

    /**
     * Get pre-checkout query
     * 
     * @return array|null Pre-checkout query
     */
    public function preCheckoutQuery(): ?array
    {
        return $this->update['pre_checkout_query'] ?? null;
    }

    /**
     * Get update type
     * 
     * @return string|null Update type
     */
    public function getUpdateType(): ?string
    {
        if (!$this->update) return null;
        
        if (isset($this->update['message'])) return self::UPDATE_MESSAGE;
        if (isset($this->update['edited_message'])) return self::UPDATE_EDITED_MESSAGE;
        if (isset($this->update['channel_post'])) return self::UPDATE_CHANNEL_POST;
        if (isset($this->update['edited_channel_post'])) return self::UPDATE_EDITED_CHANNEL_POST;
        if (isset($this->update['inline_query'])) return self::UPDATE_INLINE_QUERY;
        if (isset($this->update['chosen_inline_result'])) return self::UPDATE_CHOSEN_INLINE_RESULT;
        if (isset($this->update['callback_query'])) return self::UPDATE_CALLBACK_QUERY;
        if (isset($this->update['shipping_query'])) return self::UPDATE_SHIPPING_QUERY;
        if (isset($this->update['pre_checkout_query'])) return self::UPDATE_PRE_CHECKOUT_QUERY;
        if (isset($this->update['poll'])) return self::UPDATE_POLL;
        if (isset($this->update['poll_answer'])) return self::UPDATE_POLL_ANSWER;
        if (isset($this->update['my_chat_member'])) return self::UPDATE_MY_CHAT_MEMBER;
        if (isset($this->update['chat_member'])) return self::UPDATE_CHAT_MEMBER;
        if (isset($this->update['chat_join_request'])) return self::UPDATE_CHAT_JOIN_REQUEST;
        
        return null;
    }

    /**
     * Get message type
     * 
     * @return string|null Message type
     */
    public function getMessageType(): ?string
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? $this->update['channel_post'] ?? null;
        if (!$message) return null;

        if (isset($message['text'])) return self::MESSAGE_TYPE_TEXT;
        if (isset($message['photo'])) return self::MESSAGE_TYPE_PHOTO;
        if (isset($message['video'])) return self::MESSAGE_TYPE_VIDEO;
        if (isset($message['audio'])) return self::MESSAGE_TYPE_AUDIO;
        if (isset($message['voice'])) return self::MESSAGE_TYPE_VOICE;
        if (isset($message['document'])) return self::MESSAGE_TYPE_DOCUMENT;
        if (isset($message['sticker'])) return self::MESSAGE_TYPE_STICKER;
        if (isset($message['animation'])) return self::MESSAGE_TYPE_ANIMATION;
        if (isset($message['video_note'])) return self::MESSAGE_TYPE_VIDEO_NOTE;
        if (isset($message['contact'])) return self::MESSAGE_TYPE_CONTACT;
        if (isset($message['location'])) return self::MESSAGE_TYPE_LOCATION;
        if (isset($message['venue'])) return self::MESSAGE_TYPE_VENUE;
        if (isset($message['dice'])) return self::MESSAGE_TYPE_DICE;
        if (isset($message['invoice'])) return self::MESSAGE_TYPE_INVOICE;
        if (isset($message['successful_payment'])) return self::MESSAGE_TYPE_SUCCESSFUL_PAYMENT;

        return null;
    }

    /**
     * Check if message is from group
     * 
     * @return bool True if from group
     */
    public function isGroup(): bool
    {
        if (!$this->update) return false;
        
        $chat = $this->update['message']['chat'] ?? $this->update['edited_message']['chat'] ?? null;
        if (!$chat) return false;
        
        return in_array($chat['type'], ['group', 'supergroup']);
    }

    /**
     * Check if message is from private chat
     * 
     * @return bool True if from private chat
     */
    public function isPrivate(): bool
    {
        if (!$this->update) return false;
        
        $chat = $this->update['message']['chat'] ?? $this->update['edited_message']['chat'] ?? null;
        if (!$chat) return false;
        
        return $chat['type'] === 'private';
    }

    /**
     * Check if message is from channel
     * 
     * @return bool True if from channel
     */
    public function isChannel(): bool
    {
        if (!$this->update) return false;
        
        $chat = $this->update['channel_post']['chat'] ?? null;
        if (!$chat) return false;
        
        return $chat['type'] === 'channel';
    }

    /**
     * Get first name
     * 
     * @return string|null First name
     */
    public function firstName(): ?string
    {
        if (!$this->update) return null;
        
        $from = $this->update['message']['from'] ?? 
                $this->update['edited_message']['from'] ?? 
                $this->update['callback_query']['from'] ?? 
                $this->update['inline_query']['from'] ?? null;
        
        return $from['first_name'] ?? null;
    }

    /**
     * Get last name
     * 
     * @return string|null Last name
     */
    public function lastName(): ?string
    {
        if (!$this->update) return null;
        
        $from = $this->update['message']['from'] ?? 
                $this->update['edited_message']['from'] ?? 
                $this->update['callback_query']['from'] ?? 
                $this->update['inline_query']['from'] ?? null;
        
        return $from['last_name'] ?? null;
    }

    /**
     * Get username
     * 
     * @return string|null Username
     */
    public function username(): ?string
    {
        if (!$this->update) return null;
        
        $from = $this->update['message']['from'] ?? 
                $this->update['edited_message']['from'] ?? 
                $this->update['callback_query']['from'] ?? 
                $this->update['inline_query']['from'] ?? null;
        
        return $from['username'] ?? null;
    }

    /**
     * Get language code
     * 
     * @return string|null Language code
     */
    public function languageCode(): ?string
    {
        if (!$this->update) return null;
        
        $from = $this->update['message']['from'] ?? 
                $this->update['edited_message']['from'] ?? 
                $this->update['callback_query']['from'] ?? 
                $this->update['inline_query']['from'] ?? null;
        
        return $from['language_code'] ?? null;
    }

    /**
     * Get location
     * 
     * @return array|null Location data
     */
    public function location(): ?array
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        return $message['location'] ?? null;
    }

    /**
     * Get contact
     * 
     * @return array|null Contact data
     */
    public function contact(): ?array
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        return $message['contact'] ?? null;
    }

    /**
     * Get entities
     * 
     * @return array|null Message entities
     */
    public function entities(): ?array
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        return $message['entities'] ?? null;
    }

    /**
     * Get reply to message
     * 
     * @return array|null Reply to message
     */
    public function replyToMessage(): ?array
    {
        if (!$this->update) return null;
        
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        return $message['reply_to_message'] ?? null;
    }

    /**
     * Get update ID
     * 
     * @return int|null Update ID
     */
    public function updateId(): ?int
    {
        return $this->update['update_id'] ?? null;
    }

    /**
     * Serve update from webhook
     * 
     * @return array|null Update data
     */
    public function serveUpdate(): ?array
    {
        $input = file_get_contents('php://input');
        if ($input) {
            $update = json_decode($input, true);
            if ($update) {
                $this->setUpdate($update);
                return $update;
            }
        }
        return null;
    }

    /**
     * Send success response for webhook
     * 
     * @return void
     */
    public function respondSuccess(): void
    {
        http_response_code(200);
        echo json_encode(['status' => 'success']);
        exit;
    }
}
