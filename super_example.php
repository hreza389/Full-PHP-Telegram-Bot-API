
<?php

if (file_exists('TelegramErrorLogger.php')) {
    <?php

/**
 * Telegram Error Logger Class.
 *
 * @author shakibonline <shakiba_9@yahoo.com>
 */
class TelegramErrorLogger
{
    private static $self;

    /// Log request and response parameters from/to Telegrsm API

    /**
     * Prints the list of parameters from/to Telegram's API endpoint
     * \param $result the Telegram's response as array
     * \param $content the request parameters as array.
     */
    public static function log($result, $content, $use_rt = true)
    {
        try {
            if ($result['ok'] === false) {
                self::$self = new self();
                $e = new \Exception();
                $error = PHP_EOL;
                $error .= '==========[Response]==========';
                $error .= "\n";
                foreach ($result as $key => $value) {
                    if ($value == false) {
                        $error .= $key.":\t\t\tFalse\n";
                    } else {
                        $error .= $key.":\t\t".$value."\n";
                    }
                }
                $array = '=========[Sent Data]==========';
                $array .= "\n";
                if ($use_rt == true) {
                    foreach ($content as $item) {
                        $array .= self::$self->rt($item).PHP_EOL.PHP_EOL;
                    }
                } else {
                    foreach ($content as $key => $value) {
                        $array .= $key.":\t\t".$value."\n";
                    }
                }
                $backtrace = '============[Trace]===========';
                $backtrace .= "\n";
                $backtrace .= $e->getTraceAsString();
                self::$self->_log_to_file($error.$array.$backtrace);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /// Write a string in the log file adding the current server time

    /**
     * Write a string in the log file TelegramErrorLogger.txt adding the current server time
     * \param $error_text the text to append in the log.
     */
    private function _log_to_file($error_text)
    {
        try {
            $dir_name = 'logs';
            if (!is_dir($dir_name)) {
                mkdir($dir_name);
            }
            $fileName = $dir_name.'/'.__CLASS__.'-'.date('Y-m-d').'.txt';
            $myFile = fopen($fileName, 'a+');
            $date = '============[Date]============';
            $date .= "\n";
            $date .= '[ '.date('Y-m-d H:i:s  e').' ] ';
            fwrite($myFile, $date.$error_text."\n\n");
            fclose($myFile);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    private function rt($array, $title = null, $head = true)
    {
        $ref = 'ref';
        $text = '';
        if ($head) {
            $text = "[$ref]";
            $text .= "\n";
        }
        foreach ($array as $key => $value) {
            if ($value instanceof CURLFile) {
                $text .= $ref.'.'.$key.'= File'.PHP_EOL;
            } elseif (is_array($value)) {
                if ($title != null) {
                    $key = $title.'.'.$key;
                }
                $text .= self::rt($value, $key, false);
            } else {
                if (is_bool($value)) {
                    $value = ($value) ? 'true' : 'false';
                }
                if ($title != '') {
                    $text .= $ref.'.'.$title.'.'.$key.'= '.$value.PHP_EOL;
                } else {
                    $text .= $ref.'.'.$key.'= '.$value.PHP_EOL;
                }
            }
        }

        return $text;
    }
}

}



/**
 * Telegram Bot Class.
 *
 * @author Gabriele Grillo <gabry.grillo@alice.it>
 */
class Telegram
{
    /**
     * Constant for type Inline Query.
     */
    const INLINE_QUERY = 'inline_query';
    /**
     * Constant for type Callback Query.
     */
    const CALLBACK_QUERY = 'callback_query';
    /**
     * Constant for type Edited Message.
     */
    const EDITED_MESSAGE = 'edited_message';
    /**
     * Constant for type Reply.
     */
    const REPLY = 'reply';
    /**
     * Constant for type Message.
     */
    const MESSAGE = 'message';
    /**
     * Constant for type Photo.
     */
    const PHOTO = 'photo';
    /**
     * Constant for type Video.
     */
    const VIDEO = 'video';
    /**
     * Constant for type Audio.
     */
    const AUDIO = 'audio';
    /**
     * Constant for type Voice.
     */
    const VOICE = 'voice';
    /**
     * Constant for type animation.
     */
    const ANIMATION = 'animation';
    /**
     * Constant for type sticker.
     */
    const STICKER = 'sticker';
    /**
     * Constant for type Document.
     */
    const DOCUMENT = 'document';
    /**
     * Constant for type Location.
     */
    const LOCATION = 'location';
    /**
     * Constant for type Contact.
     */
    const CONTACT = 'contact';
    /**
     * Constant for type Channel Post.
     */
    const CHANNEL_POST = 'channel_post';

    private $bot_token = '';
    private $data = [];
    private $updates = [];
    private $log_errors;
    private $proxy;

    /// Class constructor

    /**
     * Create a Telegram instance from the bot token
     * \param $bot_token the bot token
     * \param $log_errors enable or disable the logging
     * \param $proxy array with the proxy configuration (url, port, type, auth)
     * \return an instance of the class.
     */
    public function __construct($bot_token, $log_errors = true, array $proxy = [])
    {
        $this->bot_token = $bot_token;
        $this->data = $this->getData();
        $this->log_errors = $log_errors;
        $this->proxy = $proxy;
    }

    /// Do requests to Telegram Bot API

    /**
     * Contacts the various API's endpoints
     * \param $api the API endpoint
     * \param $content the request parameters as array
     * \param $post boolean tells if $content needs to be sends
     * \return the JSON Telegram's reply.
     */
    public function endpoint($api, array $content, $post = true)
    {
        $url = 'https://api.telegram.org/bot'.$this->bot_token.'/'.$api;
        if ($post) {
            $reply = $this->sendAPIRequest($url, $content);
        } else {
            $reply = $this->sendAPIRequest($url, [], false);
        }

        return json_decode($reply, true);
    }

    /// A method for testing your bot.

    /**
     * A simple method for testing your bot's auth token. Requires no parameters.
     * Returns basic information about the bot in form of a User object.
     * \return the JSON Telegram's reply.
     */
    public function getMe()
    {
        return $this->endpoint('getMe', [], false);
    }

    /// A method for responding http to Telegram.

    /**
     * \return the HTTP 200 to Telegram.
     */
    public function respondSuccess()
    {
        http_response_code(200);

        return json_encode(['status' => 'success']);
    }

    /// Send a message

    /**
     * Use this method to send text messages.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * * </tr>
     * <tr>
     * <td>text</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Text of the message to be sent</td>
     * </tr>
     * <tr>
     * <td>parse_mode</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Send <em>Markdown</em>, if you want Telegram apps to show bold, italic and inline URLs in your bot's message. For the moment, only Telegram for Android supports this.</td>
     * </tr>
     * <tr>
     * <td>disable_web_page_preview</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Disables link previews for links in this message</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardHide or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendMessage(array $content)
    {
        return $this->endpoint('sendMessage', $content);
    }

    /// Forward a message

    /**
     * Use this method to forward messages of any kind. On success, the sent Message is returned<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>from_chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique message identifier</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function forwardMessage(array $content)
    {
        return $this->endpoint('forwardMessage', $content);
    }


    public function copyMessage(array $content)
    {
        return $this->endpoint('copyMessage', $content);
    }

    /// Send a photo

    /**
     * Use this method to send photos. On success, the sent Message is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>photo</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Photo to send. Pass a file_id as String to send a photo that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get a photo from the Internet, or upload a new photo using multipart/form-data.</td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Photo caption (may also be used when resending photos by <em>file_id</em>).</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendPhoto(array $content)
    {
        return $this->endpoint('sendPhoto', $content);
    }

    /// Send an audio

    /**
     * Use this method to send audio files, if you want Telegram clients to display them in the music player. Your audio must be in the .mp3 format. On success, the sent Message is returned. Bots can currently send audio files of up to 50 MB in size, this limit may be changed in the future.
     * For sending voice messages, use the sendVoice method instead.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>audio</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Audio file to send. Pass a file_id as String to send an audio file that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get an audio file from the Internet, or upload a new one using <strong>multipart/form-data</strong>.</td>
     * </tr>
     * <tr>
     * <td>duration</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Duration of the audio in seconds</td>
     * </tr>
     * <tr>
     * <td>performer</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Performer</td>
     * </tr>
     * <tr>
     * <td>title</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Track name</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendAudio(array $content)
    {
        return $this->endpoint('sendAudio', $content);
    }

    /// Send a document

    /**
     * Use this method to send general files. On success, the sent Message is returned. Bots can currently send files of any type of up to 50 MB in size, this limit may be changed in the future.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>document</td>
     * <td>InputFile or String</td>
     * <td>Yes</td>
     * <td>File to send. Pass a file_id as String to send a file that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get a file from the Internet, or upload a new one using <strong>multipart/form-data</strong>.</td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Document caption (may also be used when resending documents by file_id), 0-200 characters.</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendDocument(array $content)
    {
        return $this->endpoint('sendDocument', $content);
    }

    /// Send an animation

    /**
     * Use this method to send animation files (GIF or H.264/MPEG-4 AVC video without sound). On success, the sent Message is returned. Bots can currently send animation files of up to 50 MB in size, this limit may be changed in the future.<br/>Values inside $content:<br/>
     * </table>
     * <tr>
     * <th>Parameter</th>
     * <th>Type</th>
     * <th>Required</th>
     * <th>Description</th>
     * </tr>
     * </thead>
     * <tbody>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format <code>@channelusername</code>)</td>
     * </tr>
     * <tr>
     * <td>animation</td>
     * <td><a href="#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Animation to send. Pass a file_id as String to send an animation that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get an animation from the Internet, or upload a new animation using multipart/form-data. <a href="#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * <tr>
     * <td>duration</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Duration of sent animation in seconds</td>
     * </tr>
     * <tr>
     * <td>width</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Animation width</td>
     * </tr>
     * <tr>
     * <td>height</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Animation height</td>
     * </tr>
     * <tr>
     * <td>thumb</td>
     * <td><a href="#inputfile">InputFile</a> or String</td>
     * <td>Optional</td>
     * <td>Thumbnail of the file sent; can be ignored if thumbnail generation for the file is supported server-side. The thumbnail should be in JPEG format and less than 200 kB in size. A thumbnail‘s width and height should not exceed 90. Ignored if the file is not uploaded using multipart/form-data. Thumbnails can’t be reused and can be only uploaded as a new file, so you can pass “attach://&lt;file_attach_name&gt;” if the thumbnail was uploaded using multipart/form-data under &lt;file_attach_name&gt;. <a href="#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Animation caption (may also be used when resending animation by <em>file_id</em>), 0-1024 characters</td>
     * </tr>
     * <tr>
     * <td>parse_mode</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Send <a href="#markdown-style"><em>Markdown</em></a> or <a href="#html-style"><em>HTML</em></a>, if you want Telegram apps to show <a href="#formatting-* options">bold, italic, fixed-width text or inline URLs</a> in the media caption.</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="#inlinekeyboardmarkup">InlineKeyboardMarkup</a> or <a href="#replykeyboardmarkup">ReplyKeyboardMarkup</a> or <a href="#replykeyboardremove">ReplyKeyboardRemove</a> or <a href="#forcereply">ForceReply</a></td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>, <a href="https://core.telegram.org/bots#keyboards">custom reply keyboard</a>, instructions to remove reply keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendAnimation(array $content)
    {
        return $this->endpoint('sendAnimation', $content);
    }

    /// Send a sticker

    /**
     * Use this method to send .webp stickers. On success, the sent Message is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the message recipient — User or GroupChat id</td>
     * </tr>
     * <tr>
     * <td>sticker</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Sticker to send. You can either pass a <em>file_id</em> as String to resend a sticker that is already on the Telegram servers, or upload a new sticker using <strong>multipart/form-data</strong>.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>ReplyKeyboardMarkup or ReplyKeyboardHide or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendSticker(array $content)
    {
        return $this->endpoint('sendSticker', $content);
    }

    /// Send a video

    /**
     * Use this method to send video files, Telegram clients support mp4 videos (other formats may be sent as Document). On success, the sent Message is returned. Bots can currently send video files of up to 50 MB in size, this limit may be changed in the future.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the message recipient — User or GroupChat id</td>
     * </tr>
     * <tr>
     * <td>video</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Video to send. Pass a file_id as String to send a video that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get a video from the Internet, or upload a new video using <strong>multipart/form-data</strong>.</td>
     * </tr>
     * <tr>
     * <td>duration</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Duration of sent video in seconds</td>
     * </tr>
     * <tr>
     * <td>width</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Video width</td>
     * </tr>
     * <tr>
     * <td>height</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Video height</td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Video caption (may also be used when resending videos by <em>file_id</em>).</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendVideo(array $content)
    {
        return $this->endpoint('sendVideo', $content);
    }

    /// Send a voice message

    /**
     *  Use this method to send audio files, if you want Telegram clients to display the file as a playable voice message. For this to work, your audio must be in an .ogg file encoded with OPUS (other formats may be sent as Audio or Document). On success, the sent Message is returned. Bots can currently send voice messages of up to 50 MB in size, this limit may be changed in the future.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>voice</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Audio file to send. Pass a file_id as String to send a file that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get a file from the Internet, or upload a new one using <strong>multipart/form-data</strong>.</td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Voice message caption, 0-200 characters</td>
     * </tr>
     * <tr>
     * <td>duration</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Duration of sent audio in seconds</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendVoice(array $content)
    {
        return $this->endpoint('sendVoice', $content);
    }

    /// Send a location

    /**
     *  Use this method to send point on the map. On success, the sent Message is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>latitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Latitude of location</td>
     * </tr>
     * <tr>
     * <td>longitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Longitude of location</td>
     * </tr>
     * <tr>
     * <td>live_period</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Period in seconds for which the location will be updated (see <a href="https://telegram.org/blog/live-locations">Live Locations</a>, should be between 60 and 86400.</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message silently. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td>InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply</td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for a custom reply keyboard, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendLocation(array $content)
    {
        return $this->endpoint('sendLocation', $content);
    }

    /// Edit Message Live Location

    /**
     * Use this method to edit live location messages sent by the bot or via the bot (for <a href="https://core.telegram.org/bots/api#inline-mode">inline bots</a>). A location can be edited until its <em>live_period</em> expires or editing is explicitly disabled by a call to <a href="https://core.telegram.org/bots/api#stopmessagelivelocation">stopMessageLiveLocation</a>. On success, if the edited message was sent by the bot, the edited <a href="https://core.telegram.org/bots/api#message">Message</a> is returned, otherwise <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * <tr>
     * <td>latitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Latitude of new location</td>
     * </tr>
     * <tr>
     * <td>longitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Longitude of new location</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for a new <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function editMessageLiveLocation(array $content)
    {
        return $this->endpoint('editMessageLiveLocation', $content);
    }

    /// Stop Message Live Location

    /**
     * Use this method to stop updating a live location message sent by the bot or via the bot (for <a href="https://core.telegram.org/bots/api#inline-mode">inline bots</a>) before <em>live_period</em> expires. On success, if the message was sent by the bot, the sent <a href="https://core.telegram.org/bots/api#message">Message</a> is returned, otherwise <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for a new <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function stopMessageLiveLocation(array $content)
    {
        return $this->endpoint('stopMessageLiveLocation', $content);
    }

    /// Set Chat Sticker Set

    /**
     * Use this method to set a new group sticker set for a supergroup. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Use the field <em>can_set_sticker_set</em> optionally returned in <a href="https://core.telegram.org/bots/api#getchat">getChat</a> requests to check if the bot can use this method. Returns <em>True</em> on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup (in the format <code>@supergroupusername</code>)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setChatStickerSet(array $content)
    {
        return $this->endpoint('setChatStickerSet', $content);
    }

    /// Delete Chat Sticker Set

    /**
     * Use this method to delete a group sticker set from a supergroup. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Use the field <em>can_set_sticker_set</em> optionally returned in <a href="https://core.telegram.org/bots/api#getchat">getChat</a> requests to check if the bot can use this method. Returns <em>True</em> on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup (in the format <code>@supergroupusername</code>)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function deleteChatStickerSet(array $content)
    {
        return $this->endpoint('deleteChatStickerSet', $content);
    }

    /// Send Media Group

    /**
     * Use this method to send a group of photos or videos as an album. On success, an array of the sent <a href="https://core.telegram.org/bots/api#message">Messages</a> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>media</td>
     * <td>Array of <a href="https://core.telegram.org/bots/api#inputmedia">InputMedia</a></td>
     * <td>Yes</td>
     * <td>A JSON-serialized array describing photos and videos to be sent, must include 2–10 items</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the messages <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the messages are a reply, ID of the original message</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendMediaGroup(array $content)
    {
        return $this->endpoint('sendMediaGroup', $content);
    }

    /// Send Venue

    /**
     * Use this method to send information about a venue. On success, the sent <a href="https://core.telegram.org/bots/api#message">Message</a> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>latitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Latitude of the venue</td>
     * </tr>
     * <tr>
     * <td>longitude</td>
     * <td>Float number</td>
     * <td>Yes</td>
     * <td>Longitude of the venue</td>
     * </tr>
     * <tr>
     * <td>title</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Name of the venue</td>
     * </tr>
     * <tr>
     * <td>address</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Address of the venue</td>
     * </tr>
     * <tr>
     * <td>foursquare_id</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Foursquare identifier of the venue</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. iOS users will not receive a notification, Android users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardmarkup">ReplyKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardhide">ReplyKeyboardHide</a> or <a href="https://core.telegram.org/bots/api#forcereply">ForceReply</a></td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>, <a href="https://core.telegram.org/bots#keyboards">custom reply keyboard</a>, instructions to hide reply keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendVenue(array $content)
    {
        return $this->endpoint('sendVenue', $content);
    }

    //Send contact

    /**Use this method to send phone contacts. On success, the sent <a href="https://core.telegram.org/bots/api#message">Message</a> is returned.</p> <br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>phone_number</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Contact&#39;s phone number</td>
     * </tr>
     * <tr>
     * <td>first_name</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Contact&#39;s first name</td>
     * </tr>
     * <tr>
     * <td>last_name</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Contact&#39;s last name</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. iOS users will not receive a notification, Android users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardmarkup">ReplyKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardhide">ReplyKeyboardHide</a> or <a href="https://core.telegram.org/bots/api#forcereply">ForceReply</a></td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>, <a href="https://core.telegram.org/bots#keyboards">custom reply keyboard</a>, instructions to hide keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply
     */
    public function sendContact(array $content)
    {
        return $this->endpoint('sendContact', $content);
    }

    /// Send a chat action

    /**
     *  Use this method when you need to tell the user that something is happening on the bot's side. The status is set for 5 seconds or less (when a message arrives from your bot, Telegram clients clear its typing status).
     *
     * Example: The ImageBot needs some time to process a request and upload the image. Instead of sending a text message along the lines of “Retrieving image, please wait…”, the bot may use sendChatAction with action = upload_photo. The user will see a “sending photo” status for the bot.
     *
     * We only recommend using this method when a response from the bot will take a noticeable amount of time to arrive.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the message recipient — User or GroupChat id</td>
     * </tr>
     * <tr>
     * <td>action</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Type of action to broadcast. Choose one, depending on what the user is about to receive: <em>typing</em> for text messages, <em>upload_photo</em> for photos, <em>record_video</em> or <em>upload_video</em> for videos, <em>record_audio</em> or <em>upload_audio</em> for audio files, <em>upload_document</em> for general files, <em>find_location</em> for location data.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendChatAction(array $content)
    {
        return $this->endpoint('sendChatAction', $content);
    }

    /// Get a list of profile pictures for a user

    /**
     *  Use this method to get a list of profile pictures for a user. Returns a UserProfilePhotos object.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier of the target user</td>
     * </tr>
     * <tr>
     * <td>offset</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Sequential number of the first photo to be returned. By default, all photos are returned.</td>
     * </tr>
     * <tr>
     * <td>limit</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Limits the number of photos to be retrieved. Values between 1—100 are accepted. Defaults to 100.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getUserProfilePhotos(array $content)
    {
        return $this->endpoint('getUserProfilePhotos', $content);
    }

    /// Use this method to get basic info about a file and prepare it for downloading

    /**
     *  Use this method to get basic info about a file and prepare it for downloading. For the moment, bots can download files of up to 20MB in size. On success, a File object is returned. The file can then be downloaded via the link https://api.telegram.org/file/bot<token>/<file_path>, where <file_path> is taken from the response. It is guaranteed that the link will be valid for at least 1 hour. When the link expires, a new one can be requested by calling getFile again.
     * \param $file_id String File identifier to get info about
     * \return the JSON Telegram's reply.
     */
    public function getFile($file_id)
    {
        $content = ['file_id' => $file_id];

        return $this->endpoint('getFile', $content);
    }

    /// Kick Chat Member

    /**
     * Use this method to kick a user from a group or a supergroup. In the case of supergroups, the user will not be able to return to the group on their own using invite links, etc., unless <a href="https://core.telegram.org/bots/api#unbanchatmember">unbanned</a> first. The bot must be an administrator in the group for this to work. Returns <em>True</em> on success.<br>
     * Note: This will method only work if the \˜All Members Are Admins\' setting is off in the target group. Otherwise members may only be removed by the group&#39;s creator or by the member that added them.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target group or username of the target supergroup (in the format \c \@supergroupusername)</td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier of the target user</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function kickChatMember(array $content)
    {
        return $this->endpoint('kickChatMember', $content);
    }

    /// Leave Chat

    /**
     * Use this method for your bot to leave a group, supergroup or channel. Returns <em>True</em> on success.</p> <br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup or channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function leaveChat(array $content)
    {
        return $this->endpoint('leaveChat', $content);
    }

    /// Unban Chat Member

    /**
     * Use this method to unban a previously kicked user in a supergroup. The user will <strong>not</strong> return to the group automatically, but will be able to join via link, etc. The bot must be an administrator in the group for this to work. Returns <em>True</em> on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target group or username of the target supergroup (in the format <code>@supergroupusername</code>)</td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier of the target user</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function unbanChatMember(array $content)
    {
        return $this->endpoint('unbanChatMember', $content);
    }

    /// Get Chat Information

    /**
     * Use this method to get up to date information about the chat (current name of the user for one-on-one conversations, current username of a user, group or channel, etc.). Returns a <a href="https://core.telegram.org/bots/api#chat">Chat</a> object on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup or channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getChat(array $content)
    {
        return $this->endpoint('getChat', $content);
    }

    /**
     * Use this method to get a list of administrators in a chat. On success, returns an Array of <a href="https://core.telegram.org/bots/api#chatmember">ChatMember</a> objects that contains information about all chat administrators except other bots. If the chat is a group or a supergroup and no administrators were appointed, only the creator will be returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup or channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getChatAdministrators(array $content)
    {
        return $this->endpoint('getChatAdministrators', $content);
    }

    /**
     * Use this method to get the number of members in a chat. Returns <em>Int</em> on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup or channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getChatMembersCount(array $content)
    {
        return $this->endpoint('getChatMembersCount', $content);
    }

    /**
     * Use this method to get information about a member of a chat. Returns a <a href="https://core.telegram.org/bots/api#chatmember">ChatMember</a> object on success.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target supergroup or channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier of the target user</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getChatMember(array $content)
    {
        return $this->endpoint('getChatMember', $content);
    }

    /**
     * Use this method to send answers to an inline query. On success, <em>True</em> is returned.<br>No more than <strong>50</strong> results per query are allowed.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>inline_query_id</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the answered query</td>
     * </tr>
     * <tr>
     * <td>results</td>
     * <td>Array of <a href="https://core.telegram.org/bots/api#inlinequeryresult">InlineQueryResult</a></td>
     * <td>Yes</td>
     * <td>A JSON-serialized array of results for the inline query</td>
     * </tr>
     * <tr>
     * <td>cache_time</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>The maximum amount of time in seconds that the result of the inline query may be cached on the server. Defaults to 300.</td>
     * </tr>
     * <tr>
     * <td>is_personal</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if results may be cached on the server side only for the user that sent the query. By default, results may be returned to any user who sends the same query</td>
     * </tr>
     * <tr>
     * <td>next_offset</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Pass the offset that a client should send in the next query with the same text to receive more results. Pass an empty string if there are no more results or if you donâ€˜t support pagination. Offset length canâ€™t exceed 64 bytes.</td>
     * </tr>
     * <tr>
     * <td>switch_pm_text</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>If passed, clients will display a button with specified text that switches the user to a private chat with the bot and sends the bot a start message with the parameter <em>switch_pm_parameter</em></td>
     * </tr>
     * <tr>
     * <td>switch_pm_parameter</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Parameter for the start message sent to the bot when user presses the switch button<br><br><em>Example:</em> An inline bot that sends YouTube videos can ask the user to connect the bot to their YouTube account to adapt search results accordingly. To do this, it displays a â€˜Connect your YouTube accountâ€™ button above the results, or even before showing any. The user presses the button, switches to a private chat with the bot and, in doing so, passes a start parameter that instructs the bot to return an oauth link. Once done, the bot can offer a <a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup"><em>switch_inline</em></a> button so that the user can easily return to the chat where they wanted to use the bot&#39;s inline capabilities.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function answerInlineQuery(array $content)
    {
        return $this->endpoint('answerInlineQuery', $content);
    }

    /// Set Game Score

    /**
     * Use this method to set the score of the specified user in a game. On success, if the message was sent by the bot, returns the edited Message, otherwise returns <em>True</em>. Returns an error, if the new score is not greater than the user&#39;s current score in the chat and <em>force</em> is <em>False</em>.<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>User identifier</td>
     * </tr>
     * <tr>
     * <td>score</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>New score, must be non-negative</td>
     * </tr>
     * <tr>
     * <td>force</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass True, if the high score is allowed to decrease. This can be useful when fixing mistakes or banning cheaters</td>
     * </tr>
     * <tr>
     * <td>disable_edit_message</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass True, if the game message should not be automatically edited to include the current scoreboard</td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setGameScore(array $content)
    {
        return $this->endpoint('setGameScore', $content);
    }

    /// Answer a callback Query

    /**
     * Use this method to send answers to callback queries sent from inline keyboards. The answer will be displayed to the user as a notification at the top of the chat screen or as an alert. On success, <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>callback_query_id</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the query to be answered</td>
     * </tr>
     * <tr>
     * <td>text</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Text of the notification. If not specified, nothing will be shown to the user</td>
     * </tr>
     * <tr>
     * <td>show_alert</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>If <em>true</em>, an alert will be shown by the client instead of a notification at the top of the chat screen. Defaults to <em>false</em>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function answerCallbackQuery(array $content)
    {
        return $this->endpoint('answerCallbackQuery', $content);
    }

    /**
     * Use this method to edit text messages sent by the bot or via the bot (for <a href="https://core.telegram.org/bots/api#inline-mode">inline bots</a>). On success, if edited message is sent by the bot, the edited <a href="https://core.telegram.org/bots/api#message">Message</a> is returned, otherwise <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>No</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * <tr>
     * <td>text</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>New text of the message</td>
     * </tr>
     * <tr>
     * <td>parse_mode</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Send <a href="https://core.telegram.org/bots/api#markdown-style"><em>Markdown</em></a> or <a href="https://core.telegram.org/bots/api#html-style"><em>HTML</em></a>, if you want Telegram apps to show <a href="https://core.telegram.org/bots/api#formatting-options">bold, italic, fixed-width text or inline URLs</a> in your bot&#39;s message.</td>
     * </tr>
     * <tr>
     * <td>disable_web_page_preview</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Disables link previews for links in this message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function editMessageText(array $content)
    {
        return $this->endpoint('editMessageText', $content);
    }

    /**
     * Use this method to edit captions of messages sent by the bot or via the bot (for <a href="https://core.telegram.org/bots/api#inline-mode">inline bots</a>). On success, if edited message is sent by the bot, the edited <a href="https://core.telegram.org/bots/api#message">Message</a> is returned, otherwise <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>No</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>New caption of the message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function editMessageCaption(array $content)
    {
        return $this->endpoint('editMessageCaption', $content);
    }

    /**
     * Use this method to edit only the reply markup of messages sent by the bot or via the bot (for <a href="https://core.telegram.org/bots/api#inline-mode">inline bots</a>).  On success, if edited message is sent by the bot, the edited <a href="https://core.telegram.org/bots/api#message">Message</a> is returned, otherwise <em>True</em> is returned.<br/>Values inside $content:<br/>
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>No</td>
     * <td>Required if <em>inline_message_id</em> is not specified. Unique identifier of the sent message</td>
     * </tr>
     * <tr>
     * <td>inline_message_id</td>
     * <td>String</td>
     * <td>No</td>
     * <td>Required if <em>chat_id</em> and <em>message_id</em> are not specified. Identifier of the inline message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function editMessageReplyMarkup(array $content)
    {
        return $this->endpoint('editMessageReplyMarkup', $content);
    }

    /// Use this method to download a file

    /**
     *  Use this method to to download a file from the Telegram servers.
     * \param $telegram_file_path String File path on Telegram servers
     * \param $local_file_path String File path where save the file.
     */
    public function downloadFile($telegram_file_path, $local_file_path)
    {
        $file_url = 'https://api.telegram.org/file/bot'.$this->bot_token.'/'.$telegram_file_path;
        $in = fopen($file_url, 'rb');
        $out = fopen($local_file_path, 'wb');

        while ($chunk = fread($in, 8192)) {
            fwrite($out, $chunk, 8192);
        }
        fclose($in);
        fclose($out);
    }

    /// Set a WebHook for the bot

    /**
     *  Use this method to specify a url and receive incoming updates via an outgoing webhook. Whenever there is an update for the bot, we will send an HTTPS POST request to the specified url, containing a JSON-serialized Update. In case of an unsuccessful request, we will give up after a reasonable amount of attempts.
     *
     * If you'd like to make sure that the Webhook request comes from Telegram, we recommend using a secret path in the URL, e.g. https://www.example.com/<token>. Since nobody else knows your botâ€˜s token, you can be pretty sure itâ€™s us.
     * \param $url String HTTPS url to send updates to. Use an empty string to remove webhook integration
     * \param $certificate InputFile Upload your public key certificate so that the root certificate in use can be checked
     * \return the JSON Telegram's reply.
     */
    public function setWebhook($url, $certificate = '')
    {
        if ($certificate == '') {
            $requestBody = ['url' => $url];
        } else {
            $requestBody = ['url' => $url, 'certificate' => "@$certificate"];
        }

        return $this->endpoint('setWebhook', $requestBody, true);
    }

    /// Delete the WebHook for the bot

    /**
     *  Use this method to remove webhook integration if you decide to switch back to <a href="https://core.telegram.org/bots/api#getupdates">getUpdates</a>. Returns True on success. Requires no parameters.
     * \return the JSON Telegram's reply.
     */
    public function deleteWebhook()
    {
        return $this->endpoint('deleteWebhook', [], false);
    }

    /// Get the data of the current message

    /** Get the POST request of a user in a Webhook or the message actually processed in a getUpdates() enviroment.
     * \return the JSON users's message.
     */
    public function getData()
    {
        if (empty($this->data)) {
            $rawData = file_get_contents('php://input');

            return json_decode($rawData, true);
        } else {
            return $this->data;
        }
    }

    /// Set the data currently used
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /// Get the text of the current message

    /**
     * \return the String users's text.
     */
    public function Text()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['data'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['text'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['text'];
        }

        return @$this->data['message']['text'];
    }

    public function Caption()
    {
        $type = $this->getUpdateType();
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['caption'];
        }

        return @$this->data['message']['caption'];
    }

    /// Get the chat_id of the current message

    /**
     * \return the String users's chat_id.
     */
    public function ChatID()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['message']['chat']['id'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['chat']['id'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['chat']['id'];
        }
        if ($type == self::INLINE_QUERY) {
            return @$this->data['inline_query']['from']['id'];
        }

        return $this->data['message']['chat']['id'];
    }

    /// Get the message_id of the current message

    /**
     * \return the String message_id.
     */
    public function MessageID()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['message']['message_id'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['message_id'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['message_id'];
        }

        return $this->data['message']['message_id'];
    }

    /// Get the reply_to_message message_id of the current message

    /**
     * \return the String reply_to_message message_id.
     */
    public function ReplyToMessageID()
    {
        return $this->data['message']['reply_to_message']['message_id'];
    }

    /// Get the reply_to_message forward_from user_id of the current message

    /**
     * \return the String reply_to_message forward_from user_id.
     */
    public function ReplyToMessageFromUserID()
    {
        return $this->data['message']['reply_to_message']['forward_from']['id'];
    }

    /// Get the inline_query of the current update

    /**
     * \return the Array inline_query.
     */
    public function Inline_Query()
    {
        return $this->data['inline_query'];
    }

    /// Get the callback_query of the current update

    /**
     * \return the String callback_query.
     */
    public function Callback_Query()
    {
        return $this->data['callback_query'];
    }

    /// Get the callback_query id of the current update

    /**
     * \return the String callback_query id.
     */
    public function Callback_ID()
    {
        return $this->data['callback_query']['id'];
    }

    /// Get the Get the data of the current callback

    /**
     * \deprecated Use Text() instead
     * \return the String callback_data.
     */
    public function Callback_Data()
    {
        return $this->data['callback_query']['data'];
    }

    /// Get the Get the message of the current callback

    /**
     * \return the Message.
     */
    public function Callback_Message()
    {
        return $this->data['callback_query']['message'];
    }

    /// Get the Get the chat_id of the current callback

    /**
     * \deprecated Use ChatId() instead
     * \return the String callback_query.
     */
    public function Callback_ChatID()
    {
        return $this->data['callback_query']['message']['chat']['id'];
    }

    /// Get the Get the from_id of the current callback

    /**
     * \return the String callback_query from_id.
     */
    public function Callback_FromID()
    {
        return $this->data['callback_query']['from']['id'];
    }


    /// Get the date of the current message

    /**
     * \return the String message's date.
     */
    public function Date()
    {
        return $this->data['message']['date'];
    }

    /// Get the first name of the user
    public function FirstName()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['from']['first_name'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['from']['first_name'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['from']['first_name'];
        }

        return @$this->data['message']['from']['first_name'];
    }

    /// Get the last name of the user
    public function LastName()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['from']['last_name'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['from']['last_name'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['from']['last_name'];
        }
        if ($type == self::MESSAGE) {
            return @$this->data['message']['from']['last_name'];
        }

        return '';
    }

    /// Get the username of the user
    public function Username()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return @$this->data['callback_query']['from']['username'];
        }
        if ($type == self::CHANNEL_POST) {
            return @$this->data['channel_post']['from']['username'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['from']['username'];
        }

        return @$this->data['message']['from']['username'];
    }

    /// Get the location in the message
    public function Location()
    {
        return $this->data['message']['location'];
    }

    /// Get the update_id of the message
    public function UpdateID()
    {
        return $this->data['update_id'];
    }

    /// Get the number of updates
    public function UpdateCount()
    {
        return count($this->updates['result']);
    }

    /// Get user's id of current message
    public function UserID()
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY) {
            return $this->data['callback_query']['from']['id'];
        }
        if ($type == self::CHANNEL_POST) {
            return $this->data['channel_post']['from']['id'];
        }
        if ($type == self::EDITED_MESSAGE) {
            return @$this->data['edited_message']['from']['id'];
        }

        return $this->data['message']['from']['id'];
    }

    /// Get user's id of current forwarded message
    public function FromID()
    {
        return $this->data['message']['forward_from']['id'];
    }

    /// Get chat's id where current message forwarded from
    public function FromChatID()
    {
        return $this->data['message']['forward_from_chat']['id'];
    }

    /// Tell if a message is from a group or user chat

    /**
     *  \return BOOLEAN true if the message is from a Group chat, false otherwise.
     */
    public function messageFromGroup(): bool
    {
        if ($this->data['message']['chat']['type'] == 'private') {
            return false;
        }

        return true;
    }

    /// Get the title of the group chat

    /**
     *  \return a String of the title chat.
     */
    public function messageFromGroupTitle()
    {
        if ($this->data['message']['chat']['type'] != 'private') {
            return $this->data['message']['chat']['title'];
        }

        return '';
    }

    /// Set a custom keyboard

    /** This object represents a custom keyboard with reply options
     * \param $options Array of Array of String; Array of button rows, each represented by an Array of Strings
     * \param $onetime Boolean Requests clients to hide the keyboard as soon as it's been used. Defaults to false.
     * \param $resize Boolean Requests clients to resize the keyboard vertically for optimal fit (e.g., make the keyboard smaller if there are just two rows of buttons). Defaults to false, in which case the custom keyboard is always of the same height as the app's standard keyboard.
     * \param $selective Boolean Use this parameter if you want to show the keyboard to specific users only. Targets: 1) users that are @mentioned in the text of the Message object; 2) if the bot's message is a reply (has reply_to_message_id), sender of the original message.
     * \param $input_field_placeholder String The placeholder to be shown in the input field when the keyboard is active; 1-64 characters
     * \param $is_persistent Boolean Requests clients to always show the keyboard when the regular keyboard is hidden. Defaults to false, in which case the custom keyboard can be hidden and opened with a keyboard icon.

     * \return the requested keyboard as Json.
     */
    public function buildKeyBoard(array $options, $onetime = false, $resize = false, $selective = true, $persistent=false, $placeholder = '')
    {
        $replyMarkup = [
            'keyboard'          => $options,
            'one_time_keyboard' => $onetime,
            'resize_keyboard'   => $resize,
            'selective'         => $selective,
            'is_persistent' => $persistent,
            'input_field_placeholder' => $placeholder
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }

    /// Set an InlineKeyBoard

    /** This object represents an inline keyboard that appears right next to the message it belongs to.
     * \param $options Array of Array of InlineKeyboardButton; Array of button rows, each represented by an Array of InlineKeyboardButton
     * \return the requested keyboard as Json.
     */
    public function buildInlineKeyBoard(array $options)
    {
        $replyMarkup = [
            'inline_keyboard' => $options,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }

    /// Create an InlineKeyboardButton

    /** This object represents one button of an inline keyboard. You must use exactly one of the optional fields.
     * \param $text String; Array of button rows, each represented by an Array of Strings
     * \param $url String Optional. HTTP url to be opened when button is pressed
     * \param $callback_data String Optional. Data to be sent in a callback query to the bot when button is pressed
     * \param $switch_inline_query String Optional. If set, pressing the button will prompt the user to select one of their chats, open that chat and insert the bot‘s username and the specified inline query in the input field. Can be empty, in which case just the bot’s username will be inserted.
     * \param $switch_inline_query_current_chat String Optional. Optional. If set, pressing the button will insert the bot‘s username and the specified inline query in the current chat's input field. Can be empty, in which case only the bot’s username will be inserted.
     * \param $callback_game  String Optional. Description of the game that will be launched when the user presses the button.
     * \param $pay  Boolean Optional. Specify True, to send a <a href="https://core.telegram.org/bots/api#payments">Pay button</a>.
     * \return the requested button as Array.
     */
    public function buildInlineKeyboardButton(
        $text,
        $url = '',
        $callback_data = '',
        $switch_inline_query = null,
        $switch_inline_query_current_chat = null,
        $callback_game = '',
        $pay = ''
    )
    {
        $replyMarkup = [
            'text' => $text,
        ];
        if ($url != '') {
            $replyMarkup['url'] = $url;
        } elseif ($callback_data != '') {
            $replyMarkup['callback_data'] = $callback_data;
        } elseif (!is_null($switch_inline_query)) {
            $replyMarkup['switch_inline_query'] = $switch_inline_query;
        } elseif (!is_null($switch_inline_query_current_chat)) {
            $replyMarkup['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
        } elseif ($callback_game != '') {
            $replyMarkup['callback_game'] = $callback_game;
        } elseif ($pay != '') {
            $replyMarkup['pay'] = $pay;
        }

        return $replyMarkup;
    }

    /// Create a KeyboardButton

    /** This object represents one button of an inline keyboard. You must use exactly one of the optional fields.
     * \param $text String; Array of button rows, each represented by an Array of Strings
     * \param $request_contact Boolean Optional. If True, the user's phone number will be sent as a contact when the button is pressed. Available in private chats only
     * \param $request_location Boolean Optional. If True, the user's current location will be sent when the button is pressed. Available in private chats only
     * \return the requested button as Array.
     */
    public function buildKeyboardButton($text, $request_contact = false, $request_location = false)
    {
        $replyMarkup = [
            'text'             => $text,
            'request_contact'  => $request_contact,
            'request_location' => $request_location,
        ];

        return $replyMarkup;
    }

    /// Hide a custom keyboard

    /** Upon receiving a message with this object, Telegram clients will hide the current custom keyboard and display the default letter-keyboard. By default, custom keyboards are displayed until a new keyboard is sent by a bot. An exception is made for one-time keyboards that are hidden immediately after the user presses a button.
     * \param $selective Boolean Use this parameter if you want to show the keyboard to specific users only. Targets: 1) users that are @mentioned in the text of the Message object; 2) if the bot's message is a reply (has reply_to_message_id), sender of the original message.
     * \return the requested keyboard hide as Array.
     */
    public function buildKeyBoardHide($selective = true)
    {
        $replyMarkup = [
            'remove_keyboard' => true,
            'selective'       => $selective,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }

    /// Display a reply interface to the user
    /* Upon receiving a message with this object, Telegram clients will display a reply interface to the user (act as if the user has selected the bot‘s message and tapped ’Reply'). This can be extremely useful if you want to create user-friendly step-by-step interfaces without having to sacrifice privacy mode.
     * \param $selective Boolean Use this parameter if you want to show the keyboard to specific users only. Targets: 1) users that are @mentioned in the text of the Message object; 2) if the bot's message is a reply (has reply_to_message_id), sender of the original message.
     * \return the requested force reply as Array
     */
    public function buildForceReply($selective = true)
    {
        $replyMarkup = [
            'force_reply' => true,
            'selective'   => $selective,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }

    // Payments
    /// Send an invoice

    /**
     * Use this method to send invoices. On success, the sent <a href="https://core.telegram.org/bots/api#message">Message</a> is returned.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target private chat</td>
     * </tr>
     * <tr>
     * <td>title</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Product name</td>
     * </tr>
     * <tr>
     * <td>description</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Product description</td>
     * </tr>
     * <tr>
     * <td>payload</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Bot-defined invoice payload, 1-128 bytes. This will not be displayed to the user, use for your internal processes.</td>
     * </tr>
     * <tr>
     * <td>provider_token</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Payments provider token, obtained via <a href="/">Botfather</a></td>
     * </tr>
     * <tr>
     * <td>start_parameter</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Unique deep-linking parameter that can be used to generate this invoice when used as a start parameter</td>
     * </tr>
     * <tr>
     * <td>currency</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Three-letter ISO 4217 currency code, see <a href="https://core.telegram.org/bots/payments#supported-currencies">more on currencies</a></td>
     * </tr>
     * <tr>
     * <td>prices</td>
     * <td>Array of <a href="https://core.telegram.org/bots/api#labeledprice">LabeledPrice</a></td>
     * <td>Yes</td>
     * <td>Price breakdown, a list of components (e.g. product price, tax, discount, delivery cost, delivery tax, bonus, etc.)</td>
     * </tr>
     * <tr>
     * <td>provider_data</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>JSON-encoded data about the invoice, which will be shared with the payment provider. A detailed description of required fields should be provided by the payment provider.</td>
     * </tr>
     * <tr>
     * <td>photo_url</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>URL of the product photo for the invoice. Can be a photo of the goods or a marketing image for a service. People like it better when they see what they are paying for.</td>
     * </tr>
     * <tr>
     * <td>photo_size</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Photo size</td>
     * </tr>
     * <tr>
     * <td>photo_width</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Photo width</td>
     * </tr>
     * <tr>
     * <td>photo_height</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Photo height</td>
     * </tr>
     * <tr>
     * <td>need_name</td>
     * <td>Bool</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if you require the user's full name to complete the order</td>
     * </tr>
     * <tr>
     * <td>need_phone_number</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if you require the user's phone number to complete the order</td>
     * </tr>
     * <tr>
     * <td>need_email</td>
     * <td>Bool</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if you require the user's email to complete the order</td>
     * </tr>
     * <tr>
     * <td>need_shipping_address</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if you require the user's shipping address to complete the order</td>
     * </tr>
     * <tr>
     * <td>is_flexible</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if the final price depends on the shipping method</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a></td>
     * <td>Optional</td>
     * <td>A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>. If empty, one 'Pay <code>total price</code>' button will be shown. If not empty, the first button must be a Pay button.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendInvoice(array $content)
    {
        return $this->endpoint('sendInvoice', $content);
    }

    /// Answer a shipping query

    /**
     * Once the user has confirmed their payment and shipping details, the Bot API sends the final confirmation in the form of an <a href="https://core.telegram.org/bots/api#updates">Update</a> with the field <em>pre_checkout_query</em>. Use this method to respond to such pre-checkout queries. On success, True is returned. <strong>Note:</strong> The Bot API must receive an answer within 10 seconds after the pre-checkout query was sent.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>shipping_query_id</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the query to be answered</td>
     * </tr>
     * <tr>
     * <td>ok</td>
     * <td>Boolean</td>
     * <td>Yes</td>
     * <td>Specify True if delivery to the specified address is possible and False if there are any problems (for example, if delivery to the specified address is not possible)</td>
     * </tr>
     * <tr>
     * <td>shipping_options</td>
     * <td>Array of <a href="https://core.telegram.org/bots/api#shippingoption">ShippingOption</a></td>
     * <td>Optional</td>
     * <td>Required if <em>ok</em> is True. A JSON-serialized array of available shipping options.</td>
     * </tr>
     * <tr>
     * <td>error_message</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Required if <em>ok</em> is False. Error message in human readable form that explains why it is impossible to complete the order (e.g. "Sorry, delivery to your desired address is unavailable'). Telegram will display this message to the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function answerShippingQuery(array $content)
    {
        return $this->endpoint('answerShippingQuery', $content);
    }

    /// Answer a PreCheckout query

    /**
     * Once the user has confirmed their payment and shipping details, the Bot API sends the final confirmation in the form of an <a href="https://core.telegram.org/bots/api#">Update</a> with the field <em>pre_checkout_query</em>. Use this method to respond to such pre-checkout queries. On success, True is returned. <strong>Note:</strong> The Bot API must receive an answer within 10 seconds after the pre-checkout query was sent.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>pre_checkout_query_id</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the query to be answered</td>
     * </tr>
     * <tr>
     * <td>ok</td>
     * <td>Boolean</td>
     * <td>Yes</td>
     * <td>Specify <em>True</em> if everything is alright (goods are available, etc.) and the bot is ready to proceed with the order. Use <em>False</em> if there are any problems.</td>
     * </tr>
     * <tr>
     * <td>error_message</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Required if <em>ok</em> is <em>False</em>. Error message in human readable form that explains the reason for failure to proceed with the checkout (e.g. "Sorry, somebody just bought the last of our amazing black T-shirts while you were busy filling out your payment details. Please choose a different color or garment!"). Telegram will display this message to the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function answerPreCheckoutQuery(array $content)
    {
        return $this->endpoint('answerPreCheckoutQuery', $content);
    }

    /// Send a video note

    /**
     * As of <a href="https://telegram.org/blog/video-messages-and-telescope">v.4.0</a>, Telegram clients support rounded square mp4 videos of up to 1 minute long. Use this method to send video messages. On success, the sent <a href="https://core.telegram.org/bots/api#message">Message</a> is returned.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>video_note</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Video note to send. Pass a file_id as String to send a video note that exists on the Telegram servers (recommended) or upload a new video using multipart/form-data. <a href="https://core.telegram.org/bots/api#sending-files">More info on Sending Files »</a>. Sending video notes by a URL is currently unsupported</td>
     * </tr>
     * <tr>
     * <td>duration</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Duration of sent video in seconds</td>
     * </tr>
     * <tr>
     * <td>length</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>Video width and height</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. iOS users will not receive a notification, Android users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardmarkup">ReplyKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardremove">ReplyKeyboardRemove</a> or <a href="https://core.telegram.org/bots/api#forcereply">ForceReply</a></td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>, <a href="https://core.telegram.org/bots#keyboards">custom reply keyboard</a>, instructions to remove reply keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function sendVideoNote(array $content)
    {
        return $this->endpoint('sendVideoNote', $content);
    }

    /// Restrict Chat Member

    /**
     * Use this method to restrict a user in a supergroup. The bot must be an administrator in the supergroup for this to work and must have the appropriate admin rights. Pass True for all boolean parameters to lift restrictions from a user. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>photo</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td>Photo to send. Pass a file_id as String to send a photo that exists on the Telegram servers (recommended), pass an HTTP URL as a String for Telegram to get a photo from the Internet, or upload a new photo using multipart/form-data. <a href="https://core.telegram.org/bots/api#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * <tr>
     * <td>caption</td>
     * <td>String</td>
     * <td>Optional</td>
     * <td>Photo caption (may also be used when resending photos by <em>file_id</em>), 0-200 characters</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Sends the message <a href="https://telegram.org/blog/channels-2-0#silent-messages">silently</a>. Users will receive a notification with no sound.</td>
     * </tr>
     * <tr>
     * <td>reply_to_message_id</td>
     * <td>Integer</td>
     * <td>Optional</td>
     * <td>If the message is a reply, ID of the original message</td>
     * </tr>
     * <tr>
     * <td>reply_markup</td>
     * <td><a href="https://core.telegram.org/bots/api#inlinekeyboardmarkup">InlineKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardmarkup">ReplyKeyboardMarkup</a> or <a href="https://core.telegram.org/bots/api#replykeyboardremove">ReplyKeyboardRemove</a> or <a href="https://core.telegram.org/bots/api#forcereply">ForceReply</a></td>
     * <td>Optional</td>
     * <td>Additional interface options. A JSON-serialized object for an <a href="https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating">inline keyboard</a>, <a href="https://core.telegram.org/bots#keyboards">custom reply keyboard</a>, instructions to remove reply keyboard or to force a reply from the user.</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function restrictChatMember(array $content)
    {
        return $this->endpoint('restrictChatMember', $content);
    }

    /// Promote Chat Member

    /**
     * Use this method to promote or demote a user in a supergroup or a channel. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Pass False for all boolean parameters to demote a user. Returns True on success
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Unique identifier of the target user</td>
     * </tr>
     * <tr>
     * <td>can_change_info</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can change chat title, photo and other settings</td>
     * </tr>
     * <tr>
     * <td>can_post_messages</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can create channel posts, channels only</td>
     * </tr>
     * <tr>
     * <td>can_edit_messages</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can edit messages of other users, channels only</td>
     * </tr>
     * <tr>
     * <td>can_delete_messages</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can delete messages of other users</td>
     * </tr>
     * <tr>
     * <td>can_invite_users</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can invite new users to the chat</td>
     * </tr>
     * <tr>
     * <td>can_restrict_members</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can restrict, ban or unban chat members</td>
     * </tr>
     * <tr>
     * <td>can_pin_messages</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can pin messages, supergroups only</td>
     * </tr>
     * <tr>
     * <td>can_promote_members</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass True, if the administrator can add new administrators with a subset of his own privileges or demote administrators that he has promoted, directly or indirectly (promoted by administrators that were appointed by him)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function promoteChatMember(array $content)
    {
        return $this->endpoint('promoteChatMember', $content);
    }

    //// Export Chat Invite Link

    /**
     * Use this method to export an invite link to a supergroup or a channel. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns exported invite link as String on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function exportChatInviteLink(array $content)
    {
        return $this->endpoint('exportChatInviteLink', $content);
    }

    /// Set Chat Photo

    /**
     * Use this method to set a new profile photo for the chat. Photos can't be changed for private chats. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success. Note: In regular groups (non-supergroups), this method will only work if the ‘All Members Are Admins’ setting is off in the target group.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>photo</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a></td>
     * <td>Yes</td>
     * <td>New chat photo, uploaded using multipart/form-data</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setChatPhoto(array $content)
    {
        return $this->endpoint('setChatPhoto', $content);
    }

    /// Delete Chat Photo

    /**
     * Use this method to delete a chat photo. Photos can't be changed for private chats. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success. Note: In regular groups (non-supergroups), this method will only work if the ‘All Members Are Admins’ setting is off in the target group.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function deleteChatPhoto(array $content)
    {
        return $this->endpoint('deleteChatPhoto', $content);
    }

    /// Set Chat Title

    /**
     * Use this method to change the title of a chat. Titles can't be changed for private chats. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success. Note: In regular groups (non-supergroups), this method will only work if the ‘All Members Are Admins’ setting is off in the target group.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>title</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>New chat title, 1-255 characters</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setChatTitle(array $content)
    {
        return $this->endpoint('setChatTitle', $content);
    }

    /// Set Chat Description

    /**
     * Use this method to change the description of a supergroup or a channel. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>description</td>
     * <td>String</td>
     * <td>No</td>
     * <td>New chat description, 0-255 characters</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setChatDescription(array $content)
    {
        return $this->endpoint('setChatDescription', $content);
    }

    /// Pin Chat Message

    /**
     * Use this method to pin a message in a supergroup. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Identifier of a message to pin</td>
     * </tr>
     * <tr>
     * <td>disable_notification</td>
     * <td>Boolean</td>
     * <td>No</td>
     * <td>Pass <em>True</em>, if it is not necessary to send a notification to all group members about the new pinned message</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function pinChatMessage(array $content)
    {
        return $this->endpoint('pinChatMessage', $content);
    }

    /// Unpin Chat Message

    /**
     * Use this method to unpin a message in a supergroup chat. The bot must be an administrator in the chat for this to work and must have the appropriate admin rights. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function unpinChatMessage(array $content)
    {
        return $this->endpoint('unpinChatMessage', $content);
    }

    /// Get Sticker Set

    /**
     * Use this method to get a sticker set. On success, a StickerSet object is returned.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>name</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Short name of the sticker set that is used in <code>t.me/addstickers/</code> URLs (e.g., <em>animals</em>)</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function getStickerSet(array $content)
    {
        return $this->endpoint('getStickerSet', $content);
    }

    /// Upload Sticker File

    /**
     * Use this method to upload a .png file with a sticker for later use in createNewStickerSet and addStickerToSet methods (can be used multiple times). Returns the uploaded File on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>User identifier of sticker file owner</td>
     * </tr>
     * <tr>
     * <td>png_sticker</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a></td>
     * <td>Yes</td>
     * <td><strong>Png</strong> image with the sticker, must be up to 512 kilobytes in size, dimensions must not exceed 512px, and either width or height must be exactly 512px. <a href="https://core.telegram.org/bots/api#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function uploadStickerFile(array $content)
    {
        return $this->endpoint('uploadStickerFile', $content);
    }

    /// Create New Sticker Set

    /**
     * Use this method to create new sticker set owned by a user. The bot will be able to edit the created sticker set. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>User identifier of created sticker set owner</td>
     * </tr>
     * <tr>
     * <td>name</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Short name of sticker set, to be used in <code>t.me/addstickers/</code> URLs (e.g., <em>animals</em>). Can contain only english letters, digits and underscores. Must begin with a letter, can't contain consecutive underscores and must end in <em>“_by_&lt;bot username&gt;”</em>. <em>&lt;bot_username&gt;</em> is case insensitive. 1-64 characters.</td>
     * </tr>
     * <tr>
     * <td>title</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Sticker set title, 1-64 characters</td>
     * </tr>
     * <tr>
     * <td>png_sticker</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td><strong>Png</strong> image with the sticker, must be up to 512 kilobytes in size, dimensions must not exceed 512px, and either width or height must be exactly 512px. Pass a <em>file_id</em> as a String to send a file that already exists on the Telegram servers, pass an HTTP URL as a String for Telegram to get a file from the Internet, or upload a new one using multipart/form-data. <a href="https://core.telegram.org/bots/api#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * <tr>
     * <td>emojis</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>One or more emoji corresponding to the sticker</td>
     * </tr>
     * <tr>
     * <td>is_masks</td>
     * <td>Boolean</td>
     * <td>Optional</td>
     * <td>Pass <em>True</em>, if a set of mask stickers should be created</td>
     * </tr>
     * <tr>
     * <td>mask_position</td>
     * <td><a href="https://core.telegram.org/bots/api#maskposition">MaskPosition</a></td>
     * <td>Optional</td>
     * <td>Position where the mask should be placed on faces</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function createNewStickerSet(array $content)
    {
        return $this->endpoint('createNewStickerSet', $content);
    }

    /// Add Sticker To Set

    /**
     * Use this method to add a new sticker to a set created by the bot. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>user_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>User identifier of sticker set owner</td>
     * </tr>
     * <tr>
     * <td>name</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>Sticker set name</td>
     * </tr>
     * <tr>
     * <td>png_sticker</td>
     * <td><a href="https://core.telegram.org/bots/api#inputfile">InputFile</a> or String</td>
     * <td>Yes</td>
     * <td><strong>Png</strong> image with the sticker, must be up to 512 kilobytes in size, dimensions must not exceed 512px, and either width or height must be exactly 512px. Pass a <em>file_id</em> as a String to send a file that already exists on the Telegram servers, pass an HTTP URL as a String for Telegram to get a file from the Internet, or upload a new one using multipart/form-data. <a href="https://core.telegram.org/bots/api#sending-files">More info on Sending Files »</a></td>
     * </tr>
     * <tr>
     * <td>emojis</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>One or more emoji corresponding to the sticker</td>
     * </tr>
     * <tr>
     * <td>mask_position</td>
     * <td><a href="https://core.telegram.org/bots/api#maskposition">MaskPosition</a></td>
     * <td>Optional</td>
     * <td>Position where the mask should be placed on faces</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function addStickerToSet(array $content)
    {
        return $this->endpoint('addStickerToSet', $content);
    }

    /// Set Sticker Position In Set

    /**
     * Use this method to move a sticker in a set created by the bot to a specific position . Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>sticker</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>File identifier of the sticker</td>
     * </tr>
     * <tr>
     * <td>position</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>New sticker position in the set, zero-based</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function setStickerPositionInSet(array $content)
    {
        return $this->endpoint('setStickerPositionInSet', $content);
    }

    /// Delete Sticker From Set

    /**
     * Use this method to delete a sticker from a set created by the bot. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>sticker</td>
     * <td>String</td>
     * <td>Yes</td>
     * <td>File identifier of the sticker</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function deleteStickerFromSet(array $content)
    {
        return $this->endpoint('deleteStickerFromSet', $content);
    }

    /// Delete a message

    /**
     * Use this method to delete a message. A message can only be deleted if it was sent less than 48 hours ago. Any such recently sent outgoing message may be deleted. Additionally, if the bot is an administrator in a group chat, it can delete any message. If the bot is an administrator in a supergroup, it can delete messages from any other user and service messages about people joining or leaving the group (other types of service messages may only be removed by the group creator). In channels, bots can only remove their own messages. Returns True on success.
     * <table>
     * <tr>
     * <td><strong>Parameters</strong></td>
     * <td><strong>Type</strong></td>
     * <td><strong>Required</strong></td>
     * <td><strong>Description</strong></td>
     * </tr>
     * <tr>
     * <td>chat_id</td>
     * <td>Integer or String</td>
     * <td>Yes</td>
     * <td>Unique identifier for the target chat or username of the target channel (in the format \c \@channelusername)</td>
     * </tr>
     * <tr>
     * <td>message_id</td>
     * <td>Integer</td>
     * <td>Yes</td>
     * <td>Identifier of the message to delete</td>
     * </tr>
     * </table>
     * \param $content the request parameters as array
     * \return the JSON Telegram's reply.
     */
    public function deleteMessage(array $content)
    {
        return $this->endpoint('deleteMessage', $content);
    }

    /// Receive incoming messages using polling

    /** Use this method to receive incoming updates using long polling.
     * \param $offset Integer Identifier of the first update to be returned. Must be greater by one than the highest among the identifiers of previously received updates. By default, updates starting with the earliest unconfirmed update are returned. An update is considered confirmed as soon as getUpdates is called with an offset higher than its update_id.
     * \param $limit Integer Limits the number of updates to be retrieved. Values between 1—100 are accepted. Defaults to 100
     * \param $timeout Integer Timeout in seconds for long polling. Defaults to 0, i.e. usual short polling
     * \param $update Boolean If true updates the pending message list to the last update received. Default to true.
     * \return the updates as Array.
     */
    public function getUpdates($offset = 0, $limit = 100, $timeout = 0, $update = true)
    {
        $content = ['offset' => $offset, 'limit' => $limit, 'timeout' => $timeout];
        $this->updates = $this->endpoint('getUpdates', $content);
        if ($update) {
            if (array_key_exists('result', $this->updates) && is_array($this->updates['result']) && count($this->updates['result']) >= 1) { //for CLI working.
                $last_element_id = $this->updates['result'][count($this->updates['result']) - 1]['update_id'] + 1;
                $content = ['offset' => $last_element_id, 'limit' => '1', 'timeout' => $timeout];
                $this->endpoint('getUpdates', $content);
            }
        }

        return $this->updates;
    }

    /// Serve an update

    /** Use this method to use the bultin function like Text() or Username() on a specific update.
     * \param $update Integer The index of the update in the updates array.
     */
    public function serveUpdate($update)
    {
        $this->data = $this->updates['result'][$update];
    }

    /// Return current update type

    /**
     * Return current update type `False` on failure.
     *
     * @return bool|string
     */
    public function getUpdateType()
    {
        $update = $this->data;
        if (isset($update['inline_query'])) {
            return self::INLINE_QUERY;
        }
        if (isset($update['callback_query'])) {
            return self::CALLBACK_QUERY;
        }
        if (isset($update['edited_message'])) {
            return self::EDITED_MESSAGE;
        }
        if (isset($update['message']['text'])) {
            return self::MESSAGE;
        }
        if (isset($update['message']['photo'])) {
            return self::PHOTO;
        }
        if (isset($update['message']['video'])) {
            return self::VIDEO;
        }
        if (isset($update['message']['audio'])) {
            return self::AUDIO;
        }
        if (isset($update['message']['voice'])) {
            return self::VOICE;
        }
        if (isset($update['message']['contact'])) {
            return self::CONTACT;
        }
        if (isset($update['message']['location'])) {
            return self::LOCATION;
        }
        if (isset($update['message']['reply_to_message'])) {
            return self::REPLY;
        }
        if (isset($update['message']['animation'])) {
            return self::ANIMATION;
        }
        if (isset($update['message']['sticker'])) {
            return self::STICKER;
        }
        if (isset($update['message']['document'])) {
            return self::DOCUMENT;
        }
        if (isset($update['channel_post'])) {
            return self::CHANNEL_POST;
        }

        return false;
    }

    private function sendAPIRequest($url, array $content, $post = true)
    {
        if (isset($content['chat_id'])) {
            $url = $url.'?chat_id='.$content['chat_id'];
            unset($content['chat_id']);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }
        // 		echo "inside curl if";
        if (!empty($this->proxy)) {
            // 			echo "inside proxy if";
            if (array_key_exists('type', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy['type']);
            }

            if (array_key_exists('auth', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['auth']);
            }

            if (array_key_exists('url', $this->proxy)) {
                // 				echo "Proxy Url";
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy['url']);
            }

            if (array_key_exists('port', $this->proxy)) {
                // 				echo "Proxy port";
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
            }
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if ($result === false) {
            $result = json_encode(
                ['ok' => false, 'curl_error_code' => curl_errno($ch), 'curl_error' => curl_error($ch)]
            );
        }
        curl_close($ch);
        if ($this->log_errors) {
            if (class_exists('TelegramErrorLogger')) {
                $loggerArray = ($this->getData() == null) ? [$content] : [$this->getData(), $content];
                TelegramErrorLogger::log(json_decode($result, true), $loggerArray);
            }
        }

        return $result;
    }
}

// Helper for Uploading file using CURL
if (!function_exists('curl_file_create')) {
    function curl_file_create($filename, $mimetype = '', $postname = '')
    {
        return "@$filename;filename="
            .($postname ?: basename($filename))
            .($mimetype ? ";type=$mimetype" : '');
    }
}

<?php
date_default_timezone_set("asia/tehran");

//const USER_NAME = 'playmak1_sighe_bot_user';
//const PASSWORD = 'sighe_bot_user';
//const DATABASE = 'playmak1_Freelancerly_DB';


// Database
const HOST_NAME = 'localhost';
const USER_NAME = 'freelan1_user';
const PASSWORD = 'freelan1_pass';
const DATABASE = 'freelan1_Freelancerly_DB';
$connection = mysqli_connect(HOST_NAME, USER_NAME, PASSWORD, DATABASE);


// The utf8mb4 character set is a version of UTF-8 that supports 4-byte characters,
// which is necessary for handling all Unicode characters including emojis.
mysqli_set_charset($connection, 'utf8mb4');


if (!$connection) {
    // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "<u>مشکل اتصال به پایگاه داده!</u>", 'parse_mode' => "HTML"]);
    die("Connection failed: " . mysqli_connect_error());
}

// Bot TOKEN
$bot_token = "6200903131:AAEROPO3w13V-ZZiE9v9GONLBi0LET3Pnqc";

<?php

/**********
 * HELPER
 **********/

/*
function prettyJson($jsonObject)
{
    return json_encode($jsonObject, JSON_PRETTY_PRINT);
}
*/

/**
 * Checks if the given message data contains a bot command.
 *
 * @param array $last_msg_data The message data to check.
 * @return bool Returns true if the message data contains a bot command, false otherwise.
 */
function isBotCommand($last_msg_data): bool
{
    return isset($last_msg_data['message']['entities'][0]['type']) && $last_msg_data['message']['entities'][0]['type'] == 'bot_command';
}

function is_valid_invite_link(string $text): bool
{
    // "text": "/start 133084833"
    return preg_match('/^\/start ([1-9]\d{2,10})$/', trim($text)) === 1;
}


function extractInvitationLinkParts(string $text)
{
    // Extract the command and parameters
    $command_parts = explode(' ', $text);
    $command = $command_parts[0]; // /start
    $user_id_param = $command_parts[1]; // id
    $user_id_param = (int)filter_var($user_id_param, FILTER_SANITIZE_NUMBER_INT);

    // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'Parameter : ' . $user_id_param, 'reply_to_message_id' => $message_id]);
    return $user_id_param;
}

//$telegram->messageFromGroup();
// we can also use getChat()/getData() instead.
function isPrivateChat($last_msg_data): bool
{
    if (isset($last_msg_data['message']['chat']['type']) && $last_msg_data['message']['chat']['type'] == 'private') {
        return true;
    } else {
        return false;
    }
}

function isValidAdvId($str, string $param)
{
    // Explanation:
    // - `^`: It matches the starting position of the string.
    // - `$param`: Match the value of the $param variable literally.
    // - `\d{1,5}`: Match any digit between 1 to 5 times.
    // - `$`: It matches the ending position of the string.
    //
    // This code will return `1` if the string is in the specified format, otherwise `0`

    // supports 999999 adv (6 digit)
    return preg_match("/^{$param}\d{1,6}$/", $str);
}

function extractId($str, $param)
{
    $parts = explode($param, $str);
    if (count($parts) > 1) {
        return intval($parts[1]);
    } else {
        return false;
    }
}

function isValidId($string)
{
    // Check if string starts with "@" and has at least five alpha-numeric characters or underscores after it
    return preg_match('/^@[a-zA-Z0-9_]{5,}$/', $string);
}

function isValidPhone($string)
{
    // Check if string is 11 digits long and only contains digits
    //    return preg_match('/^\d{11}$/', $string);

    // Check if string is 11 digits long and starts with "09"
    return preg_match('/^09\d{9}$/', $string);
}

function calcStatus($adv_is_paid, $adv_is_approved, $adv_is_assigned)
{

    $status_text = '';

    if ($adv_is_paid == 0 and $adv_is_approved == 0 and $adv_is_assigned == 0) {
        $status_text = 'پرداخت نشده';
        $status_text .= " 💳";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == -1 and $adv_is_assigned == 0) {
        $status_text = 'رد شده';
        $status_text .= " ❌";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == -2 and $adv_is_assigned == 0) {
        // todo : fix
        // همان رد شده بدون بازگرداندن پول
        $status_text = 'حذف شده';
        $status_text .= " 🗑";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 0 and $adv_is_assigned == 0) {
        $status_text = 'در انتظار تایید مدیر';
        $status_text .= " ⏳";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 0) {
        $status_text = 'منتشر شده';
        $status_text .= " ✅";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 1) {
        $status_text = 'واگذار شده';
        $status_text .= " 🔴";
    }
    // todo: implement expiration
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 2) {
        $status_text = 'منقضی شده';
        $status_text .= " ⚫️";
    }

    return $status_text;
}

function returnButton(): array
{
    return [
        [
            ['text' => "بازگشت به منو ↪️"],
        ],
    ];
}

function doubleReturnButton(): array
{
    return [
        [
            ['text' => "بازگشت به منو ↪️"],['text' => "/admin"],
        ],
    ];
}

// $string = "/approveReq_76_133084833";
function split_string($string)
{
    return explode('_', $string);
}

/*
function showBackButton(): void
{
}
*/
/*
function prettyJsonPrint($jsonObject): void
{
    echo "<pre>" . json_encode($jsonObject, JSON_PRETTY_PRINT) . "<pre/>";
}
*/

function is_channel($last_msg_data): bool
{
    if (isset($last_msg_data['channel_post']) && $last_msg_data['channel_post']['chat']['type'] == 'channel') {
        return true;
    }
    else {
        return false;
    }
}


/********************
 * DATABASE
 *******************/
function hasUserThisUnpaidAdv($user_id, $advId): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM advertisements WHERE (adv_user_numeric_id = ? and adv_id = ? and adv_is_paid = 0)");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $advId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $count === 1;
}

function userExists($user_from_id): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM users WHERE user_numeric_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $count === 1;
}

/**
 * @param $connection
 * @param $user_from_id
 * @param $user_username_id
 * @return bool
 */
function insertUser($user_from_id, $user_username_id): bool
{
    global $connection;
    if (!empty($user_username_id)){
        $stmt = mysqli_prepare($connection, "INSERT INTO users (user_numeric_id, user_username_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "is", $user_from_id, $user_username_id);
    }
    else {
        $stmt = mysqli_prepare($connection, "INSERT INTO users (user_numeric_id) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    }

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function insertInvite($inviter_id, $invited_id): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "INSERT INTO invitations (inviter_user_numerical_id, invited_user_numerical_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $inviter_id, $invited_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function getUserStep($user_from_id): int
{
    // object-oriented style
    global $connection;
    $stmt = $connection->prepare("SELECT user_step FROM users WHERE user_numeric_id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();
    $result = $stmt->get_result(); // result is an array
    $step = $result->fetch_array()[0];
    $stmt->close();
    return $step;
}

function setUserStep($user_from_id, $step): bool
{
    global $connection;
    $stmt = $connection->prepare("UPDATE users SET user_step = ? WHERE user_numeric_id = ?");
    $stmt->bind_param("ii", $step, $user_from_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    return $affected_rows == 1;
}

function getCoins($user_from_id)
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT user_coins_count FROM users WHERE user_numeric_id = ?");
    if ($stmt === false) {
        // Handle the error case here.
        return 0; // Or some other default value.
    }
    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt); // result is an array
    $coins = mysqli_fetch_array($result)[0];
    mysqli_stmt_close($stmt);
    return $coins;
}

function setCoins($user_from_id, $coins): bool
{
    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = ? WHERE user_numeric_id = ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "ii", $coins, $user_from_id);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}

function increaseCoins($user_from_id, $coins_to_add): bool
{
    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = user_coins_count + ? WHERE user_numeric_id = ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "ii", $coins_to_add, $user_from_id);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}

function subtractCoins($user_from_id, $coins_to_subtract): bool
{
    if ($coins_to_subtract == 0) {return true;}

    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = user_coins_count - ? WHERE user_numeric_id = ? AND user_coins_count >= ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "iii", $coins_to_subtract, $user_from_id, $coins_to_subtract);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}


function getUserDataByID($user_from_id): int
{
    // object-oriented style
    global $connection;
    $stmt = $connection->prepare("SELECT user_step FROM users WHERE user_numeric_id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();
    $result = $stmt->get_result(); // result is an array
    $step = $result->fetch_array()[0];
    $stmt->close();
    return $step;
}

/*
function getUserById($userId): array
{

    global $connection;
    $stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    // Fetch the data and store them in variables
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $username = $user['username'];
    $email = $user['email'];

    // Close the statement and the database connection
    $stmt->close();
    $connection->close();

    // Return the user data stored in variables
    return [
        'userId' => $userId,
        'username' => $username,
        'email' => $email
    ];
}
*/

function getUserById($userId)
{
    global $connection;

    try {
        $stmt = $connection->prepare("SELECT * FROM users WHERE user_numeric_id = ?");
        if (!$stmt) {throw new Exception("Failed to prepare statement");}

        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {throw new Exception("Failed to execute statement");}

        $result = $stmt->get_result();
        if (!$result) {throw new Exception("Failed to get result set");}

        $user = $result->fetch_assoc();
        if (!$user) {throw new Exception("User not found");}

        $user_username_id = $user['user_username_id'];
        $user_numeric_id = $user['user_numeric_id'];
        //$email = $user['email'];

        $stmt->close();

        return [
            'user_username_id' => $user_username_id,
            'user_numeric_id' => $user_numeric_id
        ];
    }
    catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}
//$userData = getUserById($user_from_id);
//if ($userData) {
//    $retrievedUserId = $userData['userId'];
//    $retrievedUsername = $userData['username'];
//    $retrievedEmail = $userData['email'];
//}


function insertAdvertisement($chat_id, array $data)
{
    global $connection, $telegram;

    $user_numeric_id = $data['adv_user_numeric_id'];
    $text = $data['adv_text'];
    $contact_info = $data['adv_contact_info'];
    // $required_skills = $data['adv_required_skills'];
    $creation_date = $data['adv_creation_date'];

    $stmt = mysqli_prepare($connection, "INSERT INTO advertisements (adv_user_numeric_id, adv_text, adv_contact_info, adv_creation_date) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }


    mysqli_stmt_bind_param($stmt, "isss", $user_numeric_id, $text, $contact_info, $creation_date);
    $result = mysqli_stmt_execute($stmt);

    if (!$result) {
        // Handle error
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    // Get the ID of the inserted row
    $inserted_id = mysqli_insert_id($connection);

    // Close statement and connection
    mysqli_stmt_close($stmt);

    // Return the inserted row id
    return $inserted_id;
}



function countUnpaidAdvertisements($user_from_id)
{
    global $connection, $telegram, $chat_id;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM advertisements WHERE adv_user_numeric_id = ? AND adv_is_paid = 0");
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $count;
}

function setIsPaid($adv_id, $user_from_id, $value): bool
{
    global $connection;

    $stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_paid` = {$value} WHERE adv_id = ? AND adv_user_numeric_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $adv_id, $user_from_id);
    mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function fixStringOrder(string $string) {
    $pattern = '/(.*)@(\w+)/u';
    $replacement = '$2@$1';
    $fixedString = preg_replace($pattern, $replacement, $string);

    return $fixedString;
}

function setIsApproved($adv_id, $user_from_id, $value): bool
{
    global $connection, $telegram, $chat_id;

    //$stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_approved` = {$value} WHERE adv_id = ? AND adv_user_numeric_id = ?");
    $stmt = mysqli_prepare($connection, "UPDATE advertisements SET adv_is_approved = ? , adv_publication_date = ? WHERE adv_id = ? AND adv_user_numeric_id = ?");

    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    $timestamp = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, "isii", $value, $timestamp, $adv_id, $user_from_id);

    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function setIsAssigned($adv_id, $user_from_id, $value): bool
{
    global $connection, $telegram, $chat_id;

    $stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_assigned` = ? , adv_assignment_date = ? WHERE adv_id = ? AND adv_user_numeric_id = ?");
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    $timestamp = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, "isii", $value, $timestamp, $adv_id, $user_from_id);


    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function setAdvertisementMessageId($adv_id, $adv_message_id): bool
{
    global $connection, $telegram, $chat_id;

    $query = "UPDATE `advertisements` SET `adv_message_id` = ? WHERE `advertisements`.`adv_id` = ?";

    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ii", $adv_message_id, $adv_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected_rows == 1) {
        return true;
    } else {
        return false;
    }
}

function getAdvertisementMessage_id($adv_id)
{
    global $connection;
    $query = "SELECT `adv_message_id` FROM `advertisements` WHERE `adv_id` = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $adv_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $adv_message_id);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($adv_message_id)) {
        return $adv_message_id;
    } else {
        return false;
    }
}


/********************
 * JSON
 *******************/
function is_admin($user_id, $fileName): bool
{
    // Read JSON file
    $json_data = file_get_contents($fileName);

    // Decode JSON data
    $data = json_decode($json_data, true);

    // Check if value and key exist
    $found = false;
    foreach ($data['admins'] as $admin) {
        if (in_array($user_id, $admin)) {
            $found = true;
            break;
        }
    }

    if ($found) {
        return true;
    } else {
        return false;
    }
}

function add_admin($key, $value): bool
{
    // Read JSON file
    $json_data = file_get_contents('admins.json');

    // Decode JSON data
    $data = json_decode($json_data, true);

    // Add new admin to array
    $new_admin = [$key => $value];
    $data['admins'][] = $new_admin;

    // Encode data back to JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT);

    if (file_put_contents('admins.json', $json_data) !== false) {
        // Return true if JSON data was successfully saved
        return true;
    } else {
        // Return false if an error occurred while saving the JSON data
        return false;
    }
}

function createJsonFile(string $file_path): bool
{

    $data = [];
//    $data['message'] = 'Hello, world!';
//    $data['test'] = 'Hello, world!-2';


    // convert array to json style
    $json_data = json_encode($data, JSON_PRETTY_PRINT);

    if (file_put_contents($file_path, $json_data)) {
        return true;
    } else {
        return false;
    }
}

function createJsonFile2(string $file_path , $data = []): bool
{

    // $data = [];
    // $data['message'] = 'Hello, world!';
    // $data['test'] = 'Hello, world!-2';


    // convert array to json style
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($file_path, $json_data)) {
        return true;
    }
    else {
        return false;
    }
}


function addOrUpdateJson($file_path, string $key, $value): bool
{
    //$file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

    // Check if the file exists, create a new file if it does not.
    if (!file_exists($file_path)) {
        $success = file_put_contents($file_path, "{}");
        if ($success === false) {
            // Error handling: Return false if file creation fails
            return false;
        }
    }

    $jsonData = file_get_contents($file_path);
    if ($jsonData === false) {
        // Error handling: Return false if file reading fails
        return false;
    }

    $data = json_decode($jsonData, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        // Error handling: Return false if JSON decoding fails
        return false;
    }

    if ($data[$key] !== $value) { // check if value has changed
        $data[$key] = $value;
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            // Error handling: Return false if JSON encoding fails
            return false;
        }

        $success = file_put_contents($file_path, $jsonData);
        if ($success === false) {
            // Error handling: Return false if file writing fails
            return false;
        }
    }

    return true;
}

function deleteJsonFile(string $filename): bool
{
    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

    // Check if the file exists
    if (file_exists($file_path)) {
        // Delete the file
        unlink($filename);
        return true;
    } else {
        return false;
    }
}

function getValueByKeyFromJson($file_path, string $key)
{
    //$file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

//    if (!file_exists($file_path)) {
//        throw new Exception('File not found.');
//    }

    if (!file_exists($file_path)) {
        return false;
    }

    $jsonData = file_get_contents($file_path);
    $data = json_decode($jsonData, true);

//    $advData = [
//        'user_username_id' => $data['user_username_id'],
//        'adv_user_numeric_id' => $data['adv_user_numeric_id'],
//        'adv_text' => $data['adv_text'],
//        'adv_contact_info' => $data['adv_contact_info'],
//        'adv_timestamp' => $data['adv_timestamp']
//    ];

//    return $advData;

    return $data[$key];
}


/*********
 * BOX AND BUTTONS
 ********/

function displayMainMenuButtons($chat_id): array
{
    global $telegram,$is_admin;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "ثبت آگهی جدید 📝"], ['text' => "آگهی‌های ثبت شده 🗄"],
        ],
        [
            ['text' => "پشتیبانی 💬"], ['text' => "سکه رایگان 🌕"],
        ],
        [
            ['text' => "واسطه کردن ادمین 🤝"], ['text' => "واگذاری پروژه به تیم ما"],
        ],
        [
            ['text' => "پیشگیری از کلاهبرداری"]
        ],
    ];

//    [
//        ['text' => "انجام تبلیغات"], ['text' => "پیشگیری از کلاهبرداری"]
//    ],
//    [
//        ['text' => "انتخاب زبان ربات"]
//    ],

    $append_array = [['text' => "/admin"]];
    if ($is_admin){
        $rep_KeyB_BTNs_Main[] = $append_array;
    }


    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی اصلی'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی اصلی',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function displayAdminMenu($chat_id): array
{
    global $telegram;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "اگهی های در انتظار تایید (؟)"]
        ],
        [
            ['text' => "ارسال پیام همگانی"], ['text' => "حالت فوروارد اختصاصی"],
        ],
        [
            ['text' => "تغییر هزینه ثبت آگهی"], ['text' => "تغییر سکه های کاربر"],
        ],
        [
            ['text' => "ارسال پیام همگانی"], ['text' => "ارسال پیام به کاربر"],
        ],
        [
            ['text' => "غیرفعال/فعالسازی ربات"], ['text' => " بلاک/آنبلاک کاربر"],
        ],
        [
            ['text' => "تعداد کاربران جدید"], ['text' => "آمار کاربران"],
        ],
        [
            ['text' => "ایجاد کد تخفیف"], ['text' => "جایزه به کاربر"],
        ],
        [
            ['text' => "آمار آگهی ها"], ['text' => "تغییر وضعیت آگهی"],
        ],
        [
            ['text' => "تنظیمات جوین اجباری"],['text' => "تخفیف همگانی"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی مدیر'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی مدیر',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function displayEditUserCoinsMenu($chat_id): array
{
    global $telegram, $message_id;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "کاهش سکه"], ['text' => "افزایش سکه"]
        ],
        [
            ['text' => "تعیین مقدار دقیق سکه"]
        ],
        [
            ['text' => "بازگشت به منو ↪️"],['text' => "/admin"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'تغییر سکه ها'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منو تغییر سکه ها',
        'reply_markup' => $replyKeyboard_Main,
        'reply_to_message_id' => $message_id,
        'allow_sending_without_reply' => true
    ];
}

function getAdvertisementData($advId, $user_from_id)
{

    global $connection, $telegram, $chat_id;

    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_id = ? AND adv_user_numeric_id = ?");
    if (!$stmt) {
        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ii", $advId, $user_from_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    // Get the results
    $result = mysqli_stmt_get_result($stmt);

    $adv_info = [];

    // Check the result count
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        $adv_info['adv_id'] = $row['adv_id'];
        $adv_info['adv_user_numeric_id'] = $row['adv_user_numeric_id'];
        $adv_info['adv_message_id'] = $row['adv_message_id'];
        $adv_info['adv_text'] = $row['adv_text'];
        $adv_info['adv_contact_info'] = $row['adv_contact_info'];
        $adv_info['adv_required_skills'] = $row['adv_required_skills'];
        $adv_info['adv_is_paid'] = $row['adv_is_paid'];
        $adv_info['adv_is_approved'] = $row['adv_is_approved'];
        $adv_info['adv_is_assigned'] = $row['adv_is_assigned'];
        $adv_info['adv_creation_date'] = $row['adv_creation_date'];
        $adv_info['adv_publication_date'] = $row['adv_publication_date'];
        $adv_info['adv_assignment_date'] = $row['adv_assignment_date'];
    }
    else {
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    //  mysqli_close($connection);
    return $adv_info;
}

function showMyAdvertisementsList($chat_id, $user_numeric_id): void
{

    global $connection, $telegram;
    $rowCount = 0;
    $whole_list = "";

    // 10 last ones from lowest adv_id to the last.
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC");
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC LIMIT {$count}");
    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? ORDER BY adv_id ASC");
    mysqli_stmt_bind_param($stmt, "i", $user_numeric_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);



    if ($result !== false) {

        $long_adv_text = [];

        while ($row = mysqli_fetch_assoc($result)) {


            $status_text = calcStatus($row['adv_is_paid'], $row['adv_is_approved'], $row['adv_is_assigned']);
            $text = "➖➖➖➖➖ /id" . $row['adv_id'] . " ➖➖➖➖➖
● وضعیت آگهی: " . $status_text . "
● متن آگهی:     
" . $row['adv_text'] . "
";

            $adv_length = mb_strlen($text, 'UTF-8');

            // todo : fix
//            $array = ["string1", "string2", "string3"]; // Replace with your array of strings
//
//            $totalLength = 0;
//
//            foreach ($array as $text) {
//                $length = mb_strlen($text, 'UTF-8');
//                $totalLength += $length;
//            }
//
//            if ($totalLength <= 4000) {
//                echo "Total length is not greater than 4000";
//            } else {
//                echo "Total length is greater than 4000";
//            }

            if ($adv_length >= 4000) { // telegram max limit = 4096
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
                //$long_adv_text[] = $text;
                //continue;
            }
            else {
                $long_adv_text[] = $text;
                //continue;
            }


            //$whole_list = $whole_list . $text;
        }

        $rowCount = mysqli_num_rows($result);
    }



    if ($rowCount == 0) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "شما هنوز آگهی ثبت شده‌ای ندارید."]);
    }
    else { // number of advertisements.
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "" . $rowCount . " آگهی یافت شد."]);
//        foreach ($long_adv_text as $text) {
//            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
//        }
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $whole_list]);
    }


    mysqli_stmt_close($stmt);
}

function createChannelBox($channel_id, string $text)
{
//    global $telegram;

//    $inlineButton = $telegram->buildInlineKeyboardButton("برای درج آگهیت کلیک کن", 'https://t.me/Freelancerly_bot');
//    $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineButton]]);
    $inlineKeyboard = createChannelAdvKeyboard();

    return [
        'chat_id' => $channel_id,
        'text' => $text,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        'allow_sending_without_reply' => true
    ];
}

function createChannelAdvKeyboard()
{
    global $telegram;

    $inlineButton = $telegram->buildInlineKeyboardButton("برای درج آگهیت کلیک کن", 'https://t.me/Freelancerly_bot');
    $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineButton]]);

    return $inlineKeyboard;
}

function createMainAdvertisementBox($chat_id, array $data)
{
    global $telegram, $user_from_id, $adv_price_coin;
//    $user_coins = getCoins($data['user_from_id']);
    $user_coins = getCoins($user_from_id);
    $payment_url = "https://www.freelancerly.ir/freelancerly_bot/pay/pay-confirm.php?user_id={$user_from_id}&adv_id={$data['adv_id']}";

    $status_text = calcStatus($data['adv_is_paid'], $data['adv_is_approved'], $data['adv_is_assigned']);

    // todo: expired section


    $saved_adv = "
● کد آگهی: {$data['adv_id']}
● وضعیت آگهی: {$status_text}
● متن آگهی:
{$data['adv_text']}
● اطلاعات تماس:
{$data['contact_info']}
";

    if (!$data['adv_is_paid'] and $adv_price_coin != 0) {
        $saved_adv .= "

⚡️ آگهی شما پس از پرداخت و تأیید ادمین به صورت آنی در کانال منتشر می‌شود.
⚠️ برای کند نبودن روند پرداخت بانکی VPN خود را خاموش کنید.
";
    }
    elseif (!$data['adv_is_paid'] and $adv_price_coin == 0){
        $saved_adv .= "

⚡️ آگهی شما پس از تأیید ادمین به صورت آنی در کانال منتشر می‌شود.
";
    }

    //$saved_adv = $data['adv_pre_text'] . $saved_adv;
    if (isset($data['adv_pre_text'])) {
        $saved_adv = $data['adv_pre_text'] . $saved_adv;
    }
    if (isset($data['adv_post_text'])) {
        $saved_adv = $saved_adv . $data['adv_post_text'];
    }


    // inline buttons
    // todo : check if is assigned , is paid and is approved.

    if ($data['adv_is_paid'] == 0 && $data['adv_is_approved'] == 0 && $data['adv_is_assigned'] == 0) {
        if ($adv_price_coin == 0) {
            $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " سکه 🌕 (رایگان)", null, "walletPay_" . $data['adv_id']);
            $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("موجودی سکه = " . $user_coins, 'https://t.me/freelancerly_bot');
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2], [$inlineKeyboardButton3]]);
        }
        else {
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " هزار تومان 💳", $payment_url);
            $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " سکه 🌕", null, "walletPay_" . $data['adv_id']);
            $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("پرداخت با کارت به کارت + هدیه", null, "card2card_" . $data['adv_id']);
            $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("موجودی سکه = " . $user_coins, 'https://t.me/freelancerly_bot');
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1, $inlineKeyboardButton2], [$inlineKeyboardButton4], [$inlineKeyboardButton3]]);
        }
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == -1 && $data['adv_is_assigned'] == 0) {
        $status_text = 'تایید نشده';
        $status_text .= " ❌";
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == -2 && $data['adv_is_assigned'] == 0) {
        // حذف شده همان رد شدن بدون بازگرداندن پول است.
        $status_text = 'تایید نشده';
        $status_text .= " ❌";
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 0 && $data['adv_is_assigned'] == 0) {
        // $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("در انتظار تایید مدیر ⏳", 'https://t.me/Freelancerly_bot');
        $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("پرداخت شده ✅", 'https://t.me/freelancerly_bot');
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 0) {
        $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("🔴 ویرایش آگهی به واگذار شده", null, "assigned_" . $data['adv_id']);
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2], [$inlineKeyboardButton3]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 1) {
//        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("🔴 واگذار شده", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton4]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 2) {
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("⚫️ منقضی شده", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton4]]);
    }


    return [
        'chat_id' => $chat_id,
        'text' => $saved_adv,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        'allow_sending_without_reply'
    ];
}

function escape_reserved_chars(string $text): string
{
    $reserved_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    // Add any other reserved characters to the array as needed
    // '`',
    foreach ($reserved_chars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }

    return $text;
}


function createAdminManageBox($admin_chat_id, array $data)
{
    global $telegram;

    if (!empty($data['username'])){$data['username'] = "@" . $data['username'];}
    else {$data['username'] = 'ندارد';}

//    $data['user_from_id'] = '`' . $data['user_from_id'] . '`';
//    $data['adv_text'] = '`' . $data['adv_text'] . '`';

//    $data['user_from_id'] = '<code>' . $data['user_from_id'] . '</code>';
//    $data['adv_text'] = '<code>' . $data['adv_text'] . '</code>';


    $saved_adv = "
#درخواست_تایید 
● نام کاربری: {$data['username']}
● آیدی عددی: {$data['user_from_id']}

● کد آگهی: {$data['adv_id']}
● متن آگهی:
{$data['adv_text']}
● اطلاعات تماس:
{$data['contact_info']}
";


//    $saved_adv = escape_reserved_chars($saved_adv);
//    $saved_adv = "#درخواست_تایید
//    " . $saved_adv;

    // Inline Buttons
    if ($data['adv_is_paid'] && $data['adv_is_approved'] == 0) {

        $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("تایید ✅", null, "approveReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("رد ❌", null, "rejectReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("ویرایش 📄", null, "editReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("حذف 🗑", null, "deleteReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton5 = $telegram->buildInlineKeyboardButton("کپی متن آگهی", null, null, null, $data['adv_text']);
        $inlineKeyboardButton6 = $telegram->buildInlineKeyboardButton("کپی آیدی عددی", null, null, null, $data['user_from_id']);

        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2, $inlineKeyboardButton1], [$inlineKeyboardButton4, $inlineKeyboardButton3], [$inlineKeyboardButton6, $inlineKeyboardButton5]]);
    }

    return [
        'chat_id' => $admin_chat_id,
        'text' => $saved_adv,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        //'allow_sending_without_reply'
        //'parse_mode' => 'HTML'
    ];
}


// Function to reward a user for inviting a new user
function reward_user($user_id): void
{
    // Add your code here to reward the user
}

// Function to check if a user has already been rewarded for a specific invitation
function is_rewarded($inviter_id, $invited_id): void
{
    // Add your code here to check if the invited user has already been rewarded by the inviter
    // Return true if the user has already been rewarded, false otherwise
}

// Function to mark a user as rewarded for a specific invitation
function mark_rewarded($inviter_id, $invited_id): void
{
    // Add your code here to mark the invited user as rewarded by the inviter
}

/*
function show_all_data($my_Chat_Id): void
{
    global $telegram;

    // getMe()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getMe()' . "\n" . prettyJson($telegram->getMe())]);
    // getChat()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChat()' . "\n" . prettyJson($telegram->getChat(['chat_id' => $my_Chat_Id]))]);
    // getChatAdministrators()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatAdministrators()' . "\n" . prettyJson($telegram->getChatAdministrators(['chat_id' => $my_Chat_Id]))]);
    // getChatMember()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatMember()' . "\n" . prettyJson($telegram->getChatMember(['chat_id' => $my_Chat_Id]))]);
    // getChatMembersCount()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatMembersCount()' . "\n" . prettyJson($telegram->getChatMembersCount(['chat_id' => $my_Chat_Id]))]);
    // getData()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getData() | file_get_contents()' . "\n" . prettyJson($telegram->getData())]);
    // getUpdateType()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getUpdateType()' . "\n" . prettyJson($telegram->getUpdateType())]);
    // getUserProfilePhotos()
//    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'getUserProfilePhotos()'."\n". prettyJson($telegram->getUserProfilePhotos(['user_id' => $user_id]))]);
    // getUpdates()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getUpdates()' . "\n" . prettyJson($telegram->getUpdates())]);


    // callbacks
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Data()' . "\n" . prettyJson($telegram->Callback_Data())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_ID()' . "\n" . prettyJson($telegram->Callback_ID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_ChatID()' . "\n" . prettyJson($telegram->Callback_ChatID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_FromID()' . "\n" . prettyJson($telegram->Callback_FromID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Message()' . "\n" . prettyJson($telegram->Callback_Message())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Query()' . "\n" . prettyJson($telegram->Callback_Query())]);
}
*/

function isChatMember($chat_id, $user_id): bool
{
    global $telegram;
    $is_member_response = $telegram->getChatMember(['chat_id' => $chat_id, 'user_id' => $user_id]);

    //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => $is_member_response["ok"]]);
    // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => prettyJson($is_member_response)]);


    if ($is_member_response["ok"] == false || $is_member_response["result"]["status"] == "left") {

        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "false"]);
        return false;
    } else {

        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "ok"]);
        return true;
    }
}




/*
 * idpay methods
 */

/**
 * @param array $params
 * @return bool
 */
function idpay_payment_create($params)
{
    global $style;

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_PAYMENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result);

    if (empty($result) || empty($result->link)) {

//        print 'Exception message:';
//        print '<pre>';
//        print_r($result);
//        print '</pre>';

//        $font_path = __DIR__ . "/../IRANSansWeb.ttf";
        echo $style;
        echo "<div class=\"container\"><h1>$result->error_message</h1></div>";


        return FALSE;
    }

    /*
        // save to db the response
        //  {
        //      "id": "d2e353189823079e1e4181772cff5292",
        //      "link": "https://idpay.ir/p/ws-sandbox/d2e353189823079e1e4181772cff5292"
        //  }
    */
    //.Redirect to payment form
    header('Location:' . $result->link);
}


/*
// needs         'id' => $response['id'],
//        'order_id' => $response['order_id'],
*/
/**
 * @param array $response
 * @return bool
 */
function idpay_payment_get_inquiry($response)
{

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $params = array(
        'id' => $response['id'],
        'order_id' => $response['order_id'],
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_INQUIRY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result);

    if (empty($result) || empty($result->status)) {

        print 'Exception message:';
        print '<pre>';
        print_r($result);
        print '</pre>';

        echo $result->error_message;

        return FALSE;
    }

    if ($result->status == 10) {
        return TRUE;
    }

    print idpay_payment_get_message($result->status);

    return FALSE;
}



/*
// needs      'id' => $response['id'],
//        'order_id' => $response['order_id'],
*/
/**
 * @param array $response
 * @return bool
 */
function idpay_payment_verify($response)
{

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $params = array(
        'id' => $response['id'],
        'order_id' => $response['order_id'],
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_VERIFY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result);

    if (empty($result) || empty($result->status)) {

        print 'Exception message:';
        print '<pre>';
        print_r($result);
        print '</pre>';

        //echo $result->error_message;

        return FALSE;
    }

    print idpay_payment_get_message($result->status);

    print '<pre>';
    print_r($result);
    print '</pre>';
}


/**
 * @param int $status
 * @return string
 */
function idpay_payment_get_message($status)
{

    switch ($status) {
        case 1:
            return  'پرداخت انجام نشده است ';

        case 2:
            return 'پرداخت ناموفق بوده است ';

        case 3:
            return 'خطا رخ داده است ';

        case 4:
            return "بلوکه شده ";

        case 5:
            return "برگشت به پرداخت کننده ";

        case 6:
            return 'برگشت خورده سیستمی ';

        case 7:
            return 'انصراف از پرداخت ';

        case 8:
            return "به درگاه پرداخت منتقل شد ";

        case 10:
            return 'در انتظار تایید پرداخت ';

        case 100:
            return 'پرداخت تایید شده است ';

        case 101:
            return 'پرداخت قبلاً تایید شده است ';

        case 200:
            return "به دریافت کننده واریز شد ";

        default:
            return 'ارور ';
    }
}

/**
 * @return void
 */
function validateUserForPayment(): void
{
    $redirect_after = 3;
    // todo : refactor this
    $redirect_to = "https://www.freelancerly.ir/freelancerly_bot";

    $style = "<style>
          
                @font-face {
                    font-family: 'IRANSans';
                    src: url('../assets/fonts/IRANSansWeb.ttf') format('truetype');
                }

                .myh1 {
                    font-family: 'IRANSans', Arial, sans-serif;
                    direction: rtl;
                    margin: 0 auto 0 auto;
                }
                
                .my-container {
                    display: flex;
                    justify-content: center; /* Center horizontally */
                    align-items: center; /* Center vertically */
                    height: 100vh; /* Set container height for vertical centering */
                }
             
          </style>";
    echo $style;



    if (empty($_GET['user_id']) || empty($_GET['adv_id'])) {
        echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 4)</h1></div>";
        sleep($redirect_after);
        header('Location: https://www.freelancerly.ir/freelancerly_bot');
        exit;
    }
    else {
        try {
            $user_from_id = filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT);
            $advId = filter_input(INPUT_GET, 'adv_id', FILTER_SANITIZE_NUMBER_INT);

            if (empty($user_from_id) || empty($advId)) {
                echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 5)</h1></div>";
                sleep($redirect_after);
                header('Location: https://www.freelancerly.ir/freelancerly_bot');
                exit;
            }

            // Use the filtered and sanitized values in your database query or further processing...
            if (!hasUserThisUnpaidAdv($user_from_id, $advId)) {
                echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 1)</h1></div>";
                sleep($redirect_after);
                header('Location: https://www.freelancerly.ir/freelancerly_bot');
                exit;
            }
        }
        catch (Exception $e) {
            //echo "Error: " . $e->getMessage();
            echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 45)</h1></div>";
            sleep($redirect_after);
            header('Location: https://www.freelancerly.ir/freelancerly_bot');
            exit;
        }
    }
}



