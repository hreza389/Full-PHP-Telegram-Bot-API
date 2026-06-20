<?php

require_once 'TelegramBot.php';
require_once 'InputFile.php';

/**
 * Telegram Bot Keyboard Helpers
 * 
 * Simplifies creating Inline and Reply keyboards.
 */

class Keyboard
{
    /**
     * Create an inline keyboard row.
     *
     * @param array ...$buttons List of button arrays
     * @return array The formatted row
     */
    public static function inlineRow(array ...$buttons): array
    {
        return $buttons;
    }

    /**
     * Create a full inline keyboard.
     *
     * @param array ...$rows List of rows (from inlineRow)
     * @return array ['inline_keyboard' => [...]]
     */
    public static function inline(array ...$rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /**
     * Helper to create an inline button.
     *
     * @param string $text Button text
     * @param string|null $url URL to open
     * @param string|null $callback_data Callback data for bot
     * @param string|null $switch_inline_query Switch inline query
     * @param string|null $switch_inline_query_current_chat Switch inline query current chat
     * @param string|null $web_app Web app info
     * @return array Formatted button array
     */
    public static function inlineButton(
        string $text,
        ?string $url = null,
        ?string $callback_data = null,
        ?string $switch_inline_query = null,
        ?string $switch_inline_query_current_chat = null,
        ?array $web_app = null
    ): array {
        $button = ['text' => $text];
        if ($url !== null) $button['url'] = $url;
        if ($callback_data !== null) $button['callback_data'] = $callback_data;
        if ($switch_inline_query !== null) $button['switch_inline_query'] = $switch_inline_query;
        if ($switch_inline_query_current_chat !== null) $button['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
        if ($web_app !== null) $button['web_app'] = $web_app;
        return $button;
    }

    /**
     * Create a reply keyboard row.
     *
     * @param string ...$buttons List of button texts
     * @return array The formatted row
     */
    public static function replyRow(string ...$buttons): array
    {
        return array_map(fn($text) => ['text' => $text], $buttons);
    }

    /**
     * Create a full reply keyboard.
     *
     * @param array $rows List of rows (from replyRow)
     * @param bool $resize_keyboard Requests clients to resize the keyboard
     * @param bool $one_time_keyboard Requests clients to hide the keyboard after use
     * @param bool $selective Use this parameter if you want to show the keyboard to specific users only
     * @param string $input_field_placeholder Placeholder to be shown in the input field
     * @param bool $is_persistent Requests clients to always show the keyboard when the regular keyboard is hidden
     * @return array ['keyboard' => [...], ...options]
     */
    public static function reply(
        array $rows,
        bool $resize_keyboard = false,
        bool $one_time_keyboard = false,
        bool $selective = false,
        string $input_field_placeholder = '',
        bool $is_persistent = false
    ): array {
        $keyboard = ['keyboard' => $rows];
        if ($resize_keyboard) $keyboard['resize_keyboard'] = true;
        if ($one_time_keyboard) $keyboard['one_time_keyboard'] = true;
        if ($selective) $keyboard['selective'] = true;
        if ($input_field_placeholder !== '') $keyboard['input_field_placeholder'] = $input_field_placeholder;
        if ($is_persistent) $keyboard['is_persistent'] = true;
        return $keyboard;
    }

    /**
     * Create a reply keyboard removal object.
     *
     * @param bool $selective Use this parameter if you want to remove the keyboard for specific users only
     * @return array ['remove_keyboard' => true, ...]
     */
    public static function remove(bool $selective = false): array
    {
        return ['remove_keyboard' => true, 'selective' => $selective];
    }

    /**
     * Create a force reply object.
     *
     * @param bool $selective Use this parameter if you want to force reply for specific users only
     * @param string $input_field_placeholder Placeholder to be shown in the input field
     * @return array ['force_reply' => true, ...]
     */
    public static function forceReply(bool $selective = false, string $input_field_placeholder = ''): array
    {
        $reply = ['force_reply' => true];
        if ($selective) $reply['selective'] = true;
        if ($input_field_placeholder !== '') $reply['input_field_placeholder'] = $input_field_placeholder;
        return $reply;
    }
}
