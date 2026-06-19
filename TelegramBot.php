<?php
/**
 * Complete Telegram Bot API Class
 * 
 * A comprehensive PHP class implementing all methods from the Telegram Bot API
 * @see https://core.telegram.org/bots/api
 */

class TelegramBot {
    private string $botToken;
    private string $apiUrl = 'https://api.telegram.org/bot';
    
    /**
     * Constructor
     * 
     * @param string $botToken Your Telegram bot token from @BotFather
     */
    public function __construct(string $botToken) {
        $this->botToken = $botToken;
    }
    
    /**
     * Make API request to Telegram
     * 
     * @param string $method API method name
     * @param array $data Parameters to send
     * @return array|false Response data or false on failure
     */
    private function makeRequest(string $method, array $data = []) {
        $url = $this->apiUrl . $this->botToken . '/' . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Make API request with file upload
     * 
     * @param string $method API method name
     * @param array $data Parameters to send
     * @return array|false Response data or false on failure
     */
    private function makeFileRequest(string $method, array $data = []) {
        $url = $this->apiUrl . $this->botToken . '/' . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    // ==========================================
    // GETTING UPDATES
    // ==========================================
    
    /**
     * Use getUpdates to receive incoming updates using long polling
     * 
     * @param int|null $offset Identifier of the first update to be returned
     * @param int|null $limit Limits the number of updates to be retrieved
     * @param int|null $timeout Timeout in seconds for long polling
     * @param array|null $allowedUpdates List of update types to receive
     * @return array|false Updates or false on failure
     */
    public function getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null, ?array $allowedUpdates = null) {
        $data = [];
        
        if ($offset !== null) $data['offset'] = $offset;
        if ($limit !== null) $data['limit'] = $limit;
        if ($timeout !== null) $data['timeout'] = $timeout;
        if ($allowedUpdates !== null) $data['allowed_updates'] = json_encode($allowedUpdates);
        
        return $this->makeRequest('getUpdates', $data);
    }
    
    /**
     * Specify a url and receive incoming updates via an outgoing webhook
     * 
     * @param string $url HTTPS URL to receive updates
     * @param string|null $certificate Upload your public key certificate
     * @param string|null $ipAddress Fixed IP address for webhook
     * @param int|null $maxConnections Maximum allowed number of simultaneous HTTPS connections
     * @param array|null $allowedUpdates List of update types to receive
     * @param bool|null $dropPendingUpdates Drop pending updates
     * @param string|null $secretToken Secret token for webhook verification
     * @return array|false Response or false on failure
     */
    public function setWebhook(string $url, ?string $certificate = null, ?string $ipAddress = null, 
                               ?int $maxConnections = null, ?array $allowedUpdates = null, 
                               ?bool $dropPendingUpdates = null, ?string $secretToken = null) {
        $data = ['url' => $url];
        
        if ($certificate) $data['certificate'] = new CURLFile($certificate);
        if ($ipAddress) $data['ip_address'] = $ipAddress;
        if ($maxConnections) $data['max_connections'] = $maxConnections;
        if ($allowedUpdates) $data['allowed_updates'] = json_encode($allowedUpdates);
        if ($dropPendingUpdates) $data['drop_pending_updates'] = $dropPendingUpdates;
        if ($secretToken) $data['secret_token'] = $secretToken;
        
        return $this->makeRequest('setWebhook', $data);
    }
    
    /**
     * Remove webhook integration
     * 
     * @param bool|null $dropPendingUpdates Drop pending updates
     * @return array|false Response or false on failure
     */
    public function deleteWebhook(?bool $dropPendingUpdates = null) {
        $data = [];
        if ($dropPendingUpdates) $data['drop_pending_updates'] = $dropPendingUpdates;
        
        return $this->makeRequest('deleteWebhook', $data);
    }
    
    /**
     * Get current webhook status
     * 
     * @return array|false Webhook info or false on failure
     */
    public function getWebhookInfo() {
        return $this->makeRequest('getWebhookInfo');
    }
    
    // ==========================================
    // AVAILABLE METHODS - GENERAL
    // ==========================================
    
    /**
     * Get basic information about the bot
     * 
     * @return array|false User object or false on failure
     */
    public function getMe() {
        return $this->makeRequest('getMe');
    }
    
    /**
     * Log out from the cloud Bot API server
     * 
     * @return array|false Response or false on failure
     */
    public function logOut() {
        return $this->makeRequest('logOut');
    }
    
    /**
     * Close the bot instance before moving it from one local server to another
     * 
     * @return array|false Response or false on failure
     */
    public function close() {
        return $this->makeRequest('close');
    }
    
    /**
     * Send text messages
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param string|null $parseMode Parse mode (HTML, Markdown, MarkdownV2)
     * @param array|null $entities List of special entities
     * @param bool|null $disableWebPagePreview Disable link previews
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content from forwarding
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Inline keyboard, reply keyboard, or remove keyboard
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @param int|null $linkPreviewOptions Link preview options
     * @return array|false Message or false on failure
     */
    public function sendMessage($chatId, string $text, ?string $parseMode = null, ?array $entities = null,
                                ?bool $disableWebPagePreview = null, ?bool $disableNotification = null,
                                ?bool $protectContent = null, ?int $replyToMessageId = null,
                                ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                ?string $businessConnectionId = null, ?string $messageEffectId = null,
                                ?array $linkPreviewOptions = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($entities) $data['entities'] = json_encode($entities);
        if ($disableWebPagePreview) $data['disable_web_page_preview'] = $disableWebPagePreview;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        if ($linkPreviewOptions) $data['link_preview_options'] = json_encode($linkPreviewOptions);
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    /**
     * Forward messages of any kind
     * 
     * @param int|string $chatId Destination chat ID
     * @param int|string $fromChatId Source chat ID
     * @param int $messageId Message ID to forward
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content from forwarding
     * @return array|false Message or false on failure
     */
    public function forwardMessage($chatId, $fromChatId, int $messageId, ?bool $disableNotification = null, 
                                   ?bool $protectContent = null) {
        $data = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ];
        
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        
        return $this->makeRequest('forwardMessage', $data);
    }
    
    /**
     * Forward multiple messages simultaneously
     * 
     * @param int|string $chatId Destination chat ID
     * @param int|string $fromChatId Source chat ID
     * @param array $messageIds List of message IDs to forward
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content from forwarding
     * @return array|false Array of MessageId objects or false on failure
     */
    public function forwardMessages($chatId, $fromChatId, array $messageIds, ?bool $disableNotification = null,
                                    ?bool $protectContent = null) {
        $data = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds)
        ];
        
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        
        return $this->makeRequest('forwardMessages', $data);
    }
    
    /**
     * Copy messages of any kind
     * 
     * @param int|string $chatId Destination chat ID
     * @param int|string $fromChatId Source chat ID
     * @param int $messageId Message ID to copy
     * @param string|null $caption New caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @return array|false MessageId or false on failure
     */
    public function copyMessage($chatId, $fromChatId, int $messageId, ?string $caption = null,
                                ?string $parseMode = null, ?array $captionEntities = null,
                                ?bool $disableNotification = null, ?bool $protectContent = null,
                                ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                                ?array $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ];
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        
        return $this->makeRequest('copyMessage', $data);
    }
    
    /**
     * Copy multiple messages simultaneously
     * 
     * @param int|string $chatId Destination chat ID
     * @param int|string $fromChatId Source chat ID
     * @param array $messageIds List of message IDs to copy
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @return array|false Array of MessageId objects or false on failure
     */
    public function copyMessages($chatId, $fromChatId, array $messageIds, ?bool $disableNotification = null,
                                 ?bool $protectContent = null, ?int $replyToMessageId = null,
                                 ?bool $allowSendingWithoutReply = null) {
        $data = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds)
        ];
        
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        
        return $this->makeRequest('copyMessages', $data);
    }
    
    /**
     * Send photos
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $photo Photo file ID or URL or file path
     * @param string|null $caption Photo caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $hasSpoiler Spoiler for photo
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendPhoto($chatId, string $photo, ?string $caption = null, ?string $parseMode = null,
                              ?array $captionEntities = null, ?bool $hasSpoiler = null,
                              ?bool $disableNotification = null, ?bool $protectContent = null,
                              ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                              ?array $replyMarkup = null, ?string $businessConnectionId = null,
                              ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        // Check if photo is a file path (local file) or URL/file_id
        if (file_exists($photo)) {
            $data['photo'] = new CURLFile($photo);
        } else {
            $data['photo'] = $photo;
        }
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($hasSpoiler) $data['has_spoiler'] = $hasSpoiler;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendPhoto', $data);
    }
    
    /**
     * Send audio files
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $audio Audio file ID or URL or file path
     * @param string|null $caption Audio caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param int|null $duration Duration in seconds
     * @param string|null $performer Performer name
     * @param string|null $title Track name
     * @param string|null $thumb Thumbnail file path
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendAudio($chatId, string $audio, ?string $caption = null, ?string $parseMode = null,
                              ?array $captionEntities = null, ?int $duration = null,
                              ?string $performer = null, ?string $title = null, ?string $thumb = null,
                              ?bool $disableNotification = null, ?bool $protectContent = null,
                              ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                              ?array $replyMarkup = null, ?string $businessConnectionId = null,
                              ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($audio)) {
            $data['audio'] = new CURLFile($audio);
        } else {
            $data['audio'] = $audio;
        }
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($duration) $data['duration'] = $duration;
        if ($performer) $data['performer'] = $performer;
        if ($title) $data['title'] = $title;
        if ($thumb && file_exists($thumb)) $data['thumbnail'] = new CURLFile($thumb);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendAudio', $data);
    }
    
    /**
     * Send general files
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $document Document file ID or URL or file path
     * @param string|null $caption Document caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $disableContentTypeDetection Disable content type detection
     * @param string|null $thumb Thumbnail file path
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendDocument($chatId, string $document, ?string $caption = null, ?string $parseMode = null,
                                 ?array $captionEntities = null, ?bool $disableContentTypeDetection = null,
                                 ?string $thumb = null, ?bool $disableNotification = null,
                                 ?bool $protectContent = null, ?int $replyToMessageId = null,
                                 ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                 ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($document)) {
            $data['document'] = new CURLFile($document);
        } else {
            $data['document'] = $document;
        }
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($disableContentTypeDetection) $data['disable_content_type_detection'] = $disableContentTypeDetection;
        if ($thumb && file_exists($thumb)) $data['thumbnail'] = new CURLFile($thumb);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendDocument', $data);
    }
    
    /**
     * Send video files
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $video Video file ID or URL or file path
     * @param int|null $duration Duration in seconds
     * @param int|null $width Video width
     * @param int|null $height Video height
     * @param string|null $caption Video caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $hasSpoiler Spoiler for video
     * @param bool|null $supportsStreaming Pass True if video supports streaming
     * @param string|null $thumb Thumbnail file path
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendVideo($chatId, string $video, ?int $duration = null, ?int $width = null,
                              ?int $height = null, ?string $caption = null, ?string $parseMode = null,
                              ?array $captionEntities = null, ?bool $hasSpoiler = null,
                              ?bool $supportsStreaming = null, ?string $thumb = null,
                              ?bool $disableNotification = null, ?bool $protectContent = null,
                              ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                              ?array $replyMarkup = null, ?string $businessConnectionId = null,
                              ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($video)) {
            $data['video'] = new CURLFile($video);
        } else {
            $data['video'] = $video;
        }
        
        if ($duration) $data['duration'] = $duration;
        if ($width) $data['width'] = $width;
        if ($height) $data['height'] = $height;
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($hasSpoiler) $data['has_spoiler'] = $hasSpoiler;
        if ($supportsStreaming) $data['supports_streaming'] = $supportsStreaming;
        if ($thumb && file_exists($thumb)) $data['thumbnail'] = new CURLFile($thumb);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendVideo', $data);
    }
    
    /**
     * Send animation files (GIF or H.264/MPEG-4 AVC video without sound)
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $animation Animation file ID or URL or file path
     * @param int|null $duration Duration in seconds
     * @param int|null $width Animation width
     * @param int|null $height Animation height
     * @param string|null $caption Animation caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $hasSpoiler Spoiler for animation
     * @param string|null $thumb Thumbnail file path
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendAnimation($chatId, string $animation, ?int $duration = null, ?int $width = null,
                                  ?int $height = null, ?string $caption = null, ?string $parseMode = null,
                                  ?array $captionEntities = null, ?bool $hasSpoiler = null,
                                  ?string $thumb = null, ?bool $disableNotification = null,
                                  ?bool $protectContent = null, ?int $replyToMessageId = null,
                                  ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                  ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($animation)) {
            $data['animation'] = new CURLFile($animation);
        } else {
            $data['animation'] = $animation;
        }
        
        if ($duration) $data['duration'] = $duration;
        if ($width) $data['width'] = $width;
        if ($height) $data['height'] = $height;
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($hasSpoiler) $data['has_spoiler'] = $hasSpoiler;
        if ($thumb && file_exists($thumb)) $data['thumbnail'] = new CURLFile($thumb);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendAnimation', $data);
    }
    
    /**
     * Send voice audio files
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $voice Voice file ID or URL or file path
     * @param string|null $caption Voice caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param int|null $duration Duration in seconds
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendVoice($chatId, string $voice, ?string $caption = null, ?string $parseMode = null,
                              ?array $captionEntities = null, ?int $duration = null,
                              ?bool $disableNotification = null, ?bool $protectContent = null,
                              ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                              ?array $replyMarkup = null, ?string $businessConnectionId = null,
                              ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($voice)) {
            $data['voice'] = new CURLFile($voice);
        } else {
            $data['voice'] = $voice;
        }
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($duration) $data['duration'] = $duration;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendVoice', $data);
    }
    
    /**
     * Send video messages
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $videoNote Video file ID or URL or file path
     * @param int|null $duration Duration in seconds
     * @param int|null $length Video dimension
     * @param string|null $thumb Thumbnail file path
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendVideoNote($chatId, string $videoNote, ?int $duration = null, ?int $length = null,
                                  ?string $thumb = null, ?bool $disableNotification = null,
                                  ?bool $protectContent = null, ?int $replyToMessageId = null,
                                  ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                  ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if (file_exists($videoNote)) {
            $data['video_note'] = new CURLFile($videoNote);
        } else {
            $data['video_note'] = $videoNote;
        }
        
        if ($duration) $data['duration'] = $duration;
        if ($length) $data['length'] = $length;
        if ($thumb && file_exists($thumb)) $data['thumbnail'] = new CURLFile($thumb);
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeFileRequest('sendVideoNote', $data);
    }
    
    /**
     * Send paid media
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $starCount Number of stars required
     * @param array $media List of media to send
     * @param string|null $caption Media caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $showCaptionAboveMedia Show caption above media
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $payload Bot-defined paid media payload
     * @return array|false Message or false on failure
     */
    public function sendPaidMedia($chatId, int $starCount, array $media, ?string $caption = null,
                                  ?string $parseMode = null, ?array $captionEntities = null,
                                  ?bool $showCaptionAboveMedia = null, ?bool $disableNotification = null,
                                  ?bool $protectContent = null, ?int $replyToMessageId = null,
                                  ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                  ?string $businessConnectionId = null, ?string $payload = null) {
        $data = [
            'chat_id' => $chatId,
            'star_count' => $starCount,
            'media' => json_encode($media)
        ];
        
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($showCaptionAboveMedia) $data['show_caption_above_media'] = $showCaptionAboveMedia;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($payload) $data['payload'] = $payload;
        
        return $this->makeRequest('sendPaidMedia', $data);
    }
    
    /**
     * Send multiple photos as an album
     * 
     * @param int|string $chatId Chat ID or username
     * @param array $media Array of InputMediaPhoto objects
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Array of Messages or false on failure
     */
    public function sendMediaGroup($chatId, array $media, ?bool $disableNotification = null,
                                   ?bool $protectContent = null, ?int $replyToMessageId = null,
                                   ?bool $allowSendingWithoutReply = null,
                                   ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = [
            'chat_id' => $chatId,
            'media' => json_encode($media)
        ];
        
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeRequest('sendMediaGroup', $data);
    }
    
    /**
     * Send point on the map
     * 
     * @param int|string $chatId Chat ID or username
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param float|null $horizontalAccuracy Accuracy in meters
     * @param int|null $livePeriod Period in seconds for live location
     * @param int|null $heading Direction of movement
     * @param int|null $proximityAlertRadius Proximity alert radius
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendLocation($chatId, float $latitude, float $longitude, ?float $horizontalAccuracy = null,
                                 ?int $livePeriod = null, ?int $heading = null, ?int $proximityAlertRadius = null,
                                 ?bool $disableNotification = null, ?bool $protectContent = null,
                                 ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                                 ?array $replyMarkup = null, ?string $businessConnectionId = null,
                                 ?string $messageEffectId = null) {
        $data = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        
        if ($horizontalAccuracy) $data['horizontal_accuracy'] = $horizontalAccuracy;
        if ($livePeriod) $data['live_period'] = $livePeriod;
        if ($heading) $data['heading'] = $heading;
        if ($proximityAlertRadius) $data['proximity_alert_radius'] = $proximityAlertRadius;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeRequest('sendLocation', $data);
    }
    
    /**
     * Send information about a venue
     * 
     * @param int|string $chatId Chat ID or username
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param string $title Venue name
     * @param string $address Venue address
     * @param string|null $foursquareId Foursquare identifier
     * @param string|null $foursquareType Foursquare type
     * @param string|null $googlePlaceId Google Places identifier
     * @param string|null $googlePlaceType Google Places type
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendVenue($chatId, float $latitude, float $longitude, string $title, string $address,
                              ?string $foursquareId = null, ?string $foursquareType = null,
                              ?string $googlePlaceId = null, ?string $googlePlaceType = null,
                              ?bool $disableNotification = null, ?bool $protectContent = null,
                              ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                              ?array $replyMarkup = null, ?string $businessConnectionId = null,
                              ?string $messageEffectId = null) {
        $data = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address
        ];
        
        if ($foursquareId) $data['foursquare_id'] = $foursquareId;
        if ($foursquareType) $data['foursquare_type'] = $foursquareType;
        if ($googlePlaceId) $data['google_place_id'] = $googlePlaceId;
        if ($googlePlaceType) $data['google_place_type'] = $googlePlaceType;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeRequest('sendVenue', $data);
    }
    
    /**
     * Send phone contacts
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $phoneNumber Contact's phone number
     * @param string $firstName Contact's first name
     * @param string|null $lastName Contact's last name
     * @param string|null $vCard Additional data in vCard format
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendContact($chatId, string $phoneNumber, string $firstName, ?string $lastName = null,
                                ?string $vCard = null, ?bool $disableNotification = null,
                                ?bool $protectContent = null, ?int $replyToMessageId = null,
                                ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                                ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName
        ];
        
        if ($lastName) $data['last_name'] = $lastName;
        if ($vCard) $data['vcard'] = $vCard;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeRequest('sendContact', $data);
    }
    
    /**
     * Send a poll
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $question Poll question
     * @param array $options List of answer options
     * @param bool|null $isAnonymous True if poll is anonymous
     * @param string|null $type Poll type (regular or quiz)
     * @param bool|null $allowsMultipleAnswers True if multiple answers allowed
     * @param int|null $correctOptionIdx 0-based index of correct option (for quiz)
     * @param string|null $explanation Explanation for correct option
     * @param string|null $explanationParseMode Explanation parse mode
     * @param array|null $explanationEntities Explanation entities
     * @param int|null $openPeriod Time in seconds to keep poll open
     * @param int|null $closeDate Unix timestamp for poll close date
     * @param bool|null $isClosed True if poll is closed
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @param string|null $questionParseMode Question parse mode
     * @param array|null $questionEntities Question entities
     * @return array|false Message or false on failure
     */
    public function sendPoll($chatId, string $question, array $options, ?bool $isAnonymous = null,
                             ?string $type = null, ?bool $allowsMultipleAnswers = null,
                             ?int $correctOptionIdx = null, ?string $explanation = null,
                             ?string $explanationParseMode = null, ?array $explanationEntities = null,
                             ?int $openPeriod = null, ?int $closeDate = null, ?bool $isClosed = null,
                             ?bool $disableNotification = null, ?bool $protectContent = null,
                             ?int $replyToMessageId = null, ?bool $allowSendingWithoutReply = null,
                             ?array $replyMarkup = null, ?string $businessConnectionId = null,
                             ?string $messageEffectId = null, ?string $questionParseMode = null,
                             ?array $questionEntities = null) {
        $data = [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($options)
        ];
        
        if ($isAnonymous !== null) $data['is_anonymous'] = $isAnonymous;
        if ($type) $data['type'] = $type;
        if ($allowsMultipleAnswers !== null) $data['allows_multiple_answers'] = $allowsMultipleAnswers;
        if ($correctOptionIdx !== null) $data['correct_option_id'] = $correctOptionIdx;
        if ($explanation) $data['explanation'] = $explanation;
        if ($explanationParseMode) $data['explanation_parse_mode'] = $explanationParseMode;
        if ($explanationEntities) $data['explanation_entities'] = json_encode($explanationEntities);
        if ($openPeriod) $data['open_period'] = $openPeriod;
        if ($closeDate) $data['close_date'] = $closeDate;
        if ($isClosed !== null) $data['is_closed'] = $isClosed;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        if ($questionParseMode) $data['question_parse_mode'] = $questionParseMode;
        if ($questionEntities) $data['question_entities'] = json_encode($questionEntities);
        
        return $this->makeRequest('sendPoll', $data);
    }
    
    /**
     * Send a dice (random value)
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|null $emoji Emoji on which the dice is based
     * @param bool|null $disableNotification Send silently
     * @param bool|null $protectContent Protect content
     * @param int|null $replyToMessageId Reply to message ID
     * @param bool|null $allowSendingWithoutReply Allow sending without reply
     * @param array|null $replyMarkup Reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @param string|null $messageEffectId Message effect ID
     * @return array|false Message or false on failure
     */
    public function sendDice($chatId, ?string $emoji = null, ?bool $disableNotification = null,
                             ?bool $protectContent = null, ?int $replyToMessageId = null,
                             ?bool $allowSendingWithoutReply = null, ?array $replyMarkup = null,
                             ?string $businessConnectionId = null, ?string $messageEffectId = null) {
        $data = ['chat_id' => $chatId];
        
        if ($emoji) $data['emoji'] = $emoji;
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($protectContent) $data['protect_content'] = $protectContent;
        if ($replyToMessageId) $data['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply) $data['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        if ($messageEffectId) $data['message_effect_id'] = $messageEffectId;
        
        return $this->makeRequest('sendDice', $data);
    }
    
    /**
     * Send chat action (typing, upload_photo, etc.)
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $action Action type (typing, upload_photo, record_video, etc.)
     * @param string|null $messageThreadId Thread ID for supergroups
     * @return array|false Response or false on failure
     */
    public function sendChatAction($chatId, string $action, ?int $messageThreadId = null) {
        $data = [
            'chat_id' => $chatId,
            'action' => $action
        ];
        
        if ($messageThreadId) $data['message_thread_id'] = $messageThreadId;
        
        return $this->makeRequest('sendChatAction', $data);
    }
    
    /**
     * Change the chosen appearance of a reaction
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $messageId Message ID to react to
     * @param array|null $reaction List of reactions to set
     * @param bool|null $isBig Use big emoji
     * @return array|false Response or false on failure
     */
    public function setMessageReaction($chatId, int $messageId, ?array $reaction = null, ?bool $isBig = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];
        
        if ($reaction) $data['reaction'] = json_encode($reaction);
        if ($isBig) $data['is_big'] = $isBig;
        
        return $this->makeRequest('setMessageReaction', $data);
    }
    
    /**
     * Get a list of profiles for a specified user
     * 
     * @param int $userId Target user ID
     * @param int|null $offset Number of results to skip
     * @param int|null $limit Maximum number of results
     * @return array|false UserProfilePhotos or false on failure
     */
    public function getUserProfilePhotos(int $userId, ?int $offset = null, ?int $limit = null) {
        $data = ['user_id' => $userId];
        
        if ($offset) $data['offset'] = $offset;
        if ($limit) $data['limit'] = $limit;
        
        return $this->makeRequest('getUserProfilePhotos', $data);
    }
    
    /**
     * Get basic information about a file
     * 
     * @param string $fileId File identifier
     * @return array|false File object or false on failure
     */
    public function getFile(string $fileId) {
        $data = ['file_id' => $fileId];
        
        return $this->makeRequest('getFile', $data);
    }
    
    /**
     * Ban a user in a group, supergroup or channel
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID to ban
     * @param int|null $untilDate Date when user will be unbanned
     * @param bool|null $revokeMessages Revoke all messages
     * @return array|false Response or false on failure
     */
    public function banChatMember($chatId, int $userId, ?int $untilDate = null, ?bool $revokeMessages = null) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        if ($untilDate) $data['until_date'] = $untilDate;
        if ($revokeMessages) $data['revoke_messages'] = $revokeMessages;
        
        return $this->makeRequest('banChatMember', $data);
    }
    
    /**
     * Unban a previously banned user
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID to unban
     * @param bool|null $onlyIfBanned Only unban if user is banned
     * @return array|false Response or false on failure
     */
    public function unbanChatMember($chatId, int $userId, ?bool $onlyIfBanned = null) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        if ($onlyIfBanned) $data['only_if_banned'] = $onlyIfBanned;
        
        return $this->makeRequest('unbanChatMember', $data);
    }
    
    /**
     * Restrict a user in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID to restrict
     * @param array $permissions New user permissions
     * @param bool|null $useIndependentChatPermissions Use independent permissions
     * @param int|null $untilDate Date when restrictions will be lifted
     * @return array|false Response or false on failure
     */
    public function restrictChatMember($chatId, int $userId, array $permissions,
                                       ?bool $useIndependentChatPermissions = null, ?int $untilDate = null) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions)
        ];
        
        if ($useIndependentChatPermissions) $data['use_independent_chat_permissions'] = $useIndependentChatPermissions;
        if ($untilDate) $data['until_date'] = $untilDate;
        
        return $this->makeRequest('restrictChatMember', $data);
    }
    
    /**
     * Promote or demote a user in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID to promote/demote
     * @param bool|null $isAnonymous True if admin is anonymous
     * @param bool|null $canManageChat Can manage chat
     * @param bool|null $canPostMessages Can post messages
     * @param bool|null $canEditMessages Can edit messages
     * @param bool|null $canDeleteMessages Can delete messages
     * @param bool|null $canManageVideoChats Can manage video chats
     * @param bool|null $canRestrictMembers Can restrict members
     * @param bool|null $canPromoteMembers Can promote admins
     * @param bool|null $canChangeInfo Can change chat info
     * @param bool|null $canInviteUsers Can invite users
     * @param bool|null $canPinMessages Can pin messages
     * @param bool|null $canManageTopics Can manage topics (supergroups only)
     * @return array|false Response or false on failure
     */
    public function promoteChatMember($chatId, int $userId, ?bool $isAnonymous = null,
                                      ?bool $canManageChat = null, ?bool $canPostMessages = null,
                                      ?bool $canEditMessages = null, ?bool $canDeleteMessages = null,
                                      ?bool $canManageVideoChats = null, ?bool $canRestrictMembers = null,
                                      ?bool $canPromoteMembers = null, ?bool $canChangeInfo = null,
                                      ?bool $canInviteUsers = null, ?bool $canPinMessages = null,
                                      ?bool $canManageTopics = null) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        if ($isAnonymous !== null) $data['is_anonymous'] = $isAnonymous;
        if ($canManageChat !== null) $data['can_manage_chat'] = $canManageChat;
        if ($canPostMessages !== null) $data['can_post_messages'] = $canPostMessages;
        if ($canEditMessages !== null) $data['can_edit_messages'] = $canEditMessages;
        if ($canDeleteMessages !== null) $data['can_delete_messages'] = $canDeleteMessages;
        if ($canManageVideoChats !== null) $data['can_manage_video_chats'] = $canManageVideoChats;
        if ($canRestrictMembers !== null) $data['can_restrict_members'] = $canRestrictMembers;
        if ($canPromoteMembers !== null) $data['can_promote_members'] = $canPromoteMembers;
        if ($canChangeInfo !== null) $data['can_change_info'] = $canChangeInfo;
        if ($canInviteUsers !== null) $data['can_invite_users'] = $canInviteUsers;
        if ($canPinMessages !== null) $data['can_pin_messages'] = $canPinMessages;
        if ($canManageTopics !== null) $data['can_manage_topics'] = $canManageTopics;
        
        return $this->makeRequest('promoteChatMember', $data);
    }
    
    /**
     * Set custom title for an administrator
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID
     * @param string $customTitle Custom title
     * @return array|false Response or false on failure
     */
    public function setChatAdministratorCustomTitle($chatId, int $userId, string $customTitle) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle
        ];
        
        return $this->makeRequest('setChatAdministratorCustomTitle', $data);
    }
    
    /**
     * Ban a channel chat in a supergroup or channel
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $senderChatId Sender chat ID to ban
     * @return array|false Response or false on failure
     */
    public function banChatSenderChat($chatId, int $senderChatId) {
        $data = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];
        
        return $this->makeRequest('banChatSenderChat', $data);
    }
    
    /**
     * Unban a previously banned channel chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $senderChatId Sender chat ID to unban
     * @return array|false Response or false on failure
     */
    public function unbanChatSenderChat($chatId, int $senderChatId) {
        $data = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];
        
        return $this->makeRequest('unbanChatSenderChat', $data);
    }
    
    /**
     * Set default chat permissions for all members
     * 
     * @param int|string $chatId Chat ID or username
     * @param array $permissions Default chat permissions
     * @param bool|null $useIndependentChatPermissions Use independent permissions
     * @return array|false Response or false on failure
     */
    public function setChatPermissions($chatId, array $permissions, ?bool $useIndependentChatPermissions = null) {
        $data = [
            'chat_id' => $chatId,
            'permissions' => json_encode($permissions)
        ];
        
        if ($useIndependentChatPermissions) $data['use_independent_chat_permissions'] = $useIndependentChatPermissions;
        
        return $this->makeRequest('setChatPermissions', $data);
    }
    
    /**
     * Generate a new primary invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false ChatInviteLink or false on failure
     */
    public function exportChatInviteLink($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('exportChatInviteLink', $data);
    }
    
    /**
     * Create an additional invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|null $name Invite link name
     * @param int|null $expireDate Expiration time
     * @param int|null $memberLimit Maximum number of users
     * @param bool|null $createsJoinRequest Creates join requests
     * @return array|false ChatInviteLink or false on failure
     */
    public function createChatInviteLink($chatId, ?string $name = null, ?int $expireDate = null,
                                         ?int $memberLimit = null, ?bool $createsJoinRequest = null) {
        $data = ['chat_id' => $chatId];
        
        if ($name) $data['name'] = $name;
        if ($expireDate) $data['expire_date'] = $expireDate;
        if ($memberLimit) $data['member_limit'] = $memberLimit;
        if ($createsJoinRequest) $data['creates_join_request'] = $createsJoinRequest;
        
        return $this->makeRequest('createChatInviteLink', $data);
    }
    
    /**
     * Edit a non-primary invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $inviteLink Invite link to edit
     * @param string|null $name New name
     * @param int|null $expireDate New expiration time
     * @param int|null $memberLimit New member limit
     * @param bool|null $createsJoinRequest Creates join requests
     * @return array|false ChatInviteLink or false on failure
     */
    public function editChatInviteLink($chatId, string $inviteLink, ?string $name = null,
                                       ?int $expireDate = null, ?int $memberLimit = null,
                                       ?bool $createsJoinRequest = null) {
        $data = [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ];
        
        if ($name) $data['name'] = $name;
        if ($expireDate) $data['expire_date'] = $expireDate;
        if ($memberLimit) $data['member_limit'] = $memberLimit;
        if ($createsJoinRequest) $data['creates_join_request'] = $createsJoinRequest;
        
        return $this->makeRequest('editChatInviteLink', $data);
    }
    
    /**
     * Create a subscription invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $subscriptionPeriod Subscription period in seconds
     * @param int $subscriptionPrice Price in Telegram Stars
     * @param string|null $name Invite link name
     * @return array|false ChatInviteLink or false on failure
     */
    public function createChatSubscriptionInviteLink($chatId, int $subscriptionPeriod, int $subscriptionPrice,
                                                      ?string $name = null) {
        $data = [
            'chat_id' => $chatId,
            'subscription_period' => $subscriptionPeriod,
            'subscription_price' => $subscriptionPrice
        ];
        
        if ($name) $data['name'] = $name;
        
        return $this->makeRequest('createChatSubscriptionInviteLink', $data);
    }
    
    /**
     * Edit a subscription invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $inviteLink Invite link to edit
     * @param string|null $name New name
     * @return array|false ChatInviteLink or false on failure
     */
    public function editChatSubscriptionInviteLink($chatId, string $inviteLink, ?string $name = null) {
        $data = [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ];
        
        if ($name) $data['name'] = $name;
        
        return $this->makeRequest('editChatSubscriptionInviteLink', $data);
    }
    
    /**
     * Revoke an invite link
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $inviteLink Invite link to revoke
     * @return array|false ChatInviteLink or false on failure
     */
    public function revokeChatInviteLink($chatId, string $inviteLink) {
        $data = [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ];
        
        return $this->makeRequest('revokeChatInviteLink', $data);
    }
    
    /**
     * Approve a chat join request
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID of join request
     * @return array|false Response or false on failure
     */
    public function approveChatJoinRequest($chatId, int $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        return $this->makeRequest('approveChatJoinRequest', $data);
    }
    
    /**
     * Decline a chat join request
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID of join request
     * @return array|false Response or false on failure
     */
    public function declineChatJoinRequest($chatId, int $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        return $this->makeRequest('declineChatJoinRequest', $data);
    }
    
    /**
     * Set a profile photo for the chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $photo Photo file path
     * @return array|false Response or false on failure
     */
    public function setChatPhoto($chatId, string $photo) {
        $data = [
            'chat_id' => $chatId,
            'photo' => new CURLFile($photo)
        ];
        
        return $this->makeFileRequest('setChatPhoto', $data);
    }
    
    /**
     * Delete a profile photo from the chat
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Response or false on failure
     */
    public function deleteChatPhoto($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('deleteChatPhoto', $data);
    }
    
    /**
     * Change the title of a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $title New chat title
     * @return array|false Response or false on failure
     */
    public function setChatTitle($chatId, string $title) {
        $data = [
            'chat_id' => $chatId,
            'title' => $title
        ];
        
        return $this->makeRequest('setChatTitle', $data);
    }
    
    /**
     * Change the description of a group, supergroup or channel
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|null $description New chat description
     * @return array|false Response or false on failure
     */
    public function setChatDescription($chatId, ?string $description = null) {
        $data = [
            'chat_id' => $chatId,
            'description' => $description ?? ''
        ];
        
        return $this->makeRequest('setChatDescription', $data);
    }
    
    /**
     * Pin a message in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $messageId Message ID to pin
     * @param bool|null $disableNotification Disable notification
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Response or false on failure
     */
    public function pinChatMessage($chatId, int $messageId, ?bool $disableNotification = null,
                                   ?string $businessConnectionId = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];
        
        if ($disableNotification) $data['disable_notification'] = $disableNotification;
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('pinChatMessage', $data);
    }
    
    /**
     * Unpin a message in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int|null $messageId Message ID to unpin (optional)
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Response or false on failure
     */
    public function unpinChatMessage($chatId, ?int $messageId = null, ?string $businessConnectionId = null) {
        $data = ['chat_id' => $chatId];
        
        if ($messageId) $data['message_id'] = $messageId;
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('unpinChatMessage', $data);
    }
    
    /**
     * Clear the list of pinned messages in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Response or false on failure
     */
    public function unpinAllChatMessages($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('unpinAllChatMessages', $data);
    }
    
    /**
     * Leave a group, supergroup or channel
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Response or false on failure
     */
    public function leaveChat($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('leaveChat', $data);
    }
    
    /**
     * Get up-to-date information about the chat
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Chat object or false on failure
     */
    public function getChat($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('getChat', $data);
    }
    
    /**
     * Get a list of administrators in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Array of ChatMember objects or false on failure
     */
    public function getChatAdministrators($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('getChatAdministrators', $data);
    }
    
    /**
     * Get the number of members in a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Integer or false on failure
     */
    public function getChatMemberCount($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('getChatMemberCount', $data);
    }
    
    /**
     * Get information about a member of a chat
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID
     * @return array|false ChatMember object or false on failure
     */
    public function getChatMember($chatId, int $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        return $this->makeRequest('getChatMember', $data);
    }
    
    /**
     * Set a new group sticker set for a supergroup
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $stickerSetName Name of the sticker set
     * @return array|false Response or false on failure
     */
    public function setChatStickerSet($chatId, string $stickerSetName) {
        $data = [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName
        ];
        
        return $this->makeRequest('setChatStickerSet', $data);
    }
    
    /**
     * Delete a group sticker set from a supergroup
     * 
     * @param int|string $chatId Chat ID or username
     * @return array|false Response or false on failure
     */
    public function deleteChatStickerSet($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('deleteChatStickerSet', $data);
    }
    
    /**
     * Get custom emoji stickers for chat background
     * 
     * @param string|null $type Type of background
     * @return array|false Array of Sticker objects or false on failure
     */
    public function getAvailableGifts(?string $type = null) {
        $data = [];
        
        if ($type) $data['type'] = $type;
        
        return $this->makeRequest('getAvailableGifts', $data);
    }
    
    /**
     * Send a gift to a user
     * 
     * @param int $userId User ID to send gift to
     * @param string $giftId Gift identifier
     * @param string|null $text Gift text
     * @param string|null $textParseMode Text parse mode
     * @param array|null $textEntities Text entities
     * @return array|false Response or false on failure
     */
    public function sendGift(int $userId, string $giftId, ?string $text = null,
                             ?string $textParseMode = null, ?array $textEntities = null) {
        $data = [
            'user_id' => $userId,
            'gift_id' => $giftId
        ];
        
        if ($text) $data['text'] = $text;
        if ($textParseMode) $data['text_parse_mode'] = $textParseMode;
        if ($textEntities) $data['text_entities'] = json_encode($textEntities);
        
        return $this->makeRequest('sendGift', $data);
    }
    
    /**
     * Verify a user on behalf of the bot
     * 
     * @param int $userId User ID to verify
     * @param string|null $customDescription Custom description
     * @return array|false Response or false on failure
     */
    public function verifyUser(int $userId, ?string $customDescription = null) {
        $data = ['user_id' => $userId];
        
        if ($customDescription) $data['custom_description'] = $customDescription;
        
        return $this->makeRequest('verifyUser', $data);
    }
    
    /**
     * Verify a chat on behalf of the bot
     * 
     * @param int|string $chatId Chat ID to verify
     * @param string|null $customDescription Custom description
     * @return array|false Response or false on failure
     */
    public function verifyChat($chatId, ?string $customDescription = null) {
        $data = ['chat_id' => $chatId];
        
        if ($customDescription) $data['custom_description'] = $customDescription;
        
        return $this->makeRequest('verifyChat', $data);
    }
    
    /**
     * Remove verification from a user
     * 
     * @param int $userId User ID to remove verification
     * @return array|false Response or false on failure
     */
    public function removeUserVerification(int $userId) {
        $data = ['user_id' => $userId];
        
        return $this->makeRequest('removeUserVerification', $data);
    }
    
    /**
     * Remove verification from a chat
     * 
     * @param int|string $chatId Chat ID to remove verification
     * @return array|false Response or false on failure
     */
    public function removeChatVerification($chatId) {
        $data = ['chat_id' => $chatId];
        
        return $this->makeRequest('removeChatVerification', $data);
    }
    
    // ==========================================
    // EDITING MESSAGES
    // ==========================================
    
    /**
     * Edit text and game messages
     * 
     * @param string $text New text
     * @param int|string|null $chatId Chat ID or username (required for inline messages)
     * @param int|null $messageId Message ID (required for inline messages)
     * @param string|null $inlineMessageId Inline message ID
     * @param string|null $parseMode Parse mode
     * @param array|null $entities Message entities
     * @param bool|null $disableWebPagePreview Disable link previews
     * @param array|null $replyMarkup New reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Message or true on success or false on failure
     */
    public function editMessageText(string $text, $chatId = null, ?int $messageId = null,
                                    ?string $inlineMessageId = null, ?string $parseMode = null,
                                    ?array $entities = null, ?bool $disableWebPagePreview = null,
                                    ?array $replyMarkup = null, ?string $businessConnectionId = null) {
        $data = ['text' => $text];
        
        if ($chatId) $data['chat_id'] = $chatId;
        if ($messageId) $data['message_id'] = $messageId;
        if ($inlineMessageId) $data['inline_message_id'] = $inlineMessageId;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($entities) $data['entities'] = json_encode($entities);
        if ($disableWebPagePreview) $data['disable_web_page_preview'] = $disableWebPagePreview;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('editMessageText', $data);
    }
    
    /**
     * Edit captions of messages
     * 
     * @param int|string|null $chatId Chat ID or username
     * @param int|null $messageId Message ID
     * @param string|null $inlineMessageId Inline message ID
     * @param string|null $caption New caption
     * @param string|null $parseMode Caption parse mode
     * @param array|null $captionEntities Caption entities
     * @param bool|null $showCaptionAboveMedia Show caption above media
     * @param array|null $replyMarkup New reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Message or true on success or false on failure
     */
    public function editMessageCaption($chatId = null, ?int $messageId = null,
                                       ?string $inlineMessageId = null, ?string $caption = null,
                                       ?string $parseMode = null, ?array $captionEntities = null,
                                       ?bool $showCaptionAboveMedia = null, ?array $replyMarkup = null,
                                       ?string $businessConnectionId = null) {
        $data = [];
        
        if ($chatId) $data['chat_id'] = $chatId;
        if ($messageId) $data['message_id'] = $messageId;
        if ($inlineMessageId) $data['inline_message_id'] = $inlineMessageId;
        if ($caption) $data['caption'] = $caption;
        if ($parseMode) $data['parse_mode'] = $parseMode;
        if ($captionEntities) $data['caption_entities'] = json_encode($captionEntities);
        if ($showCaptionAboveMedia) $data['show_caption_above_media'] = $showCaptionAboveMedia;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('editMessageCaption', $data);
    }
    
    /**
     * Edit animation, audio, document, photo, or video messages
     * 
     * @param int|string|null $chatId Chat ID or username
     * @param int|null $messageId Message ID
     * @param string|null $inlineMessageId Inline message ID
     * @param array|null $media Array with new media
     * @param array|null $replyMarkup New reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Message or true on success or false on failure
     */
    public function editMessageMedia($chatId = null, ?int $messageId = null,
                                     ?string $inlineMessageId = null, ?array $media = null,
                                     ?array $replyMarkup = null, ?string $businessConnectionId = null) {
        $data = [];
        
        if ($chatId) $data['chat_id'] = $chatId;
        if ($messageId) $data['message_id'] = $messageId;
        if ($inlineMessageId) $data['inline_message_id'] = $inlineMessageId;
        if ($media) $data['media'] = json_encode($media);
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('editMessageMedia', $data);
    }
    
    /**
     * Edit live location messages
     * 
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param int|string|null $chatId Chat ID or username
     * @param int|null $messageId Message ID
     * @param string|null $inlineMessageId Inline message ID
     * @param float|null $horizontalAccuracy Accuracy in meters
     * @param int|null $heading Direction of movement
     * @param int|null $proximityAlertRadius Proximity alert radius
     * @param array|null $replyMarkup New reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Message or true on success or false on failure
     */
    public function editMessageLiveLocation(float $latitude, float $longitude, $chatId = null,
                                            ?int $messageId = null, ?string $inlineMessageId = null,
                                            ?float $horizontalAccuracy = null, ?int $heading = null,
                                            ?int $proximityAlertRadius = null, ?array $replyMarkup = null,
                                            ?string $businessConnectionId = null) {
        $data = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        
        if ($chatId) $data['chat_id'] = $chatId;
        if ($messageId) $data['message_id'] = $messageId;
        if ($inlineMessageId) $data['inline_message_id'] = $inlineMessageId;
        if ($horizontalAccuracy) $data['horizontal_accuracy'] = $horizontalAccuracy;
        if ($heading) $data['heading'] = $heading;
        if ($proximityAlertRadius) $data['proximity_alert_radius'] = $proximityAlertRadius;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('editMessageLiveLocation', $data);
    }
    
    /**
     * Stop updating a live location message
     * 
     * @param int|string|null $chatId Chat ID or username
     * @param int|null $messageId Message ID
     * @param string|null $inlineMessageId Inline message ID
     * @param array|null $replyMarkup New reply markup
     * @param string|null $businessConnectionId Business connection ID
     * @return array|false Message or true on success or false on failure
     */
    public function stopMessageLiveLocation($chatId = null, ?int $messageId = null,
                                            ?string $inlineMessageId = null, ?array $replyMarkup = null,
                                            ?string $businessConnectionId = null) {
        $data = [];
        
        if ($chatId) $data['chat_id'] = $chatId;
        if ($messageId) $data['message_id'] = $messageId;
        if ($inlineMessageId) $data['inline_message_id'] = $inlineMessageId;
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        if ($businessConnectionId) $data['business_connection_id'] = $businessConnectionId;
        
        return $this->makeRequest('stopMessageLiveLocation', $data);
    }
    
    /**
     * Delete messages
     * 
     * @param int|string $chatId Chat ID or username
     * @param array $messageIds List of message IDs to delete
     * @return array|false True on success or false on failure
     */
    public function deleteMessages($chatId, array $messageIds) {
        $data = [
            'chat_id' => $chatId,
            'message_ids' => json_encode($messageIds)
        ];
        
        return $this->makeRequest('deleteMessages', $data);
    }
    
    // ==========================================
    // STICKERS
    // ==========================================
    
    /**
     * Get a sticker set
     * 
     * @param string $name Name of the sticker set
     * @return array|false StickerSet object or false on failure
     */
    public function getStickerSet(string $name) {
        $data = ['name' => $name];
        
        return $this->makeRequest('getStickerSet', $data);
    }
    
    /**
     * Upload a file with a sticker for later use
     * 
     * @param int $userId User ID who uploaded the sticker
     * @param string $sticker Sticker file path
     * @param string $format Format of the sticker (static, animated, video)
     * @return array|false File object or false on failure
     */
    public function uploadStickerFile(int $userId, string $sticker, string $format) {
        $data = [
            'user_id' => $userId,
            'sticker' => new CURLFile($sticker),
            'format' => $format
        ];
        
        return $this->makeFileRequest('uploadStickerFile', $data);
    }
    
    /**
     * Create a new sticker set
     * 
     * @param int $userId User ID who created the sticker set
     * @param string $name Short name of the sticker set
     * @param string $title Title of the sticker set
     * @param array $stickers List of stickers to add
     * @param string|null $format Format of the stickers
     * @param string|null $stickerType Type of stickers (regular or mask)
     * @param bool|null $needsRepainting Needs repainting
     * @return array|false True on success or false on failure
     */
    public function createNewStickerSet(int $userId, string $name, string $title, array $stickers,
                                        ?string $format = null, ?string $stickerType = null,
                                        ?bool $needsRepainting = null) {
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'stickers' => json_encode($stickers)
        ];
        
        if ($format) $data['format'] = $format;
        if ($stickerType) $data['sticker_type'] = $stickerType;
        if ($needsRepainting) $data['needs_repainting'] = $needsRepainting;
        
        return $this->makeRequest('createNewStickerSet', $data);
    }
    
    /**
     * Add a new sticker to an existing sticker set
     * 
     * @param int $userId User ID who added the sticker
     * @param string $name Name of the sticker set
     * @param array $sticker Sticker to add
     * @return array|false True on success or false on failure
     */
    public function addStickerToSet(int $userId, string $name, array $sticker) {
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'sticker' => json_encode($sticker)
        ];
        
        return $this->makeRequest('addStickerToSet', $data);
    }
    
    /**
     * Move a sticker in a sticker set to a specific position
     * 
     * @param string $sticker File identifier of the sticker
     * @param int $position New position of the sticker
     * @return array|false True on success or false on failure
     */
    public function setStickerPositionInSet(string $sticker, int $position) {
        $data = [
            'sticker' => $sticker,
            'position' => $position
        ];
        
        return $this->makeRequest('setStickerPositionInSet', $data);
    }
    
    /**
     * Delete a sticker from a sticker set
     * 
     * @param string $sticker File identifier of the sticker
     * @return array|false True on success or false on failure
     */
    public function deleteStickerFromSet(string $sticker) {
        $data = ['sticker' => $sticker];
        
        return $this->makeRequest('deleteStickerFromSet', $data);
    }
    
    /**
     * Replace a sticker in a sticker set with another one
     * 
     * @param int $userId User ID who replaced the sticker
     * @param string $name Name of the sticker set
     * @param array $sticker New sticker
     * @return array|false True on success or false on failure
     */
    public function replaceStickerInSet(int $userId, string $name, array $sticker) {
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'sticker' => json_encode($sticker)
        ];
        
        return $this->makeRequest('replaceStickerInSet', $data);
    }
    
    /**
     * Change the list of emoji assigned to a regular or custom emoji sticker
     * 
     * @param string $sticker File identifier of the sticker
     * @param array $emojiList New list of emojis
     * @return array|false True on success or false on failure
     */
    public function setStickerEmojiList(string $sticker, array $emojiList) {
        $data = [
            'sticker' => $sticker,
            'emoji_list' => json_encode($emojiList)
        ];
        
        return $this->makeRequest('setStickerEmojiList', $data);
    }
    
    /**
     * Change search keywords assigned to a regular or custom emoji sticker
     * 
     * @param string $sticker File identifier of the sticker
     * @param array $keywords New list of keywords
     * @return array|false True on success or false on failure
     */
    public function setStickerKeywords(string $sticker, array $keywords) {
        $data = [
            'sticker' => $sticker,
            'keywords' => json_encode($keywords)
        ];
        
        return $this->makeRequest('setStickerKeywords', $data);
    }
    
    /**
     * Change the mask position of a mask sticker
     * 
     * @param string $sticker File identifier of the sticker
     * @param array|null $maskPosition New mask position
     * @return array|false True on success or false on failure
     */
    public function setStickerMaskPosition(string $sticker, ?array $maskPosition = null) {
        $data = ['sticker' => $sticker];
        
        if ($maskPosition) $data['mask_position'] = json_encode($maskPosition);
        
        return $this->makeRequest('setStickerMaskPosition', $data);
    }
    
    /**
     * Set the title of a created sticker set
     * 
     * @param string $name Name of the sticker set
     * @param string $title New title
     * @return array|false True on success or false on failure
     */
    public function setStickerSetTitle(string $name, string $title) {
        $data = [
            'name' => $name,
            'title' => $title
        ];
        
        return $this->makeRequest('setStickerSetTitle', $data);
    }
    
    /**
     * Set the thumbnail of a regular or mask sticker set
     * 
     * @param string $name Name of the sticker set
     * @param int $userId User ID of the sticker set owner
     * @param string|null $thumbnail Thumbnail file path
     * @param string|null $format Format of the thumbnail
     * @return array|false True on success or false on failure
     */
    public function setStickerSetThumbnail(string $name, int $userId, ?string $thumbnail = null,
                                           ?string $format = null) {
        $data = [
            'name' => $name,
            'user_id' => $userId
        ];
        
        if ($thumbnail && file_exists($thumbnail)) {
            $data['thumbnail'] = new CURLFile($thumbnail);
        }
        if ($format) $data['format'] = $format;
        
        return $this->makeFileRequest('setStickerSetThumbnail', $data);
    }
    
    /**
     * Set the thumbnail of a custom emoji sticker set
     * 
     * @param string $name Name of the sticker set
     * @param string|null $customEmojiId Custom emoji identifier
     * @return array|false True on success or false on failure
     */
    public function setCustomEmojiStickerSetThumbnail(string $name, ?string $customEmojiId = null) {
        $data = ['name' => $name];
        
        if ($customEmojiId) $data['custom_emoji_id'] = $customEmojiId;
        
        return $this->makeRequest('setCustomEmojiStickerSetThumbnail', $data);
    }
    
    /**
     * Delete a sticker set that was created by the bot
     * 
     * @param string $name Name of the sticker set
     * @return array|false True on success or false on failure
     */
    public function deleteStickerSet(string $name) {
        $data = ['name' => $name];
        
        return $this->makeRequest('deleteStickerSet', $data);
    }
    
    /**
     * Get information about custom emoji stickers by their identifiers
     * 
     * @param array $customEmojiIds List of custom emoji identifiers
     * @return array|false Array of Sticker objects or false on failure
     */
    public function getCustomEmojiStickers(array $customEmojiIds) {
        $data = ['custom_emoji_ids' => json_encode($customEmojiIds)];
        
        return $this->makeRequest('getCustomEmojiStickers', $data);
    }
    
    /**
     * Set default reaction for the bot
     * 
     * @param array $reaction Default reaction
     * @param bool|null $isBig Use big emoji
     * @return array|false True on success or false on failure
     */
    public function setUserEmojiStatus(array $reaction, ?bool $isBig = null) {
        $data = ['reaction' => json_encode($reaction)];
        
        if ($isBig) $data['is_big'] = $isBig;
        
        return $this->makeRequest('setUserEmojiStatus', $data);
    }
    
    // ==========================================
    // INLINE MODE
    // ==========================================
    
    /**
     * Send answers to an inline query
     * 
     * @param string $inlineQueryId Unique identifier for the answered query
     * @param array $results Array of results for the inline query
     * @param int|null $cacheTime Allowed time for caching results
     * @param bool|null $isPersonal Results are personal
     * @param string|null $nextOffset Offset for next query
     * @param array|null $button Button to show
     * @return array|false True on success or false on failure
     */
    public function answerInlineQuery(string $inlineQueryId, array $results, ?int $cacheTime = null,
                                      ?bool $isPersonal = null, ?string $nextOffset = null,
                                      ?array $button = null) {
        $data = [
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results)
        ];
        
        if ($cacheTime) $data['cache_time'] = $cacheTime;
        if ($isPersonal) $data['is_personal'] = $isPersonal;
        if ($nextOffset) $data['next_offset'] = $nextOffset;
        if ($button) $data['button'] = json_encode($button);
        
        return $this->makeRequest('answerInlineQuery', $data);
    }
    
    /**
     * Get the list of bots that can be used in inline mode
     * 
     * @param string|null $type Type of the button
     * @return array|false Array of BotDescription objects or false on failure
     */
    public function getMyDefaultAdministratorRights(?string $type = null) {
        $data = [];
        
        if ($type) $data['type'] = $type;
        
        return $this->makeRequest('getMyDefaultAdministratorRights', $data);
    }
    
    /**
     * Change the default administrator rights requested by the bot
     * 
     * @param array|null $rights Default administrator rights
     * @param bool|null $forChannels Pass True to change for channels
     * @return array|false True on success or false on failure
     */
    public function setMyDefaultAdministratorRights(?array $rights = null, ?bool $forChannels = null) {
        $data = [];
        
        if ($rights) $data['rights'] = json_encode($rights);
        if ($forChannels) $data['for_channels'] = $forChannels;
        
        return $this->makeRequest('setMyDefaultAdministratorRights', $data);
    }
    
    /**
     * Clear the list of commands for the bot
     * 
     * @param string|null $scope Scope of the commands
     * @param string|null $languageCode Language code
     * @return array|false True on success or false on failure
     */
    public function deleteMyCommands(?array $scope = null, ?string $languageCode = null) {
        $data = [];
        
        if ($scope) $data['scope'] = json_encode($scope);
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('deleteMyCommands', $data);
    }
    
    /**
     * Set the list of commands for the bot
     * 
     * @param array $commands List of bot commands
     * @param array|null $scope Scope of the commands
     * @param string|null $languageCode Language code
     * @return array|false True on success or false on failure
     */
    public function setMyCommands(array $commands, ?array $scope = null, ?string $languageCode = null) {
        $data = ['commands' => json_encode($commands)];
        
        if ($scope) $data['scope'] = json_encode($scope);
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('setMyCommands', $data);
    }
    
    /**
     * Get the current list of commands for the bot
     * 
     * @param array|null $scope Scope of the commands
     * @param string|null $languageCode Language code
     * @return array|false Array of BotCommand objects or false on failure
     */
    public function getMyCommands(?array $scope = null, ?string $languageCode = null) {
        $data = [];
        
        if ($scope) $data['scope'] = json_encode($scope);
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('getMyCommands', $data);
    }
    
    /**
     * Set the name of the bot
     * 
     * @param string $name New bot name
     * @param string|null $languageCode Language code
     * @return array|false True on success or false on failure
     */
    public function setMyName(string $name, ?string $languageCode = null) {
        $data = ['name' => $name];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('setMyName', $data);
    }
    
    /**
     * Get the current name of the bot
     * 
     * @param string|null $languageCode Language code
     * @return array|false BotName object or false on failure
     */
    public function getMyName(?string $languageCode = null) {
        $data = [];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('getMyName', $data);
    }
    
    /**
     * Set the description of the bot
     * 
     * @param string $description New bot description
     * @param string|null $languageCode Language code
     * @return array|false True on success or false on failure
     */
    public function setMyDescription(string $description, ?string $languageCode = null) {
        $data = ['description' => $description];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('setMyDescription', $data);
    }
    
    /**
     * Get the current description of the bot
     * 
     * @param string|null $languageCode Language code
     * @return array|false BotDescription object or false on failure
     */
    public function getMyDescription(?string $languageCode = null) {
        $data = [];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('getMyDescription', $data);
    }
    
    /**
     * Set the short description of the bot
     * 
     * @param string $shortDescription New bot short description
     * @param string|null $languageCode Language code
     * @return array|false True on success or false on failure
     */
    public function setMyShortDescription(string $shortDescription, ?string $languageCode = null) {
        $data = ['short_description' => $shortDescription];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('setMyShortDescription', $data);
    }
    
    /**
     * Get the current short description of the bot
     * 
     * @param string|null $languageCode Language code
     * @return array|false BotShortDescription object or false on failure
     */
    public function getMyShortDescription(?string $languageCode = null) {
        $data = [];
        
        if ($languageCode) $data['language_code'] = $languageCode;
        
        return $this->makeRequest('getMyShortDescription', $data);
    }
    
    // ==========================================
    // ANSWERING CALLBACK QUERIES
    // ==========================================
    
    /**
     * Answer callback queries (for inline buttons)
     * 
     * @param string $callbackQueryId Callback query ID
     * @param string|null $text Text to show in alert
     * @param bool|null $showAlert Show as alert
     * @param string|null $url URL to open
     * @param int|null $cacheTime Cache time
     * @return array|false True on success or false on failure
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null,
                                        ?bool $showAlert = null, ?string $url = null,
                                        ?int $cacheTime = null) {
        $data = ['callback_query_id' => $callbackQueryId];
        
        if ($text) $data['text'] = $text;
        if ($showAlert) $data['show_alert'] = $showAlert;
        if ($url) $data['url'] = $url;
        if ($cacheTime) $data['cache_time'] = $cacheTime;
        
        return $this->makeRequest('answerCallbackQuery', $data);
    }
    
    /**
     * Get the list of boosts added to a chat by a user
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $userId User ID
     * @return array|false UserChatBoosts object or false on failure
     */
    public function getUserChatBoosts($chatId, int $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        
        return $this->makeRequest('getUserChatBoosts', $data);
    }
    
    /**
     * Get information about the connection of the bot to a business account
     * 
     * @param string $businessConnectionId Business connection ID
     * @return array|false BusinessConnection object or false on failure
     */
    public function getBusinessConnection(string $businessConnectionId) {
        $data = ['business_connection_id' => $businessConnectionId];
        
        return $this->makeRequest('getBusinessConnection', $data);
    }
    
    // ==========================================
    // SENDING MESSAGES WITH KEYBOARDS (HELPER METHODS)
    // ==========================================
    
    /**
     * Send message with inline keyboard
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param array $inlineKeyboard Inline keyboard layout
     * @return array|false Response or false on failure
     */
    public function sendMessageWithInlineKeyboard($chatId, string $text, array $inlineKeyboard) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'inline_keyboard' => $inlineKeyboard
            ])
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    /**
     * Send message with reply keyboard
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param array $keyboard Reply keyboard layout
     * @param bool $resizeKeyboard Resize keyboard
     * @param bool $oneTimeKeyboard One-time keyboard
     * @return array|false Response or false on failure
     */
    public function sendMessageWithReplyKeyboard($chatId, string $text, array $keyboard, 
                                                  bool $resizeKeyboard = true, bool $oneTimeKeyboard = false) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => $resizeKeyboard,
                'one_time_keyboard' => $oneTimeKeyboard
            ])
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
}