function isForwardedMessage($last_msg_data): bool
{
    // isset($last_msg_data['message']['forward_from_chat'])
    // isset($last_msg_data['message']['forward_from'])
    // isset($last_msg_data['message']['forward_sender_name'])

    if (isset($last_msg_data['message']['forward_date'])) {
        return true;
    } else {
        return false;
    }
}

function hasText($last_msg_data): bool
{
    if (isset($last_msg_data['message']['text'])) {
        return true;
    }
    else {
        return false;
    }
}

function hasInlineKeyboard($last_msg_data): bool
{
    if (isset($last_msg_data['message']['reply_markup']['inline_keyboard'])) {return true;}
    else {
        return false;
    }
}

function replaceByFilters($file_path, $input_string) {
    // Read the JSON file and decode it into a PHP array
    $json_data = file_get_contents($file_path);
    $data = json_decode($json_data, true);

    // Get the filters object from the PHP array
    $filters = $data['filters'];

    if (count($filters) > 0) {
        // Replace placeholders in the input string with corresponding values from the filters object
        foreach ($filters as $key => $value) {
            if ($value == "/حذف") { $value = ''; }
            $input_string = str_replace($key, $value, $input_string);
        }
    }

    // Return the modified string
    return $input_string;
}

function isOk($reply4) {

    if ($reply4['ok']) {
        $is_ok4 = '✅ ارسال شد.';
    }
    elseif (isset($reply4["parameters"]["retry_after"])) {
        $is_ok4 = "🔴 ارسال نشد.\nلطفا بعدا از {$reply4["parameters"]["retry_after"]} ثانیه مجدد امتحان کنید.";
    }
    else {
        $is_ok4 = $reply4['description'];
    }
    return $is_ok4;

}

function displayFilterButtons($chat_id): array
{
    global $telegram;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "افزودن فیلتر"], ['text' => "شروع فوروارد"]
        ],
        [
            ['text' => "مشاهده/حذف تکی فیلترها"], ['text' => "حذف همه فیلترها"]
        ],
        [
            ['text' => "فعال/غیرفعال کردن موقت فیلترها"]
        ],
        [
            ['text' => "بازگشت به منو ↪️"], ['text' => "/admin"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی تنظیم فیلترها'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی تنظیم فیلترها',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function deleteFromFilters($file_path, $index)
{

    $index_to_remove = $index - 1;

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Get the filters object and convert it to a numeric array
    $filters = $data['filters'];
    $filter_keys = array_keys($filters);
    $numeric_filters = array_values($filters);

    // Check if the specified index is within bounds
    if ($index_to_remove >= 0 && $index_to_remove < count($numeric_filters)) {


        // Create copies of the arrays before modifying them
        $new_filter_keys = $filter_keys;
        $new_numeric_filters = $numeric_filters;

        // Remove the element at the specified index from the copied arrays
        array_splice($new_filter_keys, $index_to_remove, 1);
        array_splice($new_numeric_filters, $index_to_remove, 1);

        // Combine the two copied arrays into one new associative array using array_combine()
        $new_filters = array_combine($new_filter_keys, $new_numeric_filters);


        // Remove the key-value pair at the specified index
//        array_splice($numeric_filters, $index, 1);
//        unset($filters[$filter_keys[$index]]);
        //$my_array = ["apple", "banana", "cherry", "date"];


// Create a new array without the element at the specified index
        //$new_array = array_merge(array_slice($my_array, 0, $index));

        // Convert the numeric array back to an associative array
        $data['filters'] = $new_filters;
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $data['filters']]);


        // Encode the updated data back into JSON format
        $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Save the updated JSON data back to the original file
        file_put_contents($file_path, $json_data);
        return true;
    } else {
        return false;
    }
}

function deleteAllFilters($file_path): void
{

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Convert the numeric array back to an associative array
    $data['filters'] = [];

    // Encode the updated data back into JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Save the updated JSON data back to the original file
    file_put_contents($file_path, $json_data);
}

function readJsonFilters($file_path): array
{

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Extract all key-value pairs from the "filters" object
    $filters = $data['filters'];
    $key_value_pairs = [];
    $counter = 1;
    foreach ($filters as $key => $value) {
        $key_value_pairs[] = "🟠$counter. $key => $value";
        $counter++;
    }

    return [
        "count" => count($filters),
        // Return the key-value pairs as a single string with each pair on a new line
        "key_value_pairs" => implode("\n", $key_value_pairs)
    ];

}


function addOrUpdateJsonFilter($file_path, $key, $value)
{
    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    $data['filters'][$key] = $value;

    // Encode the updated data back into JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Save the updated JSON data back to the original file
    file_put_contents($file_path, $json_data);
}

function searchAndUpdateFilterValue($file_path, $search_value, $new_value)
{
    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Loop through each key-value pair in the "filters" object
    foreach ($data['filters'] as $key => $value) {
        // Check if the value matches the search value

        if ($value === $search_value) {
            // Update the value with the new value
            $data['filters'][$key] = $new_value;

            // Encode the updated data back into JSON format
            $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Save the updated JSON data back to the original file
            file_put_contents($file_path, $json_data);

            // Stop looping since we've found and updated the value
            break;
        }
    }
}


function separateForwardedAdv($inputString) {

    // Split the string based on the newline character (\n)
    $parts = explode("\n", $inputString);


    //global $telegram,$chat_id,$message_id;

//    foreach ($parts as $key => $value) {
//        //echo $key . ": " . $value . "<br>";
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "key: $key ++ value: $value", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
//    }


    if (count($parts) === 2 || count($parts) === 3) {
        // Trim the parts
        //return array_map('trim', $parts);

        $text = $parts[0];
        $text = mb_convert_encoding(trim($text), 'UTF-8');

        if (!empty($parts[2])) {$id = $parts[2];}
        elseif (!empty($parts[1])) {$id = $parts[1];}
        else {return false;}
        $id = mb_convert_encoding(trim($id), 'UTF-8');

        return [$text, $id];
    }

    return false;
}



function separateForwardedAdv2($inputString) {

    // Split the string into parts based on line breaks
    $parts = explode("\n", $inputString);

    if (count($parts) === 3) {
        // Extract and trim the text and ID based on their positions
        $text = trim($parts[0]);
        $id = trim($parts[1]);

        return [$text, $id];
    }
    // Return null if the string doesn't contain three parts
    return false;
}

function separateForwardedAdv3($inputString) {

    //$inputString = mb_convert_encoding($inputString, 'UTF-8');

    // Find the position of "🆔 " and "- - - - - - - - - - - - - -"
    $idStart = strpos($inputString, "🆔 ");
    $idEnd = strpos($inputString, "- - - - - - - - - - - - - -");

    if ($idStart !== false && $idEnd !== false) {
        // Extract and trim the ID based on the positions
        $id = substr($inputString, $idStart + 5, $idEnd - $idStart - 5);
        $id = trim($id);

        // Extract and trim the text before the ID
        $text = trim(substr($inputString, 0, $idStart));

        return [mb_convert_encoding($text, 'UTF-8'), mb_convert_encoding($id, 'UTF-8')];
    }

    // Return null if the specified markers are not found
    return false;
}





//todo: use encryption in payment GET
/*
// Encryption function
function encryptData($value, $key) {
    $encryptedValue = openssl_encrypt($value, "AES-256-CBC", $key, 0, random_bytes(16));
    return urlencode($encryptedValue);
}

// Decryption function
function decryptData($encryptedValue, $key) {
    $decryptedValue = openssl_decrypt(urldecode($encryptedValue), "AES-256-CBC", $key, 0, random_bytes(16));
    return $decryptedValue;
}

// Define the value and secret key
$value = "myValue";
$key = "mySecretKey";

// Encrypt the data and append it to the URL
$encryptedValue = encryptData($value, $key);
$url = "http://example.com?data=" . $encryptedValue;

// Retrieve the encrypted value from the URL
$encryptedValueFromUrl = $_GET['data'];

// Decrypt the value and echo the decrypted data
$decryptedValue = decryptData($encryptedValueFromUrl, $key);
echo $decryptedValue; // Output: "myValue"


http://example.com?data=myEncryptedData
URL with encrypted data: http://example.com?data=ue%2FsbupYiCEoLu5Z8J9yaA%3D%3D

In summary, if a user edits the URL with encrypted data, the decryption process may fail or
produce incorrect results. This is why it's important to ensure the integrity and
security of the encrypted data in transit. It's a good practice to implement additional
measures such as digital signatures, message authentication codes (MACs), or encryption
with authenticated modes to detect tampering and ensure the authenticity of the data.




// second way.**************
Certainly! To enhance the security and integrity of the encrypted data in transit,
you can use a combination of encryption and authentication techniques such as digital
signatures or message authentication codes (MACs).
Here's an example using HMAC-SHA256 for message authentication:
// Encryption function
function encryptData($value, $key) {
    $encryptedValue = openssl_encrypt($value, "AES-256-CBC", $key, 0, random_bytes(16));
    return urlencode($encryptedValue);
}

// Decryption function
function decryptData($encryptedValue, $key) {
    $decryptedValue = openssl_decrypt(urldecode($encryptedValue), "AES-256-CBC", $key, 0, random_bytes(16));
    return $decryptedValue;
}

// Generate an HMAC-SHA256 signature for the encrypted data
function generateSignature($data, $key) {
    $signature = hash_hmac('sha256', $data, $key);
    return urlencode($signature);
}

// Verify the HMAC-SHA256 signature
function verifySignature($data, $signature, $key) {
    $expectedSignature = generateSignature($data, $key);
    return hash_equals($expectedSignature, $signature);
}

// Define the value and secret key
$value = "myValue";
$key = "mySecretKey";

// Encrypt the data and generate the signature
$encryptedValue = encryptData($value, $key);
$signature = generateSignature($encryptedValue, $key);

// Append the encrypted value and the signature to the URL
$url = "http://example.com?data=" . $encryptedValue . "&signature=" . $signature;

// Retrieve the encrypted value and the signature from the URL
$encryptedValueFromUrl = $_GET['data'];
$signatureFromUrl = $_GET['signature'];

// Verify the signature
if (verifySignature($encryptedValueFromUrl, $signatureFromUrl, $key)) {
    // Signature is valid, decrypt the value and echo the decrypted data
    $decryptedValue = decryptData($encryptedValueFromUrl, $key);
    echo $decryptedValue; // Output: "myValue"
} else {
    // Signature is invalid, handle the error
    echo "Invalid signature!";
}

*/



//function insert_advertisement($connection, $numeric_id, $text, $contact_info, $status, $timestamp): bool
//{
//    // Prepare SQL statement with placeholders for values
//    $stmt = mysqli_prepare($connection, "INSERT INTO advertisements (adv_user_numeric_id, adv_text, adv_contact_info, adv_status, adv_creation_date) VALUES (?, ?, ?, ?, ?)");
//
//    // Bind the values to the placeholders
//    mysqli_stmt_bind_param($stmt, "issss", $numeric_id, $text, $contact_info, $status, $timestamp);
//
//    // Execute the statement and return success or failure
//    if(mysqli_stmt_execute($stmt)) {
//        // Close statement and connection
//        mysqli_stmt_close($stmt);
////        mysqli_close($connection);
//        return true;
//    } else {
//        // Close statement and connection
//        mysqli_stmt_close($stmt);
////        mysqli_close($connection);
//        return false;
//    }
//}

// test this version
//function addOrUpdateJson(string $filename, array $data): void
//{
//    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';
//
//    // Check if the file exists, create a new file if it does not.
//    if (!file_exists($file_path)) {
//        file_put_contents($file_path, "{}");
//    }
//
//    $jsonData = file_get_contents($file_path);
//    $jsonArray = json_decode($jsonData, true);
//
//    foreach ($data as $key => $value) {
//        if ($jsonArray[$key] !== $value) { // check if value has changed
//            $jsonArray[$key] = $value;
//        }
//    }
//
//    $jsonData = json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//    file_put_contents($file_path, $jsonData);
//}
//
//// run
//$data1 = [
//    'user_username_id' => $username,
//    'adv_user_numeric_id' => $user_from_id,
//    'adv_text' => $message_text
//];
//
//$data2 = [
//    'user_username_id' => $newUsername,
//    'adv_user_numeric_id' => $newUserId,
//    'adv_text' => $newMessage
//];
//
//addOrUpdateJson('filename', [$data1, $data2]);

//function addOrUpdateJson($filename, $key, $value): bool
//{
//
//    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';
//
//    // Read the existing data from the file
//    $jsonData = file_get_contents($file_path);
//
//    // Decode the JSON data into an associative array
//    $data = json_decode($jsonData, true);
//
//    // Update the value if the key already exists or add it as a new key-value pair
//    $data[$key] = $value;
//
//    // Encode the updated data back into JSON format
//    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//
//    // Write the updated data back to the file
//    if (file_put_contents($file_path, $jsonData)) {
//        return true;
//    } else {
//        return false;
//    }
//}

/*
function showMyAdvertisementsList(int $chat_id, int $user_numeric_id): void
{

    global $connection, $telegram;
    $rowCount = 0;
    $whole_list = "";

      $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? ORDER BY adv_id ASC");
    // 10 last ones from lowest adv_id to the last.
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC");
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC LIMIT {$count}");
    mysqli_stmt_bind_param($stmt, "i",  $user_numeric_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result !== false) {

        $long_adv_text = [];

        // Loop through the rows and echo the data
        while ($row = mysqli_fetch_assoc($result)) {

            $is_paid_text = '';

            if ($row['adv_is_paid']) {
                $is_paid_text .= " ✅";
            }

            $text = "➖➖➖➖➖ /id" . $row['adv_id'] . " ➖➖➖➖➖
● وضعیت آگهی: " . $is_paid_text . "
● متن آگهی:
" . $row['adv_text'] . "
";

            if (mb_strlen($text, 'UTF-8') > 450) { // max limit = 4096
                $long_adv_text[] = $text;
                continue;
            }

            $whole_list = $whole_list . $text;

        }

        $rowCount = mysqli_num_rows($result);
    }

    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "" . $rowCount . " آگهی یافت شد."]);
    foreach ($long_adv_text as $text) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
    }
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $whole_list]);
    mysqli_stmt_close($stmt);
}
*/

//function subtractCoins(int $user_from_id, int $coins_to_subtract, mysqli $connection): bool
//{
//    $query = "UPDATE users SET user_coins_count = user_coins_count - ? WHERE user_numeric_id = ? AND user_coins_count >= ?";
//    $stmt = $connection->prepare($query);
//    $stmt->bind_param("iii", $coins_to_subtract, $user_from_id, $coins_to_subtract);
//    $connection->begin_transaction();
//    $stmt->execute();
//    $affected_rows = $stmt->affected_rows;
//    if ($affected_rows != 1) {
//        $connection->rollback();
//        return false;
//    }
//    $connection->commit();
//    return true;
//}

//function decodeUnicodeString($str) {
//    $decodedStr = json_decode('["'.$str.'"]', true, 512, JSON_UNESCAPED_UNICODE)[0];
//
//    // Replace escaped newline characters with actual newlines
//    $decodedStr = str_replace('\n', "\n", $decodedStr);
//
//    // Replace multiple whitespace characters with a single space
//    $decodedStr = preg_replace('/\s+/', ' ', $decodedStr);
//
//    return $decodedStr;
//}

// Usage Example
//$phone_number = "09120000000";
//if (is_valid_phone_number($phone_number)) {
//    echo "$phone_number is a valid phone number";
//} else {
//    echo "$phone_number is not a valid phone number";
//}
//
// Usage Example
//$id = "@jobfreelancer";
//if (is_valid_id($id)) {
//    echo "$id is a valid Id";
//} else {
//    echo "$id is not a valid Id";
//}

/*
File created successfully:
 /home/playmak1/public_html/telegram_bots/Freelancerly_bot/temp_incomplete_adv/133084833.json
*/
<?php
<?php

/**********
 * HELPER
 **********/

/*
function prettyJson($jsonObject)
{
    return json_encode($jsonObject, JSON_PRETTY_PRINT);
}
*/

/**
 * Checks if the given message data contains a bot command.
 *
 * @param array $last_msg_data The message data to check.
 * @return bool Returns true if the message data contains a bot command, false otherwise.
 */
function isBotCommand($last_msg_data): bool
{
    return isset($last_msg_data['message']['entities'][0]['type']) && $last_msg_data['message']['entities'][0]['type'] == 'bot_command';
}

function is_valid_invite_link(string $text): bool
{
    // "text": "/start 133084833"
    return preg_match('/^\/start ([1-9]\d{2,10})$/', trim($text)) === 1;
}


function extractInvitationLinkParts(string $text)
{
    // Extract the command and parameters
    $command_parts = explode(' ', $text);
    $command = $command_parts[0]; // /start
    $user_id_param = $command_parts[1]; // id
    $user_id_param = (int)filter_var($user_id_param, FILTER_SANITIZE_NUMBER_INT);

    // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'Parameter : ' . $user_id_param, 'reply_to_message_id' => $message_id]);
    return $user_id_param;
}

//$telegram->messageFromGroup();
// we can also use getChat()/getData() instead.
function isPrivateChat($last_msg_data): bool
{
    if (isset($last_msg_data['message']['chat']['type']) && $last_msg_data['message']['chat']['type'] == 'private') {
        return true;
    } else {
        return false;
    }
}

function isValidAdvId($str, string $param)
{
    // Explanation:
    // - `^`: It matches the starting position of the string.
    // - `$param`: Match the value of the $param variable literally.
    // - `\d{1,5}`: Match any digit between 1 to 5 times.
    // - `$`: It matches the ending position of the string.
    //
    // This code will return `1` if the string is in the specified format, otherwise `0`

    // supports 999999 adv (6 digit)
    return preg_match("/^{$param}\d{1,6}$/", $str);
}

function extractId($str, $param)
{
    $parts = explode($param, $str);
    if (count($parts) > 1) {
        return intval($parts[1]);
    } else {
        return false;
    }
}

function isValidId($string)
{
    // Check if string starts with "@" and has at least five alpha-numeric characters or underscores after it
    return preg_match('/^@[a-zA-Z0-9_]{5,}$/', $string);
}

function isValidPhone($string)
{
    // Check if string is 11 digits long and only contains digits
    //    return preg_match('/^\d{11}$/', $string);

    // Check if string is 11 digits long and starts with "09"
    return preg_match('/^09\d{9}$/', $string);
}

function calcStatus($adv_is_paid, $adv_is_approved, $adv_is_assigned)
{

    $status_text = '';

    if ($adv_is_paid == 0 and $adv_is_approved == 0 and $adv_is_assigned == 0) {
        $status_text = 'پرداخت نشده';
        $status_text .= " 💳";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == -1 and $adv_is_assigned == 0) {
        $status_text = 'رد شده';
        $status_text .= " ❌";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == -2 and $adv_is_assigned == 0) {
        // todo : fix
        // همان رد شده بدون بازگرداندن پول
        $status_text = 'حذف شده';
        $status_text .= " 🗑";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 0 and $adv_is_assigned == 0) {
        $status_text = 'در انتظار تایید مدیر';
        $status_text .= " ⏳";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 0) {
        $status_text = 'منتشر شده';
        $status_text .= " ✅";
    }
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 1) {
        $status_text = 'واگذار شده';
        $status_text .= " 🔴";
    }
    // todo: implement expiration
    elseif ($adv_is_paid == 1 and $adv_is_approved == 1 and $adv_is_assigned == 2) {
        $status_text = 'منقضی شده';
        $status_text .= " ⚫️";
    }

    return $status_text;
}

function returnButton(): array
{
    return [
        [
            ['text' => "بازگشت به منو ↪️"],
        ],
    ];
}

function doubleReturnButton(): array
{
    return [
        [
            ['text' => "بازگشت به منو ↪️"],['text' => "/admin"],
        ],
    ];
}

// $string = "/approveReq_76_133084833";
function split_string($string)
{
    return explode('_', $string);
}

/*
function showBackButton(): void
{
}
*/
/*
function prettyJsonPrint($jsonObject): void
{
    echo "<pre>" . json_encode($jsonObject, JSON_PRETTY_PRINT) . "<pre/>";
}
*/

function is_channel($last_msg_data): bool
{
    if (isset($last_msg_data['channel_post']) && $last_msg_data['channel_post']['chat']['type'] == 'channel') {
        return true;
    }
    else {
        return false;
    }
}


/********************
 * DATABASE
 *******************/
function hasUserThisUnpaidAdv($user_id, $advId): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM advertisements WHERE (adv_user_numeric_id = ? and adv_id = ? and adv_is_paid = 0)");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $advId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $count === 1;
}

function userExists($user_from_id): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM users WHERE user_numeric_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $count === 1;
}

/**
 * @param $connection
 * @param $user_from_id
 * @param $user_username_id
 * @return bool
 */
function insertUser($user_from_id, $user_username_id): bool
{
    global $connection;
    if (!empty($user_username_id)){
        $stmt = mysqli_prepare($connection, "INSERT INTO users (user_numeric_id, user_username_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "is", $user_from_id, $user_username_id);
    }
    else {
        $stmt = mysqli_prepare($connection, "INSERT INTO users (user_numeric_id) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    }

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function insertInvite($inviter_id, $invited_id): bool
{
    global $connection;
    $stmt = mysqli_prepare($connection, "INSERT INTO invitations (inviter_user_numerical_id, invited_user_numerical_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $inviter_id, $invited_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function getUserStep($user_from_id): int
{
    // object-oriented style
    global $connection;
    $stmt = $connection->prepare("SELECT user_step FROM users WHERE user_numeric_id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();
    $result = $stmt->get_result(); // result is an array
    $step = $result->fetch_array()[0];
    $stmt->close();
    return $step;
}

function setUserStep($user_from_id, $step): bool
{
    global $connection;
    $stmt = $connection->prepare("UPDATE users SET user_step = ? WHERE user_numeric_id = ?");
    $stmt->bind_param("ii", $step, $user_from_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    return $affected_rows == 1;
}

function getCoins($user_from_id)
{
    global $connection;
    $stmt = mysqli_prepare($connection, "SELECT user_coins_count FROM users WHERE user_numeric_id = ?");
    if ($stmt === false) {
        // Handle the error case here.
        return 0; // Or some other default value.
    }
    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt); // result is an array
    $coins = mysqli_fetch_array($result)[0];
    mysqli_stmt_close($stmt);
    return $coins;
}

function setCoins($user_from_id, $coins): bool
{
    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = ? WHERE user_numeric_id = ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "ii", $coins, $user_from_id);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}

function increaseCoins($user_from_id, $coins_to_add): bool
{
    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = user_coins_count + ? WHERE user_numeric_id = ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "ii", $coins_to_add, $user_from_id);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}

function subtractCoins($user_from_id, $coins_to_subtract): bool
{
    if ($coins_to_subtract == 0) {return true;}

    global $connection;

    // Prepare the SQL statement
    $stmt = mysqli_prepare($connection, "UPDATE users SET user_coins_count = user_coins_count - ? WHERE user_numeric_id = ? AND user_coins_count >= ?");

    if (!$stmt) {
        // Error handling: Return false if the statement preparation fails
        return false;
    }

    // Bind the parameters
    mysqli_stmt_bind_param($stmt, "iii", $coins_to_subtract, $user_from_id, $coins_to_subtract);

    // Execute the statement
    $success = mysqli_stmt_execute($stmt);

    if (!$success) {
        // Error handling: Return false if the execution fails
        return false;
    }

    // Get the number of affected rows
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    // Close the statement
    mysqli_stmt_close($stmt);

    // Return true if one row was affected, false otherwise
    return $affected_rows == 1;
}


function getUserDataByID($user_from_id): int
{
    // object-oriented style
    global $connection;
    $stmt = $connection->prepare("SELECT user_step FROM users WHERE user_numeric_id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();
    $result = $stmt->get_result(); // result is an array
    $step = $result->fetch_array()[0];
    $stmt->close();
    return $step;
}

/*
function getUserById($userId): array
{

    global $connection;
    $stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_from_id);
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    // Fetch the data and store them in variables
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $username = $user['username'];
    $email = $user['email'];

    // Close the statement and the database connection
    $stmt->close();
    $connection->close();

    // Return the user data stored in variables
    return [
        'userId' => $userId,
        'username' => $username,
        'email' => $email
    ];
}
*/

function getUserById($userId)
{
    global $connection;

    try {
        $stmt = $connection->prepare("SELECT * FROM users WHERE user_numeric_id = ?");
        if (!$stmt) {throw new Exception("Failed to prepare statement");}

        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {throw new Exception("Failed to execute statement");}

        $result = $stmt->get_result();
        if (!$result) {throw new Exception("Failed to get result set");}

        $user = $result->fetch_assoc();
        if (!$user) {throw new Exception("User not found");}

        $user_username_id = $user['user_username_id'];
        $user_numeric_id = $user['user_numeric_id'];
        //$email = $user['email'];

        $stmt->close();

        return [
            'user_username_id' => $user_username_id,
            'user_numeric_id' => $user_numeric_id
        ];
    }
    catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}
//$userData = getUserById($user_from_id);
//if ($userData) {
//    $retrievedUserId = $userData['userId'];
//    $retrievedUsername = $userData['username'];
//    $retrievedEmail = $userData['email'];
//}


function insertAdvertisement($chat_id, array $data)
{
    global $connection, $telegram;

    $user_numeric_id = $data['adv_user_numeric_id'];
    $text = $data['adv_text'];
    $contact_info = $data['adv_contact_info'];
    // $required_skills = $data['adv_required_skills'];
    $creation_date = $data['adv_creation_date'];

    $stmt = mysqli_prepare($connection, "INSERT INTO advertisements (adv_user_numeric_id, adv_text, adv_contact_info, adv_creation_date) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }


    mysqli_stmt_bind_param($stmt, "isss", $user_numeric_id, $text, $contact_info, $creation_date);
    $result = mysqli_stmt_execute($stmt);

    if (!$result) {
        // Handle error
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    // Get the ID of the inserted row
    $inserted_id = mysqli_insert_id($connection);

    // Close statement and connection
    mysqli_stmt_close($stmt);

    // Return the inserted row id
    return $inserted_id;
}



function countUnpaidAdvertisements($user_from_id)
{
    global $connection, $telegram, $chat_id;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) FROM advertisements WHERE adv_user_numeric_id = ? AND adv_is_paid = 0");
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $user_from_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $count;
}

function setIsPaid($adv_id, $user_from_id, $value): bool
{
    global $connection;

    $stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_paid` = {$value} WHERE adv_id = ? AND adv_user_numeric_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $adv_id, $user_from_id);
    mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function fixStringOrder(string $string) {
    $pattern = '/(.*)@(\w+)/u';
    $replacement = '$2@$1';
    $fixedString = preg_replace($pattern, $replacement, $string);

    return $fixedString;
}

function setIsApproved($adv_id, $user_from_id, $value): bool
{
    global $connection, $telegram, $chat_id;

    //$stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_approved` = {$value} WHERE adv_id = ? AND adv_user_numeric_id = ?");
    $stmt = mysqli_prepare($connection, "UPDATE advertisements SET adv_is_approved = ? , adv_publication_date = ? WHERE adv_id = ? AND adv_user_numeric_id = ?");

    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    $timestamp = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, "isii", $value, $timestamp, $adv_id, $user_from_id);

    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function setIsAssigned($adv_id, $user_from_id, $value): bool
{
    global $connection, $telegram, $chat_id;

    $stmt = mysqli_prepare($connection, "UPDATE `advertisements` SET `adv_is_assigned` = ? , adv_assignment_date = ? WHERE adv_id = ? AND adv_user_numeric_id = ?");
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    $timestamp = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, "isii", $value, $timestamp, $adv_id, $user_from_id);


    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected_rows == 1;
}

function setAdvertisementMessageId($adv_id, $adv_message_id): bool
{
    global $connection, $telegram, $chat_id;

    $query = "UPDATE `advertisements` SET `adv_message_id` = ? WHERE `advertisements`.`adv_id` = ?";

    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ii", $adv_message_id, $adv_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected_rows == 1) {
        return true;
    } else {
        return false;
    }
}

function getAdvertisementMessage_id($adv_id)
{
    global $connection;
    $query = "SELECT `adv_message_id` FROM `advertisements` WHERE `adv_id` = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $adv_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $adv_message_id);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($adv_message_id)) {
        return $adv_message_id;
    } else {
        return false;
    }
}


/********************
 * JSON
 *******************/
function is_admin($user_id, $fileName): bool
{
    // Read JSON file
    $json_data = file_get_contents($fileName);

    // Decode JSON data
    $data = json_decode($json_data, true);

    // Check if value and key exist
    $found = false;
    foreach ($data['admins'] as $admin) {
        if (in_array($user_id, $admin)) {
            $found = true;
            break;
        }
    }

    if ($found) {
        return true;
    } else {
        return false;
    }
}

function add_admin($key, $value): bool
{
    // Read JSON file
    $json_data = file_get_contents('admins.json');

    // Decode JSON data
    $data = json_decode($json_data, true);

    // Add new admin to array
    $new_admin = [$key => $value];
    $data['admins'][] = $new_admin;

    // Encode data back to JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT);

    if (file_put_contents('admins.json', $json_data) !== false) {
        // Return true if JSON data was successfully saved
        return true;
    } else {
        // Return false if an error occurred while saving the JSON data
        return false;
    }
}

function createJsonFile(string $file_path): bool
{

    $data = [];
//    $data['message'] = 'Hello, world!';
//    $data['test'] = 'Hello, world!-2';


    // convert array to json style
    $json_data = json_encode($data, JSON_PRETTY_PRINT);

    if (file_put_contents($file_path, $json_data)) {
        return true;
    } else {
        return false;
    }
}

function createJsonFile2(string $file_path , $data = []): bool
{

    // $data = [];
    // $data['message'] = 'Hello, world!';
    // $data['test'] = 'Hello, world!-2';


    // convert array to json style
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($file_path, $json_data)) {
        return true;
    }
    else {
        return false;
    }
}


function addOrUpdateJson($file_path, string $key, $value): bool
{
    //$file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

    // Check if the file exists, create a new file if it does not.
    if (!file_exists($file_path)) {
        $success = file_put_contents($file_path, "{}");
        if ($success === false) {
            // Error handling: Return false if file creation fails
            return false;
        }
    }

    $jsonData = file_get_contents($file_path);
    if ($jsonData === false) {
        // Error handling: Return false if file reading fails
        return false;
    }

    $data = json_decode($jsonData, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        // Error handling: Return false if JSON decoding fails
        return false;
    }

    if ($data[$key] !== $value) { // check if value has changed
        $data[$key] = $value;
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            // Error handling: Return false if JSON encoding fails
            return false;
        }

        $success = file_put_contents($file_path, $jsonData);
        if ($success === false) {
            // Error handling: Return false if file writing fails
            return false;
        }
    }

    return true;
}

function deleteJsonFile(string $filename): bool
{
    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

    // Check if the file exists
    if (file_exists($file_path)) {
        // Delete the file
        unlink($filename);
        return true;
    } else {
        return false;
    }
}

function getValueByKeyFromJson($file_path, string $key)
{
    //$file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';

//    if (!file_exists($file_path)) {
//        throw new Exception('File not found.');
//    }

    if (!file_exists($file_path)) {
        return false;
    }

    $jsonData = file_get_contents($file_path);
    $data = json_decode($jsonData, true);

//    $advData = [
//        'user_username_id' => $data['user_username_id'],
//        'adv_user_numeric_id' => $data['adv_user_numeric_id'],
//        'adv_text' => $data['adv_text'],
//        'adv_contact_info' => $data['adv_contact_info'],
//        'adv_timestamp' => $data['adv_timestamp']
//    ];

//    return $advData;

    return $data[$key];
}


/*********
 * BOX AND BUTTONS
 ********/

function displayMainMenuButtons($chat_id): array
{
    global $telegram,$is_admin;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "ثبت آگهی جدید 📝"], ['text' => "آگهی‌های ثبت شده 🗄"],
        ],
        [
            ['text' => "پشتیبانی 💬"], ['text' => "سکه رایگان 🌕"],
        ],
        [
            ['text' => "واسطه کردن ادمین 🤝"], ['text' => "واگذاری پروژه به تیم ما"],
        ],
        [
            ['text' => "پیشگیری از کلاهبرداری"]
        ],
    ];

//    [
//        ['text' => "انجام تبلیغات"], ['text' => "پیشگیری از کلاهبرداری"]
//    ],
//    [
//        ['text' => "انتخاب زبان ربات"]
//    ],

    $append_array = [['text' => "/admin"]];
    if ($is_admin){
        $rep_KeyB_BTNs_Main[] = $append_array;
    }


    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی اصلی'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی اصلی',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function displayAdminMenu($chat_id): array
{
    global $telegram;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "اگهی های در انتظار تایید (؟)"]
        ],
        [
            ['text' => "ارسال پیام همگانی"], ['text' => "حالت فوروارد اختصاصی"],
        ],
        [
            ['text' => "تغییر هزینه ثبت آگهی"], ['text' => "تغییر سکه های کاربر"],
        ],
        [
            ['text' => "ارسال پیام همگانی"], ['text' => "ارسال پیام به کاربر"],
        ],
        [
            ['text' => "غیرفعال/فعالسازی ربات"], ['text' => " بلاک/آنبلاک کاربر"],
        ],
        [
            ['text' => "تعداد کاربران جدید"], ['text' => "آمار کاربران"],
        ],
        [
            ['text' => "ایجاد کد تخفیف"], ['text' => "جایزه به کاربر"],
        ],
        [
            ['text' => "آمار آگهی ها"], ['text' => "تغییر وضعیت آگهی"],
        ],
        [
            ['text' => "تنظیمات جوین اجباری"],['text' => "تخفیف همگانی"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی مدیر'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی مدیر',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function displayEditUserCoinsMenu($chat_id): array
{
    global $telegram, $message_id;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "کاهش سکه"], ['text' => "افزایش سکه"]
        ],
        [
            ['text' => "تعیین مقدار دقیق سکه"]
        ],
        [
            ['text' => "بازگشت به منو ↪️"],['text' => "/admin"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'تغییر سکه ها'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منو تغییر سکه ها',
        'reply_markup' => $replyKeyboard_Main,
        'reply_to_message_id' => $message_id,
        'allow_sending_without_reply' => true
    ];
}

function getAdvertisementData($advId, $user_from_id)
{

    global $connection, $telegram, $chat_id;

    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_id = ? AND adv_user_numeric_id = ?");
    if (!$stmt) {
        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ii", $advId, $user_from_id);
    $result = mysqli_stmt_execute($stmt);
    if (!$result) {
        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => mysqli_error($connection)]);
        return false;
    }

    // Get the results
    $result = mysqli_stmt_get_result($stmt);

    $adv_info = [];

    // Check the result count
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        $adv_info['adv_id'] = $row['adv_id'];
        $adv_info['adv_user_numeric_id'] = $row['adv_user_numeric_id'];
        $adv_info['adv_message_id'] = $row['adv_message_id'];
        $adv_info['adv_text'] = $row['adv_text'];
        $adv_info['adv_contact_info'] = $row['adv_contact_info'];
        $adv_info['adv_required_skills'] = $row['adv_required_skills'];
        $adv_info['adv_is_paid'] = $row['adv_is_paid'];
        $adv_info['adv_is_approved'] = $row['adv_is_approved'];
        $adv_info['adv_is_assigned'] = $row['adv_is_assigned'];
        $adv_info['adv_creation_date'] = $row['adv_creation_date'];
        $adv_info['adv_publication_date'] = $row['adv_publication_date'];
        $adv_info['adv_assignment_date'] = $row['adv_assignment_date'];
    }
    else {
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    //  mysqli_close($connection);
    return $adv_info;
}

function showMyAdvertisementsList($chat_id, $user_numeric_id): void
{

    global $connection, $telegram;
    $rowCount = 0;
    $whole_list = "";

    // 10 last ones from lowest adv_id to the last.
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC");
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC LIMIT {$count}");
    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? ORDER BY adv_id ASC");
    mysqli_stmt_bind_param($stmt, "i", $user_numeric_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);



    if ($result !== false) {

        $long_adv_text = [];

        while ($row = mysqli_fetch_assoc($result)) {


            $status_text = calcStatus($row['adv_is_paid'], $row['adv_is_approved'], $row['adv_is_assigned']);
            $text = "➖➖➖➖➖ /id" . $row['adv_id'] . " ➖➖➖➖➖
● وضعیت آگهی: " . $status_text . "
● متن آگهی:     
" . $row['adv_text'] . "
";

            $adv_length = mb_strlen($text, 'UTF-8');

            // todo : fix
//            $array = ["string1", "string2", "string3"]; // Replace with your array of strings
//
//            $totalLength = 0;
//
//            foreach ($array as $text) {
//                $length = mb_strlen($text, 'UTF-8');
//                $totalLength += $length;
//            }
//
//            if ($totalLength <= 4000) {
//                echo "Total length is not greater than 4000";
//            } else {
//                echo "Total length is greater than 4000";
//            }

            if ($adv_length >= 4000) { // telegram max limit = 4096
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
                //$long_adv_text[] = $text;
                //continue;
            }
            else {
                $long_adv_text[] = $text;
                //continue;
            }


            //$whole_list = $whole_list . $text;
        }

        $rowCount = mysqli_num_rows($result);
    }



    if ($rowCount == 0) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "شما هنوز آگهی ثبت شده‌ای ندارید."]);
    }
    else { // number of advertisements.
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "" . $rowCount . " آگهی یافت شد."]);
//        foreach ($long_adv_text as $text) {
//            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
//        }
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $whole_list]);
    }


    mysqli_stmt_close($stmt);
}

function createChannelBox($channel_id, string $text)
{
//    global $telegram;

//    $inlineButton = $telegram->buildInlineKeyboardButton("برای درج آگهیت کلیک کن", 'https://t.me/Freelancerly_bot');
//    $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineButton]]);
    $inlineKeyboard = createChannelAdvKeyboard();

    return [
        'chat_id' => $channel_id,
        'text' => $text,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        'allow_sending_without_reply' => true
    ];
}

function createChannelAdvKeyboard()
{
    global $telegram;

    $inlineButton = $telegram->buildInlineKeyboardButton("برای درج آگهیت کلیک کن", 'https://t.me/Freelancerly_bot');
    $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineButton]]);

    return $inlineKeyboard;
}

function createMainAdvertisementBox($chat_id, array $data)
{
    global $telegram, $user_from_id, $adv_price_coin;
//    $user_coins = getCoins($data['user_from_id']);
    $user_coins = getCoins($user_from_id);
    $payment_url = "https://www.freelancerly.ir/freelancerly_bot/pay/pay-confirm.php?user_id={$user_from_id}&adv_id={$data['adv_id']}";

    $status_text = calcStatus($data['adv_is_paid'], $data['adv_is_approved'], $data['adv_is_assigned']);

    // todo: expired section


    $saved_adv = "
● کد آگهی: {$data['adv_id']}
● وضعیت آگهی: {$status_text}
● متن آگهی:
{$data['adv_text']}
● اطلاعات تماس:
{$data['contact_info']}
";

    if (!$data['adv_is_paid'] and $adv_price_coin != 0) {
        $saved_adv .= "

⚡️ آگهی شما پس از پرداخت و تأیید ادمین به صورت آنی در کانال منتشر می‌شود.
⚠️ برای کند نبودن روند پرداخت بانکی VPN خود را خاموش کنید.
";
    }
    elseif (!$data['adv_is_paid'] and $adv_price_coin == 0){
        $saved_adv .= "

⚡️ آگهی شما پس از تأیید ادمین به صورت آنی در کانال منتشر می‌شود.
";
    }

    //$saved_adv = $data['adv_pre_text'] . $saved_adv;
    if (isset($data['adv_pre_text'])) {
        $saved_adv = $data['adv_pre_text'] . $saved_adv;
    }
    if (isset($data['adv_post_text'])) {
        $saved_adv = $saved_adv . $data['adv_post_text'];
    }


    // inline buttons
    // todo : check if is assigned , is paid and is approved.

    if ($data['adv_is_paid'] == 0 && $data['adv_is_approved'] == 0 && $data['adv_is_assigned'] == 0) {
        if ($adv_price_coin == 0) {
            $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " سکه 🌕 (رایگان)", null, "walletPay_" . $data['adv_id']);
            $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("موجودی سکه = " . $user_coins, 'https://t.me/freelancerly_bot');
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2], [$inlineKeyboardButton3]]);
        }
        else {
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " هزار تومان 💳", $payment_url);
            $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("پرداخت " . $adv_price_coin . " سکه 🌕", null, "walletPay_" . $data['adv_id']);
            $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("پرداخت با کارت به کارت + هدیه", null, "card2card_" . $data['adv_id']);
            $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("موجودی سکه = " . $user_coins, 'https://t.me/freelancerly_bot');
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1, $inlineKeyboardButton2], [$inlineKeyboardButton4], [$inlineKeyboardButton3]]);
        }
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == -1 && $data['adv_is_assigned'] == 0) {
        $status_text = 'تایید نشده';
        $status_text .= " ❌";
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == -2 && $data['adv_is_assigned'] == 0) {
        // حذف شده همان رد شدن بدون بازگرداندن پول است.
        $status_text = 'تایید نشده';
        $status_text .= " ❌";
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 0 && $data['adv_is_assigned'] == 0) {
        // $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("در انتظار تایید مدیر ⏳", 'https://t.me/Freelancerly_bot');
        $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("پرداخت شده ✅", 'https://t.me/freelancerly_bot');
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 0) {
        $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("🔴 ویرایش آگهی به واگذار شده", null, "assigned_" . $data['adv_id']);
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2], [$inlineKeyboardButton3]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 1) {
//        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("🔴 واگذار شده", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton4]]);
    }
    elseif ($data['adv_is_paid'] == 1 && $data['adv_is_approved'] == 1 && $data['adv_is_assigned'] == 2) {
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("⚫️ منقضی شده", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("مشاهده در کانال", 'https://t.me/freelancerly/' . $data['adv_message_id']);
        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton4]]);
    }


    return [
        'chat_id' => $chat_id,
        'text' => $saved_adv,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        'allow_sending_without_reply'
    ];
}

function escape_reserved_chars(string $text): string
{
    $reserved_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    // Add any other reserved characters to the array as needed
    // '`',
    foreach ($reserved_chars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }

    return $text;
}


function createAdminManageBox($admin_chat_id, array $data)
{
    global $telegram;

    if (!empty($data['username'])){$data['username'] = "@" . $data['username'];}
    else {$data['username'] = 'ندارد';}

//    $data['user_from_id'] = '`' . $data['user_from_id'] . '`';
//    $data['adv_text'] = '`' . $data['adv_text'] . '`';

//    $data['user_from_id'] = '<code>' . $data['user_from_id'] . '</code>';
//    $data['adv_text'] = '<code>' . $data['adv_text'] . '</code>';


    $saved_adv = "
#درخواست_تایید 
● نام کاربری: {$data['username']}
● آیدی عددی: {$data['user_from_id']}

● کد آگهی: {$data['adv_id']}
● متن آگهی:
{$data['adv_text']}
● اطلاعات تماس:
{$data['contact_info']}
";


//    $saved_adv = escape_reserved_chars($saved_adv);
//    $saved_adv = "#درخواست_تایید
//    " . $saved_adv;

    // Inline Buttons
    if ($data['adv_is_paid'] && $data['adv_is_approved'] == 0) {

        $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("تایید ✅", null, "approveReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton2 = $telegram->buildInlineKeyboardButton("رد ❌", null, "rejectReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton3 = $telegram->buildInlineKeyboardButton("ویرایش 📄", null, "editReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton4 = $telegram->buildInlineKeyboardButton("حذف 🗑", null, "deleteReq_" . $data['adv_id'] . "_" . $data['user_from_id'] . "_" . $data['user_paid_box_message_id']);
        $inlineKeyboardButton5 = $telegram->buildInlineKeyboardButton("کپی متن آگهی", null, null, null, $data['adv_text']);
        $inlineKeyboardButton6 = $telegram->buildInlineKeyboardButton("کپی آیدی عددی", null, null, null, $data['user_from_id']);

        $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton2, $inlineKeyboardButton1], [$inlineKeyboardButton4, $inlineKeyboardButton3], [$inlineKeyboardButton6, $inlineKeyboardButton5]]);
    }

    return [
        'chat_id' => $admin_chat_id,
        'text' => $saved_adv,
        'reply_markup' => $inlineKeyboard,
        'disable_web_page_preview' => true,
        //'allow_sending_without_reply'
        //'parse_mode' => 'HTML'
    ];
}


// Function to reward a user for inviting a new user
function reward_user($user_id): void
{
    // Add your code here to reward the user
}

// Function to check if a user has already been rewarded for a specific invitation
function is_rewarded($inviter_id, $invited_id): void
{
    // Add your code here to check if the invited user has already been rewarded by the inviter
    // Return true if the user has already been rewarded, false otherwise
}

// Function to mark a user as rewarded for a specific invitation
function mark_rewarded($inviter_id, $invited_id): void
{
    // Add your code here to mark the invited user as rewarded by the inviter
}

/*
function show_all_data($my_Chat_Id): void
{
    global $telegram;

    // getMe()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getMe()' . "\n" . prettyJson($telegram->getMe())]);
    // getChat()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChat()' . "\n" . prettyJson($telegram->getChat(['chat_id' => $my_Chat_Id]))]);
    // getChatAdministrators()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatAdministrators()' . "\n" . prettyJson($telegram->getChatAdministrators(['chat_id' => $my_Chat_Id]))]);
    // getChatMember()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatMember()' . "\n" . prettyJson($telegram->getChatMember(['chat_id' => $my_Chat_Id]))]);
    // getChatMembersCount()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getChatMembersCount()' . "\n" . prettyJson($telegram->getChatMembersCount(['chat_id' => $my_Chat_Id]))]);
    // getData()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getData() | file_get_contents()' . "\n" . prettyJson($telegram->getData())]);
    // getUpdateType()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getUpdateType()' . "\n" . prettyJson($telegram->getUpdateType())]);
    // getUserProfilePhotos()
//    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'getUserProfilePhotos()'."\n". prettyJson($telegram->getUserProfilePhotos(['user_id' => $user_id]))]);
    // getUpdates()
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'getUpdates()' . "\n" . prettyJson($telegram->getUpdates())]);


    // callbacks
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Data()' . "\n" . prettyJson($telegram->Callback_Data())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_ID()' . "\n" . prettyJson($telegram->Callback_ID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_ChatID()' . "\n" . prettyJson($telegram->Callback_ChatID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_FromID()' . "\n" . prettyJson($telegram->Callback_FromID())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Message()' . "\n" . prettyJson($telegram->Callback_Message())]);
    $telegram->sendMessage(['chat_id' => $my_Chat_Id, 'text' => 'Callback_Query()' . "\n" . prettyJson($telegram->Callback_Query())]);
}
*/

function isChatMember($chat_id, $user_id): bool
{
    global $telegram;
    $is_member_response = $telegram->getChatMember(['chat_id' => $chat_id, 'user_id' => $user_id]);

    //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => $is_member_response["ok"]]);
    // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => prettyJson($is_member_response)]);


    if ($is_member_response["ok"] == false || $is_member_response["result"]["status"] == "left") {

        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "false"]);
        return false;
    } else {

        //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "ok"]);
        return true;
    }
}




/*
 * idpay methods
 */

/**
 * @param array $params
 * @return bool
 */
function idpay_payment_create($params)
{
    global $style;

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_PAYMENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result);

    if (empty($result) || empty($result->link)) {

//        print 'Exception message:';
//        print '<pre>';
//        print_r($result);
//        print '</pre>';

//        $font_path = __DIR__ . "/../IRANSansWeb.ttf";
        echo $style;
        echo "<div class=\"container\"><h1>$result->error_message</h1></div>";


        return FALSE;
    }

    /*
        // save to db the response
        //  {
        //      "id": "d2e353189823079e1e4181772cff5292",
        //      "link": "https://idpay.ir/p/ws-sandbox/d2e353189823079e1e4181772cff5292"
        //  }
    */
    //.Redirect to payment form
    header('Location:' . $result->link);
}


/*
// needs         'id' => $response['id'],
//        'order_id' => $response['order_id'],
*/
/**
 * @param array $response
 * @return bool
 */
function idpay_payment_get_inquiry($response)
{

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $params = array(
        'id' => $response['id'],
        'order_id' => $response['order_id'],
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_INQUIRY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result);

    if (empty($result) || empty($result->status)) {

        print 'Exception message:';
        print '<pre>';
        print_r($result);
        print '</pre>';

        echo $result->error_message;

        return FALSE;
    }

    if ($result->status == 10) {
        return TRUE;
    }

    print idpay_payment_get_message($result->status);

    return FALSE;
}



/*
// needs      'id' => $response['id'],
//        'order_id' => $response['order_id'],
*/
/**
 * @param array $response
 * @return bool
 */
function idpay_payment_verify($response)
{

    $header = array(
        'Content-Type: application/json',
        'X-API-KEY:' . APIKEY,
        'X-SANDBOX:' . SANDBOX,
    );

    $params = array(
        'id' => $response['id'],
        'order_id' => $response['order_id'],
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL_VERIFY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result);

    if (empty($result) || empty($result->status)) {

        print 'Exception message:';
        print '<pre>';
        print_r($result);
        print '</pre>';

        //echo $result->error_message;

        return FALSE;
    }

    print idpay_payment_get_message($result->status);

    print '<pre>';
    print_r($result);
    print '</pre>';
}


/**
 * @param int $status
 * @return string
 */
function idpay_payment_get_message($status)
{

    switch ($status) {
        case 1:
            return  'پرداخت انجام نشده است ';

        case 2:
            return 'پرداخت ناموفق بوده است ';

        case 3:
            return 'خطا رخ داده است ';

        case 4:
            return "بلوکه شده ";

        case 5:
            return "برگشت به پرداخت کننده ";

        case 6:
            return 'برگشت خورده سیستمی ';

        case 7:
            return 'انصراف از پرداخت ';

        case 8:
            return "به درگاه پرداخت منتقل شد ";

        case 10:
            return 'در انتظار تایید پرداخت ';

        case 100:
            return 'پرداخت تایید شده است ';

        case 101:
            return 'پرداخت قبلاً تایید شده است ';

        case 200:
            return "به دریافت کننده واریز شد ";

        default:
            return 'ارور ';
    }
}

/**
 * @return void
 */
function validateUserForPayment(): void
{
    $redirect_after = 3;
    // todo : refactor this
    $redirect_to = "https://www.freelancerly.ir/freelancerly_bot";

    $style = "<style>
          
                @font-face {
                    font-family: 'IRANSans';
                    src: url('../assets/fonts/IRANSansWeb.ttf') format('truetype');
                }

                .myh1 {
                    font-family: 'IRANSans', Arial, sans-serif;
                    direction: rtl;
                    margin: 0 auto 0 auto;
                }
                
                .my-container {
                    display: flex;
                    justify-content: center; /* Center horizontally */
                    align-items: center; /* Center vertically */
                    height: 100vh; /* Set container height for vertical centering */
                }
             
          </style>";
    echo $style;



    if (empty($_GET['user_id']) || empty($_GET['adv_id'])) {
        echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 4)</h1></div>";
        sleep($redirect_after);
        header('Location: https://www.freelancerly.ir/freelancerly_bot');
        exit;
    }
    else {
        try {
            $user_from_id = filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT);
            $advId = filter_input(INPUT_GET, 'adv_id', FILTER_SANITIZE_NUMBER_INT);

            if (empty($user_from_id) || empty($advId)) {
                echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 5)</h1></div>";
                sleep($redirect_after);
                header('Location: https://www.freelancerly.ir/freelancerly_bot');
                exit;
            }

            // Use the filtered and sanitized values in your database query or further processing...
            if (!hasUserThisUnpaidAdv($user_from_id, $advId)) {
                echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 1)</h1></div>";
                sleep($redirect_after);
                header('Location: https://www.freelancerly.ir/freelancerly_bot');
                exit;
            }
        }
        catch (Exception $e) {
            //echo "Error: " . $e->getMessage();
            echo "<div class=\"my-container\"><h1 class='myh1'>اطلاعات آگهی شما جهت پرداخت یافت نشد. (خطای کد 45)</h1></div>";
            sleep($redirect_after);
            header('Location: https://www.freelancerly.ir/freelancerly_bot');
            exit;
        }
    }
}



function isForwardedMessage($last_msg_data): bool
{
    // isset($last_msg_data['message']['forward_from_chat'])
    // isset($last_msg_data['message']['forward_from'])
    // isset($last_msg_data['message']['forward_sender_name'])

    if (isset($last_msg_data['message']['forward_date'])) {
        return true;
    } else {
        return false;
    }
}

function hasText($last_msg_data): bool
{
    if (isset($last_msg_data['message']['text'])) {
        return true;
    }
    else {
        return false;
    }
}

function hasInlineKeyboard($last_msg_data): bool
{
    if (isset($last_msg_data['message']['reply_markup']['inline_keyboard'])) {return true;}
    else {
        return false;
    }
}

function replaceByFilters($file_path, $input_string) {
    // Read the JSON file and decode it into a PHP array
    $json_data = file_get_contents($file_path);
    $data = json_decode($json_data, true);

    // Get the filters object from the PHP array
    $filters = $data['filters'];

    if (count($filters) > 0) {
        // Replace placeholders in the input string with corresponding values from the filters object
        foreach ($filters as $key => $value) {
            if ($value == "/حذف") { $value = ''; }
            $input_string = str_replace($key, $value, $input_string);
        }
    }

    // Return the modified string
    return $input_string;
}

function isOk($reply4) {

    if ($reply4['ok']) {
        $is_ok4 = '✅ ارسال شد.';
    }
    elseif (isset($reply4["parameters"]["retry_after"])) {
        $is_ok4 = "🔴 ارسال نشد.\nلطفا بعدا از {$reply4["parameters"]["retry_after"]} ثانیه مجدد امتحان کنید.";
    }
    else {
        $is_ok4 = $reply4['description'];
    }
    return $is_ok4;

}

function displayFilterButtons($chat_id): array
{
    global $telegram;

    // build the custom keyboard
    $rep_KeyB_BTNs_Main = [
        [
            ['text' => "افزودن فیلتر"], ['text' => "شروع فوروارد"]
        ],
        [
            ['text' => "مشاهده/حذف تکی فیلترها"], ['text' => "حذف همه فیلترها"]
        ],
        [
            ['text' => "فعال/غیرفعال کردن موقت فیلترها"]
        ],
        [
            ['text' => "بازگشت به منو ↪️"], ['text' => "/admin"]
        ],
    ];

    $replyKeyboard_Main = $telegram->buildKeyBoard(
        $rep_KeyB_BTNs_Main,
        $onetime = false,
        $resize = true,
        $selective = true,
        $persistent = true,
        $placeholder = 'منوی تنظیم فیلترها'
    );

    // build the message to send
    return [
        'chat_id' => $chat_id,
        'text' => 'منوی تنظیم فیلترها',
        'reply_markup' => $replyKeyboard_Main,
        'allow_sending_without_reply' => true
        // 'reply_to_message_id' => $message_id
    ];
}

function deleteFromFilters($file_path, $index)
{

    $index_to_remove = $index - 1;

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Get the filters object and convert it to a numeric array
    $filters = $data['filters'];
    $filter_keys = array_keys($filters);
    $numeric_filters = array_values($filters);

    // Check if the specified index is within bounds
    if ($index_to_remove >= 0 && $index_to_remove < count($numeric_filters)) {


        // Create copies of the arrays before modifying them
        $new_filter_keys = $filter_keys;
        $new_numeric_filters = $numeric_filters;

        // Remove the element at the specified index from the copied arrays
        array_splice($new_filter_keys, $index_to_remove, 1);
        array_splice($new_numeric_filters, $index_to_remove, 1);

        // Combine the two copied arrays into one new associative array using array_combine()
        $new_filters = array_combine($new_filter_keys, $new_numeric_filters);


        // Remove the key-value pair at the specified index
//        array_splice($numeric_filters, $index, 1);
//        unset($filters[$filter_keys[$index]]);
        //$my_array = ["apple", "banana", "cherry", "date"];


// Create a new array without the element at the specified index
        //$new_array = array_merge(array_slice($my_array, 0, $index));

        // Convert the numeric array back to an associative array
        $data['filters'] = $new_filters;
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $data['filters']]);


        // Encode the updated data back into JSON format
        $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Save the updated JSON data back to the original file
        file_put_contents($file_path, $json_data);
        return true;
    } else {
        return false;
    }
}

function deleteAllFilters($file_path): void
{

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Convert the numeric array back to an associative array
    $data['filters'] = [];

    // Encode the updated data back into JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Save the updated JSON data back to the original file
    file_put_contents($file_path, $json_data);
}

function readJsonFilters($file_path): array
{

    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Extract all key-value pairs from the "filters" object
    $filters = $data['filters'];
    $key_value_pairs = [];
    $counter = 1;
    foreach ($filters as $key => $value) {
        $key_value_pairs[] = "🟠$counter. $key => $value";
        $counter++;
    }

    return [
        "count" => count($filters),
        // Return the key-value pairs as a single string with each pair on a new line
        "key_value_pairs" => implode("\n", $key_value_pairs)
    ];

}


function addOrUpdateJsonFilter($file_path, $key, $value)
{
    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    $data['filters'][$key] = $value;

    // Encode the updated data back into JSON format
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Save the updated JSON data back to the original file
    file_put_contents($file_path, $json_data);
}

function searchAndUpdateFilterValue($file_path, $search_value, $new_value)
{
    // Open the JSON file
    $json_data = file_get_contents($file_path);

    // Decode the JSON data into an array
    $data = json_decode($json_data, true);

    // Loop through each key-value pair in the "filters" object
    foreach ($data['filters'] as $key => $value) {
        // Check if the value matches the search value

        if ($value === $search_value) {
            // Update the value with the new value
            $data['filters'][$key] = $new_value;

            // Encode the updated data back into JSON format
            $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Save the updated JSON data back to the original file
            file_put_contents($file_path, $json_data);

            // Stop looping since we've found and updated the value
            break;
        }
    }
}


function separateForwardedAdv($inputString) {

    // Split the string based on the newline character (\n)
    $parts = explode("\n", $inputString);


    //global $telegram,$chat_id,$message_id;

//    foreach ($parts as $key => $value) {
//        //echo $key . ": " . $value . "<br>";
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "key: $key ++ value: $value", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
//    }


    if (count($parts) === 2 || count($parts) === 3) {
        // Trim the parts
        //return array_map('trim', $parts);

        $text = $parts[0];
        $text = mb_convert_encoding(trim($text), 'UTF-8');

        if (!empty($parts[2])) {$id = $parts[2];}
        elseif (!empty($parts[1])) {$id = $parts[1];}
        else {return false;}
        $id = mb_convert_encoding(trim($id), 'UTF-8');

        return [$text, $id];
    }

    return false;
}



function separateForwardedAdv2($inputString) {

    // Split the string into parts based on line breaks
    $parts = explode("\n", $inputString);

    if (count($parts) === 3) {
        // Extract and trim the text and ID based on their positions
        $text = trim($parts[0]);
        $id = trim($parts[1]);

        return [$text, $id];
    }
    // Return null if the string doesn't contain three parts
    return false;
}

function separateForwardedAdv3($inputString) {

    //$inputString = mb_convert_encoding($inputString, 'UTF-8');

    // Find the position of "🆔 " and "- - - - - - - - - - - - - -"
    $idStart = strpos($inputString, "🆔 ");
    $idEnd = strpos($inputString, "- - - - - - - - - - - - - -");

    if ($idStart !== false && $idEnd !== false) {
        // Extract and trim the ID based on the positions
        $id = substr($inputString, $idStart + 5, $idEnd - $idStart - 5);
        $id = trim($id);

        // Extract and trim the text before the ID
        $text = trim(substr($inputString, 0, $idStart));

        return [mb_convert_encoding($text, 'UTF-8'), mb_convert_encoding($id, 'UTF-8')];
    }

    // Return null if the specified markers are not found
    return false;
}





//todo: use encryption in payment GET
/*
// Encryption function
function encryptData($value, $key) {
    $encryptedValue = openssl_encrypt($value, "AES-256-CBC", $key, 0, random_bytes(16));
    return urlencode($encryptedValue);
}

// Decryption function
function decryptData($encryptedValue, $key) {
    $decryptedValue = openssl_decrypt(urldecode($encryptedValue), "AES-256-CBC", $key, 0, random_bytes(16));
    return $decryptedValue;
}

// Define the value and secret key
$value = "myValue";
$key = "mySecretKey";

// Encrypt the data and append it to the URL
$encryptedValue = encryptData($value, $key);
$url = "http://example.com?data=" . $encryptedValue;

// Retrieve the encrypted value from the URL
$encryptedValueFromUrl = $_GET['data'];

// Decrypt the value and echo the decrypted data
$decryptedValue = decryptData($encryptedValueFromUrl, $key);
echo $decryptedValue; // Output: "myValue"


http://example.com?data=myEncryptedData
URL with encrypted data: http://example.com?data=ue%2FsbupYiCEoLu5Z8J9yaA%3D%3D

In summary, if a user edits the URL with encrypted data, the decryption process may fail or
produce incorrect results. This is why it's important to ensure the integrity and
security of the encrypted data in transit. It's a good practice to implement additional
measures such as digital signatures, message authentication codes (MACs), or encryption
with authenticated modes to detect tampering and ensure the authenticity of the data.




// second way.**************
Certainly! To enhance the security and integrity of the encrypted data in transit,
you can use a combination of encryption and authentication techniques such as digital
signatures or message authentication codes (MACs).
Here's an example using HMAC-SHA256 for message authentication:
// Encryption function
function encryptData($value, $key) {
    $encryptedValue = openssl_encrypt($value, "AES-256-CBC", $key, 0, random_bytes(16));
    return urlencode($encryptedValue);
}

// Decryption function
function decryptData($encryptedValue, $key) {
    $decryptedValue = openssl_decrypt(urldecode($encryptedValue), "AES-256-CBC", $key, 0, random_bytes(16));
    return $decryptedValue;
}

// Generate an HMAC-SHA256 signature for the encrypted data
function generateSignature($data, $key) {
    $signature = hash_hmac('sha256', $data, $key);
    return urlencode($signature);
}

// Verify the HMAC-SHA256 signature
function verifySignature($data, $signature, $key) {
    $expectedSignature = generateSignature($data, $key);
    return hash_equals($expectedSignature, $signature);
}

// Define the value and secret key
$value = "myValue";
$key = "mySecretKey";

// Encrypt the data and generate the signature
$encryptedValue = encryptData($value, $key);
$signature = generateSignature($encryptedValue, $key);

// Append the encrypted value and the signature to the URL
$url = "http://example.com?data=" . $encryptedValue . "&signature=" . $signature;

// Retrieve the encrypted value and the signature from the URL
$encryptedValueFromUrl = $_GET['data'];
$signatureFromUrl = $_GET['signature'];

// Verify the signature
if (verifySignature($encryptedValueFromUrl, $signatureFromUrl, $key)) {
    // Signature is valid, decrypt the value and echo the decrypted data
    $decryptedValue = decryptData($encryptedValueFromUrl, $key);
    echo $decryptedValue; // Output: "myValue"
} else {
    // Signature is invalid, handle the error
    echo "Invalid signature!";
}

*/



//function insert_advertisement($connection, $numeric_id, $text, $contact_info, $status, $timestamp): bool
//{
//    // Prepare SQL statement with placeholders for values
//    $stmt = mysqli_prepare($connection, "INSERT INTO advertisements (adv_user_numeric_id, adv_text, adv_contact_info, adv_status, adv_creation_date) VALUES (?, ?, ?, ?, ?)");
//
//    // Bind the values to the placeholders
//    mysqli_stmt_bind_param($stmt, "issss", $numeric_id, $text, $contact_info, $status, $timestamp);
//
//    // Execute the statement and return success or failure
//    if(mysqli_stmt_execute($stmt)) {
//        // Close statement and connection
//        mysqli_stmt_close($stmt);
////        mysqli_close($connection);
//        return true;
//    } else {
//        // Close statement and connection
//        mysqli_stmt_close($stmt);
////        mysqli_close($connection);
//        return false;
//    }
//}

// test this version
//function addOrUpdateJson(string $filename, array $data): void
//{
//    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';
//
//    // Check if the file exists, create a new file if it does not.
//    if (!file_exists($file_path)) {
//        file_put_contents($file_path, "{}");
//    }
//
//    $jsonData = file_get_contents($file_path);
//    $jsonArray = json_decode($jsonData, true);
//
//    foreach ($data as $key => $value) {
//        if ($jsonArray[$key] !== $value) { // check if value has changed
//            $jsonArray[$key] = $value;
//        }
//    }
//
//    $jsonData = json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//    file_put_contents($file_path, $jsonData);
//}
//
//// run
//$data1 = [
//    'user_username_id' => $username,
//    'adv_user_numeric_id' => $user_from_id,
//    'adv_text' => $message_text
//];
//
//$data2 = [
//    'user_username_id' => $newUsername,
//    'adv_user_numeric_id' => $newUserId,
//    'adv_text' => $newMessage
//];
//
//addOrUpdateJson('filename', [$data1, $data2]);

//function addOrUpdateJson($filename, $key, $value): bool
//{
//
//    $file_path = __DIR__ . '/temp_incomplete_adv/' . $filename . '.json';
//
//    // Read the existing data from the file
//    $jsonData = file_get_contents($file_path);
//
//    // Decode the JSON data into an associative array
//    $data = json_decode($jsonData, true);
//
//    // Update the value if the key already exists or add it as a new key-value pair
//    $data[$key] = $value;
//
//    // Encode the updated data back into JSON format
//    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//
//    // Write the updated data back to the file
//    if (file_put_contents($file_path, $jsonData)) {
//        return true;
//    } else {
//        return false;
//    }
//}

/*
function showMyAdvertisementsList(int $chat_id, int $user_numeric_id): void
{

    global $connection, $telegram;
    $rowCount = 0;
    $whole_list = "";

      $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? ORDER BY adv_id ASC");
    // 10 last ones from lowest adv_id to the last.
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC");
//    $stmt = mysqli_prepare($connection, "SELECT * FROM advertisements WHERE adv_user_numeric_id = ? AND adv_id >= (SELECT GREATEST(MAX(adv_id) - 9, 0) FROM advertisements WHERE adv_user_numeric_id = ?) ORDER BY adv_id ASC LIMIT {$count}");
    mysqli_stmt_bind_param($stmt, "i",  $user_numeric_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result !== false) {

        $long_adv_text = [];

        // Loop through the rows and echo the data
        while ($row = mysqli_fetch_assoc($result)) {

            $is_paid_text = '';

            if ($row['adv_is_paid']) {
                $is_paid_text .= " ✅";
            }

            $text = "➖➖➖➖➖ /id" . $row['adv_id'] . " ➖➖➖➖➖
● وضعیت آگهی: " . $is_paid_text . "
● متن آگهی:
" . $row['adv_text'] . "
";

            if (mb_strlen($text, 'UTF-8') > 450) { // max limit = 4096
                $long_adv_text[] = $text;
                continue;
            }

            $whole_list = $whole_list . $text;

        }

        $rowCount = mysqli_num_rows($result);
    }

    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "" . $rowCount . " آگهی یافت شد."]);
    foreach ($long_adv_text as $text) {
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text]);
    }
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $whole_list]);
    mysqli_stmt_close($stmt);
}
*/

//function subtractCoins(int $user_from_id, int $coins_to_subtract, mysqli $connection): bool
//{
//    $query = "UPDATE users SET user_coins_count = user_coins_count - ? WHERE user_numeric_id = ? AND user_coins_count >= ?";
//    $stmt = $connection->prepare($query);
//    $stmt->bind_param("iii", $coins_to_subtract, $user_from_id, $coins_to_subtract);
//    $connection->begin_transaction();
//    $stmt->execute();
//    $affected_rows = $stmt->affected_rows;
//    if ($affected_rows != 1) {
//        $connection->rollback();
//        return false;
//    }
//    $connection->commit();
//    return true;
//}

//function decodeUnicodeString($str) {
//    $decodedStr = json_decode('["'.$str.'"]', true, 512, JSON_UNESCAPED_UNICODE)[0];
//
//    // Replace escaped newline characters with actual newlines
//    $decodedStr = str_replace('\n', "\n", $decodedStr);
//
//    // Replace multiple whitespace characters with a single space
//    $decodedStr = preg_replace('/\s+/', ' ', $decodedStr);
//
//    return $decodedStr;
//}

// Usage Example
//$phone_number = "09120000000";
//if (is_valid_phone_number($phone_number)) {
//    echo "$phone_number is a valid phone number";
//} else {
//    echo "$phone_number is not a valid phone number";
//}
//
// Usage Example
//$id = "@jobfreelancer";
//if (is_valid_id($id)) {
//    echo "$id is a valid Id";
//} else {
//    echo "$id is not a valid Id";
//}

/*
File created successfully:
 /home/playmak1/public_html/telegram_bots/Freelancerly_bot/temp_incomplete_adv/133084833.json
*/

// addOrUpdateJson($file_location, 'ADV_PRICE', 20000);

$file_location = __DIR__ . '/bot_settings/' . '133084833' . '.json';
if (!file_exists($file_location)) {
    $default_ADV_PRICE = 20000;
    $data = [
        "ADV_PRICE" => $default_ADV_PRICE // default value if file doesnt exist
        /*
        //"filters" => [
            // "name" => "John Doe",
        //]
        */
    ];
    createJsonFile2($file_location, $data);
    $adv_price = $default_ADV_PRICE;
}
else {
    $adv_price = getValueByKeyFromJson($file_location, 'ADV_PRICE');
}
$ADV_PRICE = $adv_price;

const BOT_USERNAME = "freelancerly";

// درصد مبلغ جریمه
const fine_amount_percentage = 10;

// print php hello






// Instantiate the class
$telegram = new Telegram($bot_token);
// Get Message and Sender info.
$message_text = $telegram->Text(); // Message Text
//$message_text = strtolower($message_text);
$message_id = $telegram->MessageID(); // Message ID
$message_Type = $telegram->getUpdateType(); // Message Type
$chat_id = $telegram->ChatID();
$user_from_id = $telegram->UserID(); // numeric user Id
$username = $telegram->Username(); // @ID
$first_Name = $telegram->FirstName();
$last_Name = $telegram->LastName();
$last_msg_data = $telegram->getData(); // Get the last message data
$callback_query = $telegram->Callback_Query();


// todo: show help box to the user
// todo : use elseif
// todo : in show advertisements show ... for long texts.
// todo : show main menu after assigning adv.
// todo : rewrite cart2cart alert.
// todo : implement $debug = false; and $is_Under_Maintenance = false;.


/*
 * SETTINGS
 */

// todo: what happens for the saved id in the DB if user change his/her ID.
// todo : recommended to update userdata and ID exactly before user payment./
// todo : fix users that dont have ID and fix user_first_msg_timestamp for them
// todo : also check user_last_msg_timestamp
// todo : update welcome text
// todo : fix support message reply to in client side.
// برگزاری چالش و افزودن دکمه زیرمجموعه گیری به ادمین پنل

// used under channel adv Boxes.
$freelancerlyChannelUsername = 'freelancerly'; // Freelancerly
const CHANNEL_ID = -1001446962849; // Freelancerly
$SUPPORT_ID = '@hrsh333'; // support Admin ID
$SUPPORT_ID_RTL = 'hrsh333@'; // support Admin ID
//$step = 0;
$Admin_Username = '@hrsh333';
$admin_id = 133084833;
$coins_for_free_adv = 20;
$coins_foreach_invite = 2;
global $ADV_PRICE;
$adv_price_coin = $ADV_PRICE/1000;
$is_admin = false;
if ($user_from_id == $admin_id){ $is_admin = true; }
$file_path = __DIR__ . '/temp_incomplete_adv/' . $user_from_id . '.json';

// $force_join_channels = [];


//$telegram->sendMessage(['chat_id' => $chat_id, 'text' => $message_text]);
//$telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'getData() | file_get_contents()' . "\n" . prettyJson($telegram->getData())]);





/*************
 * CallBacks *
 ************/
if (!empty($callback_query)) {

    $callback_value = $telegram->Callback_Data();
    $parts = split_string($callback_value);
//    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $parts[0].'  '.$parts[1].'  '.$parts[2]]);


    // user - pay with wallet.
    if ($parts[0] == 'walletPay') {


        $pay_answer_text = "";
        $advId = $parts[1];
        $adv_info = getAdvertisementData($advId, $user_from_id);

        if ($adv_info['adv_is_paid'] == 0) {

            $user_coins = getCoins($user_from_id);
            if ($user_coins >= $adv_price_coin) {
                if (subtractCoins($user_from_id, $adv_price_coin)) {
                    if (setIsPaid($advId, $user_from_id,1)) {

                        // delete previous paymentBox
                        $telegram->deleteMessage(['chat_id' => $telegram->Callback_ChatID(), 'message_id' => $callback_query['message']['message_id']]);


                        $myData2 = [
                            //'adv_pre_text' => '',
                            'adv_id' => $adv_info['adv_id'],
                            'adv_message_id' => $adv_info['adv_message_id'],
                            'adv_text' => $adv_info['adv_text'],
                            'contact_info' => $adv_info['adv_contact_info'],
                            'adv_is_paid' => 1,
                            'adv_is_approved' => $adv_info['adv_is_approved'],
                            'adv_is_assigned' => $adv_info['adv_is_assigned'],

                            'user_from_id' => $user_from_id,
                            'chat_id' => $chat_id,

                            'username' => $username,
                        ];
                        // send new updated Box to user - پرداخت شده
                        $response = $telegram->sendMessage(createMainAdvertisementBox($chat_id, $myData2));

                        // send request Box to the Admin for approve/disapprove
                        $myData2['user_paid_box_message_id'] = $response['result']['message_id'];
                        $response2 = $telegram->sendMessage(createAdminManageBox($admin_id, $myData2));
                        if (!$response2['ok']) {
                            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "ارور کد 400 ، متاسفانه مشکلی پیش آمده ، لطفا به ادمین پیام ارسال کنید تا اگهی شما بررسی و تایید شود."]);
                            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $response2['description']]);
                            $telegram->sendMessage(['chat_id' => $admin_id, 'text' => "ارور کد 400 ، لطفا به ادمین پیام ارسال کنید تا اگهی شما تایید شود. آگهی با شناسه و نام کاربری {$username}{$adv_info['adv_id']}"]);
                        }


                        // resend updated box for user with buttons like (assigned or see in channel).
//                        $myData = [
//                            'adv_pre_text' => '',
//                            'adv_id' => $adv_info['adv_id'],
//                            'adv_message_id' => $adv_info['adv_message_id'],
//                            'adv_text' => $adv_info['adv_text'],
//                            'contact_info' => $adv_info['adv_contact_info'],
//                            'adv_is_paid' => 1,
//                            'adv_is_approved' => $adv_info['adv_is_approved'],
//                            'adv_is_assigned' => $adv_info['adv_is_assigned'],
//                            'user_from_id' => $user_from_id,
//                            'chat_id' => $chat_id,
//                        ];


                        $pay_answer_text = "هزینه آگهی شما با شناسه {$advId} با موفقیت پرداخت شد. ✅
 سکه های باقی مانده شما : " . ($user_coins - $adv_price_coin);
                        $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $pay_answer_text, 'show_alert' => true]);

                    }
                    else {
                        $pay_answer_text = 'متاسفانه مشکلی بوجود آمده لطفا به ادمین گزارش دهید. کد 2';
                        $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $pay_answer_text, 'show_alert' => true]);
                    }
                }
                else {
                    $pay_answer_text = 'متاسفانه مشکلی بوجود آمده لطفا به ادمین گزارش دهید. کد 3';
                    $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $pay_answer_text, 'show_alert' => true]);
                }
            }
            else {
                $pay_answer_text = 'تعداد سکه‌های شما کمه!
موجودی : ' . $user_coins;

                $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $pay_answer_text, 'show_alert' => true]);
            }
        }
        elseif ($adv_info['adv_is_paid'] == 1) {
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => 'هزینه این آگهی قبلا پرداخت شده.', 'show_alert' => true]);
        }
    }

    // user - pay with card2card.
    elseif ($parts[0] == 'card2card') {

        $advId = $parts[1];
        $adv_info = getAdvertisementData($advId, $user_from_id);
        //$fixStringOrder = fixStringOrder($SUPPORT_ID);

        if ($adv_info['adv_is_paid'] == 0) {
            $text = "";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => "با کارت به کارت کردن هزینه آگهی به کارت 0482-9289-9971-6037 بنام حمیدرضا شهبازی و ارسال تصویر پرداخت به ادمین جهت تایید ، 1 سکه هدیه دریافت کنید.
آیدی ادمین :{$SUPPORT_ID_RTL}", 'show_alert' => true, 'cache_time' => 30]);
        }
        elseif ($adv_info['adv_is_paid'] == 1) {
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => 'هزینه این آگهی قبلا پرداخت شده.', 'show_alert' => true, 'cache_time' => 30]);
        }
    }
    //fixStringOrder($SUPPORT_ID);
    // user - check is joined - delete force join box.
    /*
    elseif ($parts[0] == 'joined') {

        if (!isChatMember('@'.$freelancerlyChannelUsername, $user_from_id)) {
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => "شما هنوز در کانال(ها) عضو نشده‌اید.", 'show_alert' => true]);
        }
        else {
            $telegram->deleteMessage(['chat_id' => $telegram->Callback_ChatID(), 'message_id' => $callback_query['message']['message_id']]);
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => "", 'show_alert' => true]);
        }
    }
    */


    // set is_assigned.
    elseif ($parts[0] == 'assigned') {

        $advId = $parts[1];
        $chat_and_user_from_id = $parts[2]; // in individual chats these values are the same.
        $adv_info = getAdvertisementData($advId, $user_from_id);

        if ($adv_info['adv_is_assigned'] == 0) {
            if (setIsAssigned($advId, $user_from_id, 1)) {

                // ویرایش آگهی در کانال به واگذار شده.
                $edited_adv_text_for_channel = "{$adv_info['adv_text']}
                        
🔴 واگذار شد
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";


                $inlineKeyboard = createChannelAdvKeyboard();

                $edit_result = $telegram->editMessageText([
                    'chat_id' => CHANNEL_ID,
                    'message_id' => $adv_info['adv_message_id'],
                    'text' => $edited_adv_text_for_channel,
                    'reply_markup' => $inlineKeyboard,
                    'disable_web_page_preview' => true
                ]);
                if ($edit_result['ok']){
                    //ok:	False
                    //error_code:	400
                    //description:	Bad Request: MESSAGE_ID_INVALID
                    $assign_answer_text = "آگهی شما به واگذار شده تغییر وضعیت یافت.";
                }
                else {
                    $assign_answer_text = "ظاهرا این اگهی از کانال پاک شده یا مشکلی وجود دارد.";
                }
                $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $assign_answer_text, 'show_alert' => true]);


                // delete client side previous Box.
                $telegram->deleteMessage(['chat_id' => $telegram->Callback_ChatID(), 'message_id' => $callback_query['message']['message_id']]);

                // todo : update code to not use hard coded values.
                // send updated box to user
                $myData = [
                    //'adv_pre_text' => '',
                    'adv_id' => $adv_info['adv_id'],
                    'adv_message_id' => $adv_info['adv_message_id'],
                    'adv_text' => $adv_info['adv_text'],
                    'contact_info' => $adv_info['adv_contact_info'],
                    'adv_is_paid' => 1,
                    'adv_is_approved' => 1,
                    'adv_is_assigned' => 1,
                    'user_from_id' => $user_from_id,
                    'chat_id' => $chat_id,
                ];
                $telegram->sendMessage(createMainAdvertisementBox($chat_id, $myData));

            }
            else {
                $assign_answer_text = 'متاسفانه مشکلی بوجود آمده لطفا به ادمین گزارش دهید. کد 5';
                $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $assign_answer_text, 'show_alert' => true]);
            }
        }
        elseif ($adv_info['adv_is_assigned'] == 1) {
            $assign_answer_text = "این آگهی قبلا واگذار شده.";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $assign_answer_text, 'show_alert' => true]);
        }
        elseif ($adv_info['adv_is_assigned'] == 2) {
            $assign_answer_text = "این آگهی قبلا منقضی شده.";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => $assign_answer_text, 'show_alert' => true]);
        }

    }


    // set (edit) as expired.

    // ***********************
    // * Admin ُSide Settings *
    // ***********************

    // تایید آگهی و انتشار در کانال
    elseif ($parts[0] == 'approveReq') {


        $advId = $parts[1];
        $chat_and_user_from_id = $parts[2]; // in individual chats these values are the same.
        $user_paid_box_message_id = $parts[3]; // خذف باکس پرداخت شده در سمت کاربر
        $adv_info = getAdvertisementData($advId, $chat_and_user_from_id);

//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $adv_info['adv_text']]);
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "SELECT * FROM advertisements WHERE adv_id = {$advId} AND adv_user_numeric_id = {$chat_and_user_from_id}"]);


        if (setIsApproved($advId, $chat_and_user_from_id, 1)) {


                // ارسال به کانال
            $adv_text_for_channel = "{$adv_info['adv_text']}
                        
🆔 {$adv_info['adv_contact_info']}
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";

            $response = $telegram->sendMessage(createChannelBox(CHANNEL_ID, $adv_text_for_channel)); // send to channel - returns a json obj.
            setAdvertisementMessageId($adv_info['adv_id'], $response['result']['message_id']); // store message_id of inserted adv in the channel for assignment usage.


            // // خذف باکس پرداخت شده در سمت کاربر
            $telegram->deleteMessage(['chat_id' => $chat_and_user_from_id, 'message_id' => $user_paid_box_message_id]);


            // ارسال باکس جدید به کاربر
            // resend updated box for User with buttons like (assigned or see in channel).
            $myData = [
                //'adv_pre_text' => '',
                'adv_id' => $adv_info['adv_id'],
                'adv_message_id' => $adv_info['adv_message_id'],
                'adv_text' => $adv_info['adv_text'],
                'contact_info' => $adv_info['adv_contact_info'],
                'adv_is_paid' => 1,
                'adv_is_approved' => 1,
                'adv_is_assigned' => $adv_info['adv_is_assigned'],
                'user_from_id' => $user_from_id,
                'chat_id' => $chat_id,
            ];
            $telegram->sendMessage(createMainAdvertisementBox($chat_and_user_from_id, $myData));


            // تغییر دکمه های باکس درخواست تایید
            // todo : add assign button for admin.
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("✅ تایید شده", 'https://t.me/freelancerly/' . $response['result']['message_id']);
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
            $telegram->editMessageReplyMarkup([
                'chat_id'=> $telegram->Callback_ChatID(),
                'message_id'=> $callback_query['message']['message_id'],
                'reply_markup'=> $inlineKeyboard
            ]);

            // پاسخ کالبک کوئری
            //$answer_text = "تایید شد.";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => '', 'show_alert' => true]);
        }

    }



    // todo: implement these options...........................................

    // رد کردن آگهی و بازگرداندن پرداختی
    elseif ($parts[0] == 'rejectReq'){

        $advId = $parts[1];
        $chat_and_user_from_id = $parts[2]; // in individual chats these values are the same.
        $user_paid_box_message_id = $parts[3]; // حذف باکس پرداخت شده در سمت کاربر که درهر صورت باید حذف شود
        $adv_info = getAdvertisementData($advId, $chat_and_user_from_id);


        $adv_is_approved = -1;
        if (setIsApproved($advId, $chat_and_user_from_id, $adv_is_approved)) {

/*
            // ارسال به کانال
            $adv_text_for_channel = "{$adv_info['adv_text']}
                        
🆔 {$adv_info['adv_contact_info']}
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";

            $response = $telegram->sendMessage(createChannelBox(CHANNEL_ID, $adv_text_for_channel)); // send to channel - returns a json obj.
            setAdvertisementMessageId($adv_info['adv_id'], $response['result']['message_id']); // store message_id of inserted adv in the channel for assignment usage.
*/

            // // خذف باکس پرداخت شده در سمت کاربر
            $telegram->deleteMessage(['chat_id' => $chat_and_user_from_id, 'message_id' => $user_paid_box_message_id]);

            // محاسبه 10 درصد و پس دادن مابقی به کاربر آگهی دهنده
            //$give_back_amount = (int)ceil((ADV_PRICE / 1000) * 0.9);
            //increaseCoins($chat_and_user_from_id, $adv_price_coin);

            // ارسال باکس جدید به کاربر
            // resend updated box for User with buttons like (assigned or see in channel).
            //$new_adv_text_message = "متاسفانه در بررسی آگهی شما نقض قوانین کانال تشخیص داده شد و تایید نشد.همچنین 10 درصد مبلغ پرداختی بعنوان جریمه عدم رعایت یا عدم مطالعه قوانین کسر و مابقی بعنوان سکه به حساب شما واریز شد.";
            $new_adv_text_message = "متاسفانه در بررسی آگهی شما نقض قوانین کانال تشخیص داده شد و تایید نشد.";
            $new_adv_text = "\n" . "❗️" .$new_adv_text_message . "\n" . "🔸با احترام ، تیم فریلنسرلی🔸" . "\n";

            $myData = [
                //'adv_pre_text' => '',
                'adv_post_text' => $new_adv_text,
                'adv_id' => $adv_info['adv_id'],
                'adv_message_id' => $adv_info['adv_message_id'],
                'adv_text' => $adv_info['adv_text'],
                'contact_info' => $adv_info['adv_contact_info'],
                'adv_is_paid' => $adv_info['adv_is_paid'],
                'adv_is_approved' => $adv_is_approved,
                'adv_is_assigned' => $adv_info['adv_is_assigned'],
                'user_from_id' => $user_from_id,
                'chat_id' => $chat_id,
            ];
            $telegram->sendMessage(createMainAdvertisementBox($chat_and_user_from_id, $myData));


            // تغییر دکمه های باکس درخواست تایید
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("❌ رد شده", 'https://t.me/Freelancerly_bot');
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
            $telegram->editMessageReplyMarkup([
                'chat_id'=> $telegram->Callback_ChatID(),
                'message_id'=> $callback_query['message']['message_id'],
                'reply_markup'=> $inlineKeyboard
            ]);

            // پاسخ کالبک کوئری
            //$answer_text = ".رد شد";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => '', 'show_alert' => true]);
        }

    }

    // ویرایش و تایید
    elseif ($parts[0] == 'editReq'){

        $advId = $parts[1];
        $chat_and_user_from_id = $parts[2]; // in individual chats these values are the same.
        $user_paid_box_message_id = $parts[3]; // حذف باکس پرداخت شده در سمت کاربر که درهر صورت باید حذف شود
        //$adv_info = getAdvertisementData($advId, $chat_and_user_from_id);

        /*
        if (setIsApproved($advId, $chat_and_user_from_id, 1)) {


            // ارسال به کانال
            $adv_text_for_channel = "{$adv_info['adv_text']}

🆔 {$adv_info['adv_contact_info']}
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";

            $response = $telegram->sendMessage(createChannelBox(CHANNEL_ID, $adv_text_for_channel)); // send to channel - returns a json obj.
            setAdvertisementMessageId($adv_info['adv_id'], $response['result']['message_id']); // store message_id of inserted adv in the channel for assignment usage.


            // // خذف باکس پرداخت شده در سمت کاربر
            $telegram->deleteMessage(['chat_id' => $chat_and_user_from_id, 'message_id' => $user_paid_box_message_id]);


            // ارسال باکس جدید به کاربر
            // resend updated box for User with buttons like (assigned or see in channel).
            $myData = [
                'adv_pre_text' => '',
                'adv_id' => $adv_info['adv_id'],
                'adv_message_id' => $adv_info['adv_message_id'],
                'adv_text' => $adv_info['adv_text'],
                'contact_info' => $adv_info['adv_contact_info'],
                'adv_is_paid' => 1,
                'adv_is_approved' => 1,
                'adv_is_assigned' => $adv_info['adv_is_assigned'],
                'user_from_id' => $user_from_id,
                'chat_id' => $chat_id,
            ];
            $telegram->sendMessage(createMainAdvertisementBox($chat_and_user_from_id, $myData));


            // تغییر دکمه های باکس درخواست تایید
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("تایید شده", 'https://t.me/freelancerly/' . $response['result']['message_id']);
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
            $telegram->editMessageReplyMarkup([
                'chat_id'=> $telegram->Callback_ChatID(),
                'message_id'=> $callback_query['message']['message_id'],
                'reply_markup'=> $inlineKeyboard
            ]);

            // پاسخ کالبک کوئری
            //$answer_text = "تایید شد.";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => '', 'show_alert' => true]);
        }
        */
    }

    // حذف آگهی بدون بازپرداخت
    elseif ($parts[0] == 'deleteReq'){

        $advId = $parts[1];
        $chat_and_user_from_id = $parts[2]; // in individual chats these values are the same.
        $user_paid_box_message_id = $parts[3]; // حذف باکس پرداخت شده در سمت کاربر که درهر صورت باید حذف شود
        //$adv_info = getAdvertisementData($advId, $chat_and_user_from_id);

        /*
        if (setIsApproved($advId, $chat_and_user_from_id, -2)) {


            // ارسال به کانال
            $adv_text_for_channel = "{$adv_info['adv_text']}

🆔 {$adv_info['adv_contact_info']}
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";

            $response = $telegram->sendMessage(createChannelBox(CHANNEL_ID, $adv_text_for_channel)); // send to channel - returns a json obj.
            setAdvertisementMessageId($adv_info['adv_id'], $response['result']['message_id']); // store message_id of inserted adv in the channel for assignment usage.


            // // خذف باکس پرداخت شده در سمت کاربر
            $telegram->deleteMessage(['chat_id' => $chat_and_user_from_id, 'message_id' => $user_paid_box_message_id]);


            // ارسال باکس جدید به کاربر
            // resend updated box for User with buttons like (assigned or see in channel).
            $myData = [
                'adv_pre_text' => '',
                'adv_id' => $adv_info['adv_id'],
                'adv_message_id' => $adv_info['adv_message_id'],
                'adv_text' => $adv_info['adv_text'],
                'contact_info' => $adv_info['adv_contact_info'],
                'adv_is_paid' => 1,
                'adv_is_approved' => 1,
                'adv_is_assigned' => $adv_info['adv_is_assigned'],
                'user_from_id' => $user_from_id,
                'chat_id' => $chat_id,
            ];
            $telegram->sendMessage(createMainAdvertisementBox($chat_and_user_from_id, $myData));


            // تغییر دکمه های باکس درخواست تایید
            $inlineKeyboardButton1 = $telegram->buildInlineKeyboardButton("تایید شده", 'https://t.me/freelancerly/' . $response['result']['message_id']);
            $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton1]]);
            $telegram->editMessageReplyMarkup([
                'chat_id'=> $telegram->Callback_ChatID(),
                'message_id'=> $callback_query['message']['message_id'],
                'reply_markup'=> $inlineKeyboard
            ]);

            // پاسخ کالبک کوئری
            //$answer_text = "تایید شد.";
            $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => '', 'show_alert' => true]);
        }
        */
    }


    // todo : Answer Report Button -CallBackValue: ansReport_user-from-id
    // set step 333
    elseif ($parts[0] == 'ansReport') {

        $support_sender_user_id = $parts[1];
        addOrUpdateJson($file_path, 'support_sender_user_id', $support_sender_user_id);
        setUserStep($admin_id,333);

        // ReplyKeyboardMarkup
        $keyboard_Add = $telegram->buildKeyBoard(
            returnButton(),
            $onetime = true,
            $resize = true,
            $selective = true,
            $persistent = true,
            $placeholder = 'متن پاسخ'
        );


        $cnt_answer = [
            'chat_id' => $chat_id,
            'text' => "لطفا پاسخ خود را وارد کنید:",
            'reply_markup' => $keyboard_Add,
            'reply_to_message_id' => $callback_query['message']['message_id']
        ];
        $telegram->sendMessage($cnt_answer);
        $telegram->answerCallbackQuery(['callback_query_id' => $telegram->Callback_ID(), 'text' => '', 'show_alert' => true]);

    }
}







/*
 * Start Point
 */
if (isPrivateChat($last_msg_data)) {


    // کاربر جدید
    if (!userExists($user_from_id)) {
        $welcome_text = "به ربات ثبت آگهی فریلنسرلی خوش آمدید.";
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $welcome_text]);
        // add "username" and "user_numeric_id" to DB.
        insertUser($user_from_id, $username);
        $telegram->sendMessage();

        // لینک دعوت
        if (is_valid_invite_link($message_text) && isBotCommand($last_msg_data)) {
            $inviter_id = extractInvitationLinkParts($message_text);
            $invited_id = $user_from_id;
//            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $inviter_id]);

            // todo : add inviter and invited user_id to the DB.
            if (userExists($inviter_id)) { //اگر دعوت کننده واقعی بود
                increaseCoins($inviter_id,2); // افزایش سکه دعوت کننده
                insertInvite($inviter_id, $invited_id); // افزودن به دیتابیس
                // پیام به دعوت کننده
                $telegram->sendMessage(['chat_id' => $inviter_id, 'text' => "
                کاربر جدیدی با لینک دعوت شما وارد ربات شد و 2 سکه به حساب شما اضافه شد.
                با تشکر
                از طرف فریلنسرلی
                "]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "لینک دعوتی که با آن به ربات دعوت شده اید نامعتبر است."]);
            }

        }


        // جوین اجباری
        if (!isChatMember('@'.$freelancerlyChannelUsername, $user_from_id)){
            $force_join_text = "📣کاربر گرامی
جهت استفاده از این ربات و بازشدن قفل آن ، ابتدا در کانال زیر عضو شوید:

🆔 @{$freelancerlyChannelUsername} 🔔

پس از عضویت در کانال دستور /start را مجددا ارسال نمایید‼️
";

            // $inlineKeyboardButton6 = $telegram->buildInlineKeyboardButton("کانال آگهی", "https://t.me/freelancerly");
            //$inlineKeyboardButton7 = $telegram->buildInlineKeyboardButton("بررسی عضویت", null, "joined" );
            //$inlineKeyboard6 = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton7]]);
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $force_join_text,
                //'reply_markup' => $inlineKeyboard6,
                'disable_web_page_preview' => true,
                'allow_sending_without_reply'
            ]);

            die();
        }
        else {$telegram->sendMessage(displayMainMenuButtons($chat_id));}
    }

    // کاربر قدیمی
    else {

        // جوین اجباری
        if (!isChatMember('@'.$freelancerlyChannelUsername, $user_from_id)){
            $force_join_text = "📣کاربر گرامی
جهت استفاده از این ربات و بازشدن قفل آن ، ابتدا در کانال زیر عضو شوید:

🆔 @{$freelancerlyChannelUsername} 🔔

پس از عضویت در کانال دستور /start را مجددا ارسال نمایید‼️
";

            //$inlineKeyboardButton6 = $telegram->buildInlineKeyboardButton("کانال آگهی", "https://t.me/freelancerly");
            //$inlineKeyboardButton7 = $telegram->buildInlineKeyboardButton("بررسی عضویت", null, "joined" );
            //$inlineKeyboard6 = $telegram->buildInlineKeyBoard([[$inlineKeyboardButton7]]);
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $force_join_text,
                //'reply_markup' => $inlineKeyboard6,
                'disable_web_page_preview' => true,
                'allow_sending_without_reply'
            ]);

            die();
        }

        $current_step = getUserStep($user_from_id);

        // بازگشت step 0
        if (($message_text == 'بازگشت به منو ↪️') || ($message_text == '/start' && isBotCommand($last_msg_data))) {
            setUserStep($user_from_id, 0);
            $current_step = 0;
            $telegram->sendMessage(displayMainMenuButtons($chat_id));
        }
        // شروع - step 0
//        if ($message_text == '/start' && isBotCommand($last_msg_data)) {
//            setUserStep($user_from_id, 0);
//            $current_step = 0; // set to 0 without needing to read it from DB again.
//            $telegram->sendMessage(displayMainMenuButtons($chat_id));
//        }

        // step 1 - پذیرش قوانین
        if ($message_text == 'ثبت آگهی جدید 📝') {

            $unpaidAdv = countUnpaidAdvertisements($user_from_id);

            if ($unpaidAdv <= 3) {
                $new_ad = "
    🛑 حتما این متن رو بخونید

⚖️ راهنما و قوانین ثبت آگهی:
1️⃣ آگهی باید برای یک خواسته و نیازمندی باشد یعنی نمیتوانید چندین موضوع مختلف را در یک آگهی ثبت کنید!

2️⃣ در متن آگهی نباید از لینک و موارد تبلیغاتی استفاده کنید!

3️⃣ متن آگهی باید منطبق بر عرف و بدون هرگونه توهین باشد.

4️⃣ آگهی برای امتحان، پایان‌نامه و پرپوزال ممنوعه و تیم پشتیبانی این آگهی‌ها رو رد میکنه.

5️⃣ برای درج آگهی مهارت‌های خود یا آگهی استخدامی از طریق آی‌دی زیر اقدام کنید:
" . $SUPPORT_ID . "
6️⃣ قبل از هرگونه توافق و پرداخت هزینه برای پروژه، از گزینه پیشگیری از کلاهبرداری در منوی اصلی استفاده کنید و برای جلوگیری از کلاهبرداری احتمالی از پرداخت هزینه بدون واسطه کردن ادمین بپرهیزید.

🔴 در صورتی که در آگهی شما قوانین ذکر شده رعایت نشده باشد آگهی شما رد و فقط 90 درصد هزینه بصورت‌ سکه به شما برگردانده میشود.

🔴 همچنین اگر راهکار پرداختی شما غیر از موارد فوق باشد مجموعه فریلنسرلی هیچ مسئولیتی در قبال وجه پرداختی شما ندارد.
    ";

                // ReplyKeyboard BTNs
                $rep_KeyB_BTNs_Add = [
                    [
                        ['text' => "قوانین را مطالعه کردم و می‌پذیرم ✅"],
                    ],
                    [
                        ['text' => "بازگشت به منو ↪️"],
                    ],
                ];

                // ReplyKeyboardMarkup
                $replyKeyboard_Add = $telegram->buildKeyBoard(
                    $rep_KeyB_BTNs_Add,
                    $onetime = true,
                    $resize = true,
                    $selective = true,
                    $persistent = true,
                    $placeholder = '.پس از مطالعه قوانین دکمه پذیرش را انتخاب کنید'
                );

                // ReplyKeyboard content
                $cnt_Add = [
                    'chat_id' => $chat_id,
                    'text' => $new_ad,
                    'reply_markup' => $replyKeyboard_Add,
                    'reply_to_message_id' => $message_id
                ];
                $telegram->sendMessage($cnt_Add);

                setUserStep($user_from_id, 1);
            }
            else {
                $warn = "⚠️ شما چند آگهی پرداخت نشده دارید.
لطفا قبل از ثبت آگهی جدید ، آگهی های قبلی خود را پرداخت کنید یا درصورتی که مشکلی وجود دارد به ادمین پیام ارسال کنید.";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $warn]);
            }
        }

        // step 2 - درخواست متن آگهی
        if ($message_text == "قوانین را مطالعه کردم و می‌پذیرم ✅" && $current_step == 1) {


            // ReplyKeyboardMarkup
            $keyboard_Add = $telegram->buildKeyBoard(
                returnButton(),
                $onetime = true,
                $resize = true,
                $selective = true,
                $persistent = true,
                $placeholder = 'متن آگهی'
            );

            $send_adv_text = "خب حالا متن آگهیت رو برامون بنویس. 
مثال :
به فردی مسلط به ریاضی مهندسی، برای رفع اشکال نیازمندم.
            ";

            $cnt_Add = [
                'chat_id' => $chat_id,
                'text' => $send_adv_text,
                'reply_markup' => $keyboard_Add
                 // 'reply_to_message_id' => $message_id
            ];
            $telegram->sendMessage($cnt_Add);

            setUserStep($user_from_id, 2);
        }
        elseif ($message_text == "قوانین را مطالعه کردم و می‌پذیرم ✅" && $current_step != 1) {
            setUserStep($user_from_id, 0);
            $current_step = 0; // set to 0 without needing to read it from DB again.
            $again_text = '✔️ برای درج آگهی تو کانال، کافیه دکمه "ثبت آگهی جدید 📝" رو انتخاب کنی و قدم به قدم با راهنمایی ربات جلو بری.

✔️ برای آگهی استخدامی، واسطه کردن ما برای انجام کارهاتون، سفارش تبلیغات و کلا هر کاری بجز درج آگهی، میتونی از طریق آیدی زیر به تیم پشتیبانی ۲۴ ساعته‌ی ما پیام بدی: '.$Admin_Username.'


🔰آدرس کانال: 
@freelancerly';
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $again_text]);
            $telegram->sendMessage(displayMainMenuButtons($chat_id));
        }

        // step 3 - دریافت متن آگهی
        if ($current_step == 2) {

            $minLength = 10;
            $maxLength = 1024;
            // $messageLength = strlen($message_text);
            $messageLength = mb_strlen((string)$message_text, 'UTF-8');
            // multibyte character and encoding

            // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => strlen($message_text)]);
            // $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $messageLength]);

            if ($messageLength < $minLength) { // 10+ character

                // ReplyKeyboardMarkup
                $replyKeyboard_Add = $telegram->buildKeyBoard(
                    returnButton(),
                    $onetime = true,
                    $resize = true,
                    $selective = true,
                    $persistent = true,
                    $placeholder = 'متن آگهی'
                );

                // ReplyKeyboard content
                $cnt_Add = [
                    'chat_id' => $chat_id,
                    'text' => "این که خیلی کوتاهه 😕\nدوباره سعی کن:",
                    'reply_markup' => $replyKeyboard_Add,
                    'reply_to_message_id' => $message_id
                ];

                $telegram->sendMessage($cnt_Add);
            }
            elseif ($messageLength > $maxLength) {

                // ReplyKeyboardMarkup
                $replyKeyboard_Add = $telegram->buildKeyBoard(
                    returnButton(),
                    $onetime = true,
                    $resize = true,
                    $selective = true,
                    $persistent = true,
                    $placeholder = 'متن آگهی'
                );

                // ReplyKeyboard content
                $cnt_Add = [
                    'chat_id' => $chat_id,
                    'text' => "این که خیلی زیاده 😕 ، برای ثبت آگهی طولانی بهتره به ادمین پیام بدی.",
                    'reply_markup' => $replyKeyboard_Add,
                    'reply_to_message_id' => $message_id
                ];

                $telegram->sendMessage($cnt_Add);
            }
            elseif ($maxLength > $messageLength && $messageLength > $minLength) {


                // addOrUpdateJson($user_from_id, 'user_username_id', $username);
                // addOrUpdateJson($user_from_id, 'adv_user_numeric_id', $user_from_id);

                // ذخیره متن آگهی در فایل جیسون
                if (!file_exists($file_path)) {createJsonFile($file_path);}
                addOrUpdateJson($file_path, 'adv_text', $message_text);


                $replyKeyboard_Add = $telegram->buildKeyBoard(
                    returnButton(),
                    $onetime = true,
                    $resize = true,
                    $selective = true,
                    $persistent = true,
                    $placeholder = 'آی‌دی یا موبایل'
                );

                $contact_info = "حالا آی‌دی و یا شماره تماسی که باید پایین آگهیت درج بشه رو بفرست.
مثال:
@freelancerly_bot
یا
09⍰⍰⍰⍰⍰⍰⍰⍰⍰
";

                // ReplyKeyboard content
                $cnt_Add = [
                    'chat_id' => $chat_id,
                    'text' => $contact_info,
                    'reply_markup' => $replyKeyboard_Add
//                    'reply_to_message_id' => $message_id
                ];
                $telegram->sendMessage($cnt_Add);

                setUserStep($user_from_id, 3);
            }
        }

        // آی‌دی و یا شماره تماس آگهی
        if ($current_step == 3) {
            if (isValidId($message_text) || isValidPhone($message_text)) {

                // get adv text from json file
                $adv_text = getValueByKeyFromJson($file_path, 'adv_text');



                // add advertisement to the DB
                $data = [
                    'adv_user_numeric_id' => $user_from_id,
                    'adv_text' => $adv_text,
                    'adv_contact_info' => $message_text,
                    // 'adv_required_skills' => null,
                    'adv_creation_date' => date('Y-m-d H:i:s')
                ];
                $inserted_id = insertAdvertisement($admin_id, $data);

                if (!$inserted_id) {
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "مشکلی در ذخیره آگهی وجود دارد.لطفا به ادمین گزارش دهید."]);
                    die();
                }

//              $last_id = mysqli_insert_id($connection);


//                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $adv_text]);

                // created text and inline buttons
                $adv_pre_text = "✔️ آگهی شما ذخیره شد.\n";

                // because adv is saved right now it's payment status is false.
                $data1 = [
                    'adv_pre_text' => $adv_pre_text,
                    'adv_id' => $inserted_id,
                    'adv_text' => $adv_text,
                    'contact_info' => $message_text,
                    'adv_is_paid' => 0,
                    'adv_is_approved' => 0,
                    'adv_is_assigned' => 0,
                    'user_from_id' => $user_from_id,
                    'chat_id' => $chat_id,
                ];
                $saved_adv_content = createMainAdvertisementBox($chat_id, $data1);

                setUserStep($user_from_id, 0);
                $current_step = 0;
                // show box
                $telegram->sendMessage($saved_adv_content);

                // نمایش منو اصلی
                $telegram->sendMessage(displayMainMenuButtons($chat_id));

            }
            else {
                $invalid_contact_info = "آی‌دی و یا شماره تلفن تشخیص داده نشد!
لطفا مطابق مثال وارد کنید.
                                        
مثال:
@freelancerly_bot
یا
09⍰⍰⍰⍰⍰⍰⍰⍰⍰
";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $invalid_contact_info]);
            }
        }

        // ارسال پاسخ پشتیبانی به کاربر
        if ($current_step == 333) {
            setUserStep($user_from_id, 0);
            $support_sender_user_id = getValueByKeyFromJson($file_path, 'support_sender_user_id');

            //$message_text = $message_text;
            $support_text = "#پاسخ_پشتیبانی

{$message_text}";



            // ReplyKeyboard content
            $cnt_return = [
                'chat_id' => $support_sender_user_id,
                'text' => $support_text,
                //'reply_markup' => $replyKeyboard_Add,
                //'reply_to_message_id' => $message_id, // todo: add reply to for user message.
                //'allow_sending_without_reply' => true
            ];

            $result = $telegram->sendMessage($cnt_return);
            if ($result['ok']) {
                $telegram->sendMessage(['chat_id' => $admin_id, 'text' => 'پاسخ ارسال شد.', 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $admin_id, 'text' => 'متاسفانه پاسخ ارسال نشد.', 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
            }
            setUserStep($user_from_id, 0);
            $telegram->sendMessage(displayMainMenuButtons($chat_id));
        }

        // مشاهده تعداد و آگهی ها ثبت شده من
        if ($message_text == 'آگهی‌های ثبت شده 🗄') {
            // shows adv without buttons.
            //todo : optimize function
            showMyAdvertisementsList($chat_id, $user_from_id);
        }

        // /id8374 - show adv box by id
        if (isValidAdvId($message_text, '\/id') && $current_step == 0) {

            // here $message_text is a string like this : /id12345 that 12345 is a real adv id
            $advId = extractId($message_text, '/id');
            $adv_info = getAdvertisementData($advId, $user_from_id);
            if ($adv_info) {

                $myData = [
                    //'adv_pre_text' => '',
                    'adv_id' => $adv_info['adv_id'],
                    'adv_message_id' => $adv_info['adv_message_id'],
                    'adv_text' => $adv_info['adv_text'],
                    'contact_info' => $adv_info['adv_contact_info'],
                    'adv_is_paid' => $adv_info['adv_is_paid'],
                    'adv_is_approved' => $adv_info['adv_is_approved'],
                    'adv_is_assigned' => $adv_info['adv_is_assigned'],
                    'user_from_id' => $user_from_id,
                    'chat_id' => $chat_id,
                ];
                $adv_box = createMainAdvertisementBox($chat_id, $myData);
                $telegram->sendMessage($adv_box);
            }
        }

        // درخواست پیام پشتیبانی
        if ($message_text == 'پشتیبانی 💬') {

            // ReplyKeyboardMarkup
            $replyKeyboard_Support = $telegram->buildKeyBoard(
                returnButton(),
                $onetime = true,
                $resize = true,
                $selective = true,
                $persistent = true,
                $placeholder = 'پشتیبانی'
            );

            // ReplyKeyboard content
            $cnt_Support = [
                'chat_id' => $chat_id,
                'text' => 'متن خود را در قالب یک پیام ارسال کنید.',
                'reply_markup' => $replyKeyboard_Support,
                'reply_to_message_id' => $message_id
            ];
            $telegram->sendMessage($cnt_Support);

            setUserStep($user_from_id, 7);
        }

        // step 7 - پردازش پیام پشتیبانی
        if ($current_step == 7) {

            $messageLength = mb_strlen((string)$message_text, 'UTF-8');

            if ($messageLength < 10) { // 10+ character

                // ReplyKeyboardMarkup
                $replyKeyboard_Add = $telegram->buildKeyBoard(
                    returnButton(),
                    $onetime = true,
                    $resize = true,
                    $selective = true,
                    $persistent = true,
                    $placeholder = 'پشتیبانی'
                );

                // ReplyKeyboard content
                $cnt_Add = [
                    'chat_id' => $chat_id,
                    'text' => "این که خیلی کوتاهه 😕\nدوباره سعی کن:",
                    'reply_markup' => $replyKeyboard_Add,
                    'reply_to_message_id' => $message_id
                ];

                $telegram->sendMessage($cnt_Add);
            }
            else {

                $ticket_text = "✔️ پیام شما ثبت شد.
در صورت لزوم، تیم پشتیبانی پاسخ را از طریق همین بات به شما اعلام خواهد کرد.
";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $ticket_text, 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);

                if (empty($username)){$username = 'ندارد';}

                // send to admin
                $report_text = "#درخواست_پشتیبانی
                
● نام کاربری : @$username
● آیدی عددی : $user_from_id
● متن :     
" . $message_text . "
";

                $inlineButton3 = $telegram->buildInlineKeyboardButton("پاسخ", null, "ansReport_" . $user_from_id);
                $inlineKeyboard3 = $telegram->buildInlineKeyBoard([[$inlineButton3]]);

                $telegram->sendMessage([
                    'chat_id' => $admin_id,
                    'text' => $report_text,
                    'reply_markup' => $inlineKeyboard3,
                    'disable_web_page_preview' => true,
                    'allow_sending_without_reply' => true
                ]);

                setUserStep($user_from_id, 0);
                $current_step = 0; // set to 0 without needing to read it from DB again.
                $telegram->sendMessage(displayMainMenuButtons($chat_id));
            }
        }

        if ($message_text == 'سکه رایگان 🌕') {

            // ReplyKeyboard content
            $cnt_Coin1 = [
                'chat_id' => $chat_id,
                'text' => 'تعداد سکه‌های شما : ' . getCoins($user_from_id)
            ];
            $cnt_Coin2 = [
                'chat_id' => $chat_id,
                'text' => 'پیام پایینی رو برای دوستات بفرست تا با لینک اختصاصی تو وارد ربات بشن و به ازای هر نفر ' . $coins_foreach_invite . ' سکه بگیری! بعد از جمع کردن ' . $adv_price_coin . ' تا سکه میتونی یه آگهی رایگان بزنی!
• یادت باشه طرف نباید قبلا با ربات کارکرده باشه!

پیامی که باید کپی کنی ⬇️
'];

//            $cnt_Coin3 = [
//                'chat_id' => $chat_id,
//                'text' => 'پیامی که باید کپی کنی ⬇️'
//            ];
            $cnt_Coin4 = [
                'chat_id' => $chat_id,
                'text' => 'سلام 👋
اگر دنبال واگذاری پروژه‌های درسی و کاریت به دیگران هستی یا اینکه میخوای از طریق کارای پروژه ای کسب درآمد کنی کانال فریلنسرلی رو دنبال کن: 

 t.me/freelancerly_bot?start=' . $user_from_id
            ];


            // $telegram->sendChatAction()
            $telegram->sendMessage($cnt_Coin1);
            $telegram->sendMessage($cnt_Coin2);
//            $telegram->sendMessage($cnt_Coin3);
            $telegram->sendMessage($cnt_Coin4);

        }
        if ($message_text == 'واسطه کردن ادمین 🤝') {

            $cnt_Intermediary1 = [
                'chat_id' => $chat_id,
                'text' => "🟢 توضیح واسطه شدن ادمین:

1⃣ با انجام دهنده پروژه روی قیمت مشخص توافق میکنید.

2⃣ هیچ مبلغی به انجام دهنده واریز نمی‌کنید و با ما با آیدی {$Admin_Username} هماهنگ میکنید و مبلغ توافق شده و اطلاعات تماس انجام دهنده را برای ما ارسال میکنید.

3⃣ مبلغ توافق شده به همراه هزینه پشتیبانی به حساب ادمین  کانال واریز میکنید.

4⃣ بعد از اتمام پروژه و تایید پروژه به ادمین اطلاع میدید و ادمین پول را به حساب انجام دهنده واریز می‌کند.

🔻نحوه واسطه شدن ما:
در ابتدا پول توسط فرد پروژه‌دهنده به طور کامل برای ما واریز میشه بعد از انجام کار و تایید فرد پروژه دهنده، پول به صورت کامل به فرد انجام دهنده واریز میشه.


❗️❗️توافق برای دریافتی ما از واسطه شدن هم به عهده طرفین هست، می‌تونین نصف نصف بدین یا اینکه کلش رو فردی که می‌خواد ما رو واسطه کنه پرداخت کنه.

آیدی جهت هماهنگی 👈 {$Admin_Username}
"];
            $cnt_Intermediary_Cost = [
                'chat_id' => $chat_id,
                'text' => "🔴 هزینه پشتیبانی:

1️⃣ برای مبالغ کمتر 200 هزار تومان 12 هزار تومان 

2️⃣ برای مبالغ بالای 200 هزار تومان 18 هزار تومان 

3️⃣ برای مبالغ بالای 500 هزار تومان 22 هزار تومان 

4️⃣ برای مبالغ بالای 1 میلیون تومان 35 هزار تومان 


🛑 این مبلغ توافقی هست و ممکنه انجام دهنده یا کارفرما پرداخت کنه و یا نصف بشه بین هر دو.

🔴 مبلغ توافق شده تا حداکثر ۲۴ ساعت کاری بعد از تایید کارفرما برای انجام دهنده واریز میشه.


آیدی جهت هماهنگی 👈 {$Admin_Username}
"];

            // $telegram->sendChatAction()
            $telegram->sendMessage($cnt_Intermediary1);
            $telegram->sendMessage($cnt_Intermediary_Cost);

        }
        if ($message_text == 'واگذاری پروژه به تیم ما') {

            $cnt_Assigning = [
                'chat_id' => $chat_id,
                'text' => "برای واگذاری پروژه خود به تیم کانال فریلنسرلی به آیدی زیر پیام بدید و موضوع پروژه خود را بیان کنید:
{$Admin_Username}

در صورت توانایی انجام پروژه توسط تیم فریلنسرلی وارد روند انجام پروژه میشیم و در غیر این صورت اطلاع میدیم که در کانال آگهی خود رو ثبت کنید.
"];

            $telegram->sendMessage($cnt_Assigning);
        }


        if ($message_text == 'پیشگیری از کلاهبرداری') {
            $cnt = [
                'chat_id' => $chat_id,
                'text' => "این بخش بزودی راه اندازی خواهد شد."
            ];

            $telegram->sendMessage($cnt);
        }

        if ($message_text == 'انجام تبلیغات') {
            $cnt = [
                'chat_id' => $chat_id,
                'text' => "این بخش بزودی راه اندازی خواهد شد."
            ];

            $telegram->sendMessage($cnt);
        }











        // todo: complete admin panel.
        // *************
        // *** ADMIN ***
        // *************

        if ($message_text == "/admin" and isBotCommand($last_msg_data) and $is_admin) {
            setUserStep($user_from_id, 89);
            $telegram->sendMessage(displayAdminMenu($chat_id));
        }

        if ($message_text == "حالت فوروارد اختصاصی" and $is_admin) {
            setUserStep($user_from_id, 90);
            $telegram->sendMessage(displayFilterButtons($chat_id));
        }

        // vars
        //$file_location = 'bot_settings/'.$admin_id.'.json';
        $file_location = __DIR__ . '/bot_settings/'.$admin_id.'.json';
        $msg_parts = split_string($message_text);
        if ($message_text == "شروع فوروارد" && $is_admin) {

            setUserStep($user_from_id, 91);
            // ReplyKeyboardMarkup
            $replyKeyboard_Add = $telegram->buildKeyBoard(doubleReturnButton(), $onetime = true, $resize = true,
                $selective = true, $persistent = true, $placeholder = 'حالت فوروارد');
            // ReplyKeyboard content
            $cnt_Forward = [
                'chat_id' => $chat_id,
                'text' => "لطفا پیام ها را فوروارد کنید:
کانال های پشتیبانی شده :
@prozhe_pazhoh , @freelancer_job , @project_board",
                'reply_markup' => $replyKeyboard_Add,
                'reply_to_message_id' => $message_id
            ];
            $telegram->sendMessage($cnt_Forward);

        }
        if ($current_step == 91 and $message_text != "/admin" and $is_admin) {

            // Check if the 133084833.json filters file exists
            if (!file_exists($file_location)) {
                $data = [
                    "filters" => [
                        // "name" => "John Doe",
                    ]
                ];
                createJsonFile2($file_location, $data);
            }

            if (!isForwardedMessage($last_msg_data)){$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "این پیام فوروارد شده نیست.", 'reply_to_message_id' => $message_id]);}
            // Forward to Channel
            elseif (hasText($last_msg_data)) {

                $adv_text = "";
                $adv_contact_info = "";

                /*
                if (hasInlineKeyboard($last_msg_data)) {
                    $reply_markup = $telegram->buildInlineKeyBoard($last_msg_data['message']['reply_markup']['inline_keyboard']);
                } else {$reply_markup = '';}
                */
                /*
                $inlineButton = $telegram->buildInlineKeyboardButton("برای درج آگهیت کلیک کن", 'https://t.me/Freelancerly_bot');
                $inlineKeyboard = $telegram->buildInlineKeyBoard([[$inlineButton]]);
                $content = [
                    'chat_id' => CHANNEL_ID,
                    'text' => replaceByFilters($file_location, $last_msg_data['message']['text']),
                    'entities' => $last_msg_data['message']['entities'],
                    'reply_markup' => $inlineKeyboard
                ];
                $reply4 = $telegram->sendMessage($content);
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => isOk($reply4) . "\n" . 'Type : text', 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
                */

                // پروژه پزوه
                if ($last_msg_data['message']['forward_from_chat']['username'] == 'prozhe_pazhoh') {

                    $result = separateForwardedAdv($message_text);
                    if ($result) {
                        $adv_text = $result[0];
                        $adv_contact_info = $result[1];
                    }
                    else {
                        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "prozhe_pazhoh - Invalid input string format", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
                        die();
                    }
                }
                elseif ($last_msg_data['message']['forward_from_chat']['username'] == 'freelancer_job' ||
                    $last_msg_data['message']['forward_from_chat']['username'] == 'project_board')
                {

                    $result = separateForwardedAdv3($message_text);

                    if ($result) {
                        $adv_text = $result[0];
                        $adv_contact_info = $result[1];
                    } else {
                        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "freelancer_job - Invalid input string format", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
                        die();
                    }
                }
                else {
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه فوروارد از این کانال پشتیبانی نمیشود.", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);
                }


                //$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "adv_text:\n$adv_text\nadv_contact_info:\n$adv_contact_info", 'reply_to_message_id' => $message_id, 'allow_sending_without_reply' => true]);

                // add adv to DB
                $data = [
                    'adv_user_numeric_id' => $user_from_id,
                    'adv_text' => $adv_text,
                    'adv_contact_info' => $adv_contact_info,
                    // 'adv_required_skills' => null,
                    'adv_creation_date' => date('Y-m-d H:i:s')
                ];
                $inserted_id = insertAdvertisement($admin_id, $data);
                if (!$inserted_id) {
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "مشکلی در ذخیره آگهی وجود دارد.لطفا به ادمین گزارش دهید."]);
                    die();
                }

                // ارسال به کانال
                $adv_text_for_channel = "{$adv_text}
                        
🆔 {$adv_contact_info}
- - - - - - - - - - - - - -
@{$freelancerlyChannelUsername}";

                $response = $telegram->sendMessage(createChannelBox(CHANNEL_ID, $adv_text_for_channel)); // send to channel - returns a json obj.
                setAdvertisementMessageId($inserted_id, $response['result']['message_id']); // store message_id of inserted adv in the channel

                setIsPaid($inserted_id, $admin_id,1);
                setIsApproved($inserted_id, $admin_id, 1);

                // سمت کاربر
                // created text and inline buttons
                $adv_pre_text = "✔️ آگهی شما ذخیره شد.\n";
                $data1 = [
                    'adv_pre_text' => $adv_pre_text,
                    'adv_id' => $inserted_id,
                    'adv_text' => $adv_text,
                    'contact_info' => $adv_contact_info,
                    'adv_is_paid' => 1,
                    'adv_is_approved' => 1,
                    'adv_is_assigned' => 0,
                    'user_from_id' => $user_from_id,
                    'chat_id' => $chat_id,
                ];
                $saved_adv_content = createMainAdvertisementBox($chat_id, $data1);
                $telegram->sendMessage($saved_adv_content);

                // نمایش منو اصلی
                //$telegram->sendMessage(displayMainMenuButtons($chat_id));

            }
        }


        elseif ($message_text == "افزودن فیلتر"  && $is_admin) {
            setUserStep($user_from_id, 95);
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'کلمه ، آیدی یا قسمتی که میخواهید جایگزین یا حذف شود را وارد کنید :']);
        }
        // Get Key
        elseif ($current_step == 95  && $is_admin) {
            setUserStep($user_from_id, 96);
            addOrUpdateJsonFilter($file_location, $message_text,'temp');

            $filterAction = "حالا نوع اقدامی که باید انجام شود را وارد کنید :
برای حذف ، کلمه '/حذف' را ارسال کنید.
برای جایگزین کردن ، کلمه یا آیدی جدید که باید جاگزین شود را وارد کنید.";

            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $filterAction]);
        }
        // Get Value
        elseif ($current_step == 96  && $is_admin) {
            setUserStep($user_from_id, 90);
            searchAndUpdateFilterValue($file_location, 'temp', $message_text);
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'فیلتر جدید با موفقیت اضافه شد.']);
            $telegram->sendMessage(displayFilterButtons($chat_id));
        }

        elseif ($message_text == "مشاهده/حذف تکی فیلترها"  && $is_admin) {

            $all_filters = readJsonFilters($file_location);
            $count = $all_filters["count"];
            $key_value_pairs = $all_filters["key_value_pairs"];

            if ($count != 0) {
                $key_value_pairs = "فیلترهای فعلی :" . "\n\n" . $key_value_pairs;
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $key_value_pairs]);

                $delete_filter_text = "برای حذف فیلتر باید کلمه _del و سپس شماره فیلتر را بعد از آن بنویسید.
مثال حذف فیلتر شماره 3 :
del_3";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $delete_filter_text]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "هیچ فیلتری تعریف نشده."]);
            }


        }
        elseif ($msg_parts[0] == "del"  && $is_admin) {
            if (deleteFromFilters($file_location, $msg_parts[1])) {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "فیلتر شماره {$msg_parts[1]} قبلی با موفقیت حذف شد."]);

                $all_filters = readJsonFilters($file_location);
                $count = $all_filters["count"];
                $key_value_pairs = $all_filters["key_value_pairs"];

                if ($count == 0){
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "هیچ فیلتری وجود ندارد."]);
                }
                else {
                    $key_value_pairs = "فیلترهای فعلی :" . "\n\n" . $key_value_pairs;
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $key_value_pairs]);
                }

            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه فیلتر شماره {$msg_parts[1]} حذف نشد یا وجود ندارد."]);
            }
        }
        elseif ($message_text == "حذف همه فیلترها"  && $is_admin) {
            // todo : show a confirm
            deleteAllFilters($file_location);
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "همه فیلتر ها با موفقیت حذف شدند."]);
        }


        // step 99 -> 102
        elseif ($message_text == "تغییر سکه های کاربر"  and $is_admin) {
            setUserStep($user_from_id, 99);
            $telegram->sendMessage(displayEditUserCoinsMenu($chat_id));
        }
        elseif ($message_text == "افزایش سکه"  and $is_admin) {
            setUserStep($user_from_id, 100);
            $replyKeyboard_100 = $telegram->buildKeyBoard(doubleReturnButton(), $onetime = true, $resize = true, $selective = true, $persistent = true, $placeholder = '');
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "لطفا آیدی عددی کاربر و مقدار سکه را بصورت 10_133084833 وارد کنید:",
                'reply_to_message_id' => $message_id,
                'reply_markup' => $replyKeyboard_100
            ]);
        }
        elseif ($message_text == "کاهش سکه"  and $is_admin) {
            setUserStep($user_from_id, 101);
            $replyKeyboard_100 = $telegram->buildKeyBoard(doubleReturnButton(), $onetime = true, $resize = true, $selective = true, $persistent = true, $placeholder = '');
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "لطفا آیدی عددی کاربر و مقدار سکه را بصورت 10_133084833 وارد کنید:",
                'reply_to_message_id' => $message_id,
                'reply_markup' => $replyKeyboard_100
            ]);
        }
        elseif ($message_text == "تعیین مقدار دقیق سکه"  and $is_admin) {
            setUserStep($user_from_id, 102);
            $replyKeyboard_100 = $telegram->buildKeyBoard(doubleReturnButton(), $onetime = true, $resize = true, $selective = true, $persistent = true, $placeholder = '');
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "لطفا آیدی عددی کاربر و مقدار سکه را بصورت 10_133084833 وارد کنید:",
                'reply_to_message_id' => $message_id,
                'reply_markup' => $replyKeyboard_100
            ]);
        }
        elseif ($current_step == 100  && $is_admin) {
            $parts = split_string($message_text);
            if (increaseCoins($parts[0], $parts[1])) {
                $after_user_coins = getCoins($parts[0]);
                $message_response = "افزایش سکه کاربر {$parts[0]} با موفقیت انجام شد.
تعداد سکه فعلی کاربر : $after_user_coins";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $message_response, 'reply_to_message_id' => $message_id]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه افزایش سکه کاربر انجام نشد.", 'reply_to_message_id' => $message_id]);
            }
            setUserStep($user_from_id, 99);
            $telegram->sendMessage(displayEditUserCoinsMenu($chat_id));
        }
        elseif ($current_step == 101  && $is_admin) {
            $parts = split_string($message_text);
            if (subtractCoins($parts[0], $parts[1])) {
                $after_user_coins = getCoins($parts[0]);
                $message_response = "کاهش سکه کاربر {$parts[0]} با موفقیت انجام شد.
تعداد سکه فعلی کاربر : $after_user_coins";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $message_response, 'reply_to_message_id' => $message_id]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه کاهش سکه کاربر انجام نشد.", 'reply_to_message_id' => $message_id]);
            }
            setUserStep($user_from_id, 99);
            $telegram->sendMessage(displayEditUserCoinsMenu($chat_id));
        }
        elseif ($current_step == 102  && $is_admin) {
            $parts = split_string($message_text);
            if (setCoins($parts[0], $parts[1])) {
                $after_user_coins = getCoins($parts[0]);
                $message_response = "تغییر سکه کاربر {$parts[0]} با موفقیت انجام شد.
تعداد سکه فعلی کاربر : $after_user_coins";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $message_response, 'reply_to_message_id' => $message_id]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه تغییر سکه کاربر انجام نشد.", 'reply_to_message_id' => $message_id]);
            }
            setUserStep($user_from_id, 99);
            $telegram->sendMessage(displayEditUserCoinsMenu($chat_id));
        }


        // step 105
        elseif ($message_text == "تغییر هزینه ثبت آگهی" and $is_admin) {
            setUserStep($user_from_id, 105);
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "لطفا هزینه جدید هر آگهی را به تومان وارد کنید:",
                'reply_to_message_id' => $message_id
            ]);

        }
        elseif ($current_step == 105 and $is_admin) {
            $file_location = __DIR__ . '/bot_settings/' . $admin_id . '.json';
            if (addOrUpdateJson($file_location, 'ADV_PRICE', (int)$message_text)) {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "هزینه هر آگهی با موفقیت به {$message_text} تومان تغییر کرد.", 'reply_to_message_id' => $message_id]);
            }
            else {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "متاسفانه مشکلی در تغییر هزینه هر آگهی پیش آمده.", 'reply_to_message_id' => $message_id]);
            }
            setUserStep($user_from_id, 89);
            //$telegram->sendMessage(displayAdminMenu($chat_id));
        }

    }

} // END























/*
if (is_admin($user_from_id, 'admins.json')) {
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'سلام مدیر!',]);
} else {
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'فقط مدیران میتوانند از ربات استفاده کنند.',]);
}
*/


/*
$reply = 'Callback value '.$telegram->Callback_Data();
$content = ['chat_id' => $telegram->Callback_ChatID(), 'text' => $reply];
$telegram->sendMessage($content);
$content = ['callback_query_id' => $telegram->Callback_ID(), 'text' => $reply, 'show_alert' => true];
$telegram->answerCallbackQuery($content);
*/

/*
strpos($input['message']['text'], '/') === 0)
$telegram->sendMessage(['chat_id' => CHANNEL_ID, 'text' => 'entered values:']);
*/

// send forwarded photo to the channel.
//    if (isset($last_msg_data['message']['forward_from_chat']) && isset($last_msg_data['message']['photo'])) {
//
//        $caption = $last_msg_data['message']['caption'] ?? '';
//
//        $photo_content = [
//            'chat_id' => CHANNEL_ID,
//            'photo' => $last_msg_data['message']['photo'][0]['file_id'],
//            'caption' => $caption,
//            'has_spoiler' => true,
//        ];
//        $telegram->sendPhoto($photo_content);
//    }



//if ($message_text == 'st0000art') {
//
//
//    // ReplyKeyboard BTNs
//    $replyKeyboardButtons = [
//        [
//            ['text' => "1"],
//        ],
//        [
//            ['text' => "2"], ['text' => "3"],
//        ],
//        [
//            ['text' => "4"],
//        ],
//        [
//            ['text' => 'ارسال شماره تلفن', 'request_contact' => true], ['text' => 'ارسال لوکیشن', 'request_location' => true],
//        ],
//    ];
//    // ReplyKeyboardMarkup
//    $replyKeyboard = $telegram->buildKeyBoard($replyKeyboardButtons, $onetime = true, $resize = true, $selective = true, $persistent = true, $placeholder = 'hello');
//    // ReplyKeyboard content
//    $content1 = [
//        'chat_id' => $chat_id,
//        'text' => '<u>لطفا یکی از گزینه های زیر را انتخاب نمایید :</u>' . "\n",
//        'parse_mode' => "HTML",
//        'reply_markup' => $replyKeyboard,
//        'reply_to_message_id' => $message_id
//    ];
//
//
//    // InlineKeyboard BTNs
//    $inlineKeyboardButtons = [
//        [
//            ['text' => 'Option 1', 'callback_data' => 'option1'], ['text' => 'Option 2', 'callback_data' => 'option2'],
//        ],
//        [
//            ['text' => 'Option 3', 'callback_data' => 'option3'],
//        ],
//        [
//            ['text' => 'Option 4', 'callback_data' => 'option4'],
//        ]
//    ];
//    // InlineKeyboardMarkup
//    $inlineKeyboard = $telegram->buildInlineKeyBoard($inlineKeyboardButtons);
//    // InlineKeyboard content
//    $content2 = [
//        'chat_id' => $chat_id,
//        'text' => '<tg-spoiler>لطفا یکی از گزینه های زیر را انتخاب نمایید :</tg-spoiler>' . "\n",
//        'parse_mode' => "HTML",
//        'reply_markup' => $inlineKeyboard,
////        'reply_to_message_id' => $message_id
//    ];
//
//
//    // send a message and KeyboardButton
//    $telegram->sendMessage($content1);
//
//    try {
//        $telegram->sendMessage($content2);
//    } catch (Exception $e) {
//        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'Exception : ' . $e . '\n inline keyboard not worked']);
//    }
//
//
//    show_all_data();
//}

/*
if (!empty($message_text) and $message_text != '/start') {
 show_all_data();
}
*/

/*
if (isset($_GET['start'])) {
    $inviter_id = substr($_GET['start'], strpos($_GET['start'], '=') + 1);
    $invited_id = $telegram->ChatID();

    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $inviter_id . "\n" . $invited_id . "\n" . $_GET['start']]);

    // Check if the user has not already been rewarded for this invitation
    if (!is_rewarded($inviter_id, $invited_id)) {
        // Reward the inviter
        reward_user($inviter_id);
        // Mark the invited user as rewarded for this invitation
        mark_rewarded($inviter_id, $invited_id);
        // Send a message to the inviter to notify them of the reward
        $message = "Congratulations! You have earned a reward for inviting a new user to our bot.";
        $telegram->sendMessage(['chat_id' => $inviter_id, 'text' => $message]);
    }

}
*/

//function searchKeysInMultiDimensionalArray($array, $key): array
//{
//    $results = array();
//    if (is_array($array)) {
//        $resultArray = array_intersect_key($array, array_flip($key));
//        if (!empty($resultArray)) {
//            $results[] = $resultArray;
//        }
//
//        foreach ($array as $subarray) {
//            $results = array_merge($results, searchKeysInMultiDimensionalArray($subarray, $key));
//        }
//    }
//    return $results;
//} // searchKeysInMultiDimensionalArray

/*
$telegram->answerInlineQuery([
    'inline_query_id' => $message_id,
    'results' => urlencode('[{"type":"article","id":"1","title":"Select an option","input_message_content":{"message_text":"Please select an option"},"reply_markup":' . $inlineKeyboard . '}]')
]);
*/



/*

if ($text == '/startt') {

    $info = "
            Message : $text\n
            Message Type : $messageType\n
            UserName : $username\n
            FirstName : $name\n
            LastName : $lastname\n
            Message Id : $message_id\n
            User Id : $user_id\n
            Chat Id : $chat_id\n
            Date : " . date("Y-m-d H:i:s") . "\n\n
            ";

    $bot_options = "           
            گزینه های ربات :
            /start - شروع
            /help - راهنما
            /about - درباره ما
            /contact - تماس با ما
            /support - پشتیبانی
            /faq - پرسش های متداول
            /donate - کمک به تیم
            /share - به اشتراک گذاری
            /rate - امتیاز دهی
            /report - گزارش
            ";


    $telegram->sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']); // bot is typing...
    sleep(1);

    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $info]);
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $bot_options]);


}


if ($text == '/help') {

    // force join
    $from_id = $last_msg_data_array['message']['from']['id'];
    $channel_id = "@unityengine360";
    $join_txt = "
                    📌 جهت استفاده از ربات ابتدا باید در کانال ما عضو شوید
                    @unityengine360
                    • پس از عضو شدن در کانال ربات را مجددا /start کنید تا ربات برای شما فعال شود";

    // chat member
    $is_member = $telegram->getChatMember(['chat_id' => $channel_id, 'user_id' => $from_id]);
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => json_encode($is_member, JSON_PRETTY_PRINT)]);

    // chat admin
    $chat_admin = $telegram->getChatAdministrators(['chat_id' => $channel_id]);
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => json_encode($chat_admin, JSON_PRETTY_PRINT)]);


    $keyboardButtons = [
        [
            ['text' => 'ارسال شماره تلفن', 'request_contact' => true],
            ['text' => 'ارسال لوکیشن', 'request_location' => true]
        ]
    ];
    $keyBoard = $telegram->buildKeyBoard($keyboardButtons, $onetime = false, $resize = true, $selective = true);

    // message
    $answerText = 'لطفا گزینه های زیر را انتخاب کنید :' . "\n";
    $content = ['chat_id' => $chat_id, 'text' => $answerText, 'reply_markup' => $keyBoard, 'reply_to_message_id' => $message_id];
    $telegram->sendMessage($content);

    // send message and its type
    $txt = "Message : " . $text . "\n" . "Type : " . $messageType;
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $txt]);
}
*/

/*
// save user phone number to db
if ($messageType == 'contact'){
    $extracted_phone = $last_msg_data_array['message']['contact']['phone_number'];


    // insert extracted phone number to user table where id column is equal to 1
    $query = "UPDATE user SET phone_number = '$extracted_phone' WHERE id = 1";

    // check if extracted phone number is already in db
    $query_check = "SELECT * FROM user WHERE phone_number = '$extracted_phone'";

    // insert
    $result = mysqli_query($connection, $query);
    if (!$result) {
        $resultText = "Error : ".mysqli_error($connection);
    }
    else {
        $resultText = "شماره تلفن شما با موفقیت ثبت شد.";
    }
    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $resultText]);

}
*/

/*

INSERT INTO `user` (`id`, `user_id`, `username`, `first_name`, `last_name`, `is_blocked`, `is_bot`, `phone_number`, `location`, `user_email`) VALUES (NULL, '13546456', '@hamid', 'hamid', 'reza', '0', '0', '989373338', NULL, 'hreza389@gmail.com');

$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "getChat()\n".json_encode($telegram->getChat(['chat_id'=>$chat_id]), JSON_PRETTY_PRINT)]);
$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "getUpdates()\n".json_encode($telegram->getUpdates(), JSON_PRETTY_PRINT)]);
$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "getMe()\n".json_encode($telegram->getMe(), JSON_PRETTY_PRINT)]);
$telegram->sendMessage(['chat_id' => $chat_id, 'text' => "getData()\n".json_encode($last_msg_data_array, JSON_PRETTY_PRINT)]);

$member_count = $telegram->getChatMembersCount(['chat_id'=>'@Sobhan_adv']);
$telegram->sendMessage(['chat_id'=>$chat_id, 'text'=>json_encode($member_count,JSON_PRETTY_PRINT)]);

if ($text == "/start') {
    $option = [["\xF0\x9F\x90\xAE"], ['Git', 'Credit']];
    // Create a permanent custom keyboard
    $keyb = $telegram->buildKeyBoard($option, $onetime = false);
    $content = ['chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "Welcome to CowBot \xF0\x9F\x90\xAE \nPlease type /cowsay or click the Cow button !"];
    $telegram->sendMessage($content);
}

if ($text == '/cowsay' || $text == "\xF0\x9F\x90\xAE") {
    $randstring = rand().sha1(time());
    $cowurl = 'http://bangame.altervista.org/cowsay/fortune_image_w.php?preview='.$randstring;
    $content = ['chat_id' => $chat_id, 'text' => $cowurl];
    $telegram->sendMessage($content);
}

if ($text == '/credit' || $text == 'Credit') {
    $reply = "Eleirbag89 Telegram PHP API http://telegrambot.ienadeprex.com \nFrancesco Laurita (for the cowsay script) http://francesco-laurita.info/wordpress/fortune-cowsay-on-php-5";
    $content = ['chat_id' => $chat_id, 'text' => $reply];
    $telegram->sendMessage($content);
}

if ($text == '/git' || $text == 'Git') {
    $reply = 'Check me on GitHub: https://github.com/Eleirbag89/TelegramBotPHP';
    $content = ['chat_id' => $chat_id, 'text' => $reply];
    $telegram->sendMessage($content);
}

// send contact
$telegram->sendContact(['chat_id' => $chat_id, 'phone_number' => '09122222222', 'first_name' => 'test']);
*/





