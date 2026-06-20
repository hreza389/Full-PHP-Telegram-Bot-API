<?php

/**
 * Telegram Types Collection
 * 
 * A collection of classes representing common Telegram Bot API objects.
 */

/**
 * Represents a Telegram User
 */
class TelegramUser
{
    public int $id;
    public bool $isBot;
    public string $firstName;
    public ?string $lastName;
    public ?string $username;
    public ?string $languageCode;
    public ?bool $isPremium;
    public ?bool $addedToAttachmentMenu;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->isBot = $data['is_bot'] ?? false;
        $this->firstName = $data['first_name'];
        $this->lastName = $data['last_name'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->languageCode = $data['language_code'] ?? null;
        $this->isPremium = $data['is_premium'] ?? null;
        $this->addedToAttachmentMenu = $data['added_to_attachment_menu'] ?? null;
    }

    public function getFullName(): string
    {
        if ($this->lastName) {
            return trim($this->firstName . ' ' . $this->lastName);
        }
        return $this->firstName;
    }

    public function getMention(): string
    {
        return $this->username ? '@' . $this->username : $this->getFullName();
    }
}

/**
 * Represents a Telegram Chat
 */
class TelegramChat
{
    public int $id;
    public string $type; // private, group, supergroup, channel
    public ?string $title;
    public ?string $username;
    public ?string $firstName;
    public ?string $lastName;
    public ?bool $isForum;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->title = $data['title'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->firstName = $data['first_name'] ?? null;
        $this->lastName = $data['last_name'] ?? null;
        $this->isForum = $data['is_forum'] ?? null;
    }

    public function getName(): string
    {
        return $this->title ?? $this->firstName ?? $this->username ?? 'Unknown';
    }
}

/**
 * Represents a Telegram Message
 */
class TelegramMessage
{
    public int $messageId;
    public ?TelegramUser $from;
    public TelegramChat $chat;
    public int $date;
    public ?string $text;
    public ?array $entities;
    public ?array $photo;
    public ?array $document;
    public ?array $sticker;
    public ?array $animation;
    public ?array $video;
    public ?array $voice;
    public ?array $contact;
    public ?array $location;
    public ?array $poll;
    public ?array $replyMarkup;
    public ?TelegramMessage $replyToMessage;

    public function __construct(array $data)
    {
        $this->messageId = $data['message_id'];
        $this->from = isset($data['from']) ? new TelegramUser($data['from']) : null;
        $this->chat = new TelegramChat($data['chat']);
        $this->date = $data['date'];
        $this->text = $data['text'] ?? null;
        $this->entities = $data['entities'] ?? null;
        $this->photo = $data['photo'] ?? null;
        $this->document = $data['document'] ?? null;
        $this->sticker = $data['sticker'] ?? null;
        $this->animation = $data['animation'] ?? null;
        $this->video = $data['video'] ?? null;
        $this->voice = $data['voice'] ?? null;
        $this->contact = $data['contact'] ?? null;
        $this->location = $data['location'] ?? null;
        $this->poll = $data['poll'] ?? null;
        $this->replyMarkup = $data['reply_markup'] ?? null;
        $this->replyToMessage = isset($data['reply_to_message']) 
            ? new self($data['reply_to_message']) 
            : null;
    }

    public function isCommand(): bool
    {
        return $this->text && strpos($this->text, '/') === 0;
    }

    public function getCommand(): ?string
    {
        if (!$this->isCommand()) {
            return null;
        }
        $parts = explode(' ', $this->text);
        $command = explode('@', $parts[0]);
        return strtolower($command[0]);
    }

    public function getCommandArgs(): array
    {
        if (!$this->isCommand()) {
            return [];
        }
        $parts = explode(' ', $this->text);
        array_shift($parts);
        return $parts;
    }
}

/**
 * Represents a Callback Query
 */
class TelegramCallbackQuery
{
    public string $id;
    public TelegramUser $from;
    public ?TelegramMessage $message;
    public ?string $data;
    public ?string $chatInstance;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->from = new TelegramUser($data['from']);
        $this->message = isset($data['message']) ? new TelegramMessage($data['message']) : null;
        $this->data = $data['data'] ?? null;
        $this->chatInstance = $data['chat_instance'] ?? null;
    }

    public function getDataArray(): array
    {
        if (!$this->data) {
            return [];
        }
        return json_decode($this->data, true) ?? explode(':', $this->data);
    }
}

/**
 * Represents an Inline Query
 */
class TelegramInlineQuery
{
    public string $id;
    public TelegramUser $from;
    public string $query;
    public string $offset;
    public ?string $chatType;
    public ?array $location;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->from = new TelegramUser($data['from']);
        $this->query = $data['query'];
        $this->offset = $data['offset'];
        $this->chatType = $data['chat_type'] ?? null;
        $this->location = $data['location'] ?? null;
    }
}

/**
 * Represents a PreCheckout Query
 */
class TelegramPreCheckoutQuery
{
    public string $id;
    public TelegramUser $from;
    public string $currency;
    public int $totalAmount;
    public string $invoicePayload;
    public ?string $shippingOptionId;
    public ?array $orderInfo;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->from = new TelegramUser($data['from']);
        $this->currency = $data['currency'];
        $this->totalAmount = $data['total_amount'];
        $this->invoicePayload = $data['invoice_payload'];
        $this->shippingOptionId = $data['shipping_option_id'] ?? null;
        $this->orderInfo = $data['order_info'] ?? null;
    }
}

/**
 * Represents a Shipping Query
 */
class TelegramShippingQuery
{
    public string $id;
    public TelegramUser $from;
    public string $invoicePayload;
    public array $shippingAddress;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->from = new TelegramUser($data['from']);
        $this->invoicePayload = $data['invoice_payload'];
        $this->shippingAddress = $data['shipping_address'];
    }
}

/**
 * Represents a Chat Member
 */
class TelegramChatMember
{
    public TelegramUser $user;
    public string $status; // creator, administrator, member, restricted, left, kicked
    public ?string $customTitle;
    public ?bool $isAnonymous;
    public ?int $untilDate;
    public ?bool $canBeEdited;
    public ?bool $canPostMessages;
    public ?bool $canEditMessages;
    public ?bool $canDeleteMessages;
    public ?bool $canRestrictMembers;
    public ?bool $canPromoteMembers;
    public ?bool $canChangeInfo;
    public ?bool $canInviteUsers;
    public ?bool $canPinMessages;
    public ?bool $isMember;
    public ?bool $canSendMessages;
    public ?bool $canSendAudios;
    public ?bool $canSendDocuments;
    public ?bool $canSendPhotos;
    public ?bool $canSendVideos;
    public ?bool $canSendVideoNotes;
    public ?bool $canSendVoiceNotes;
    public ?bool $canSendPolls;
    public ?bool $canSendOtherMessages;
    public ?bool $canAddWebPagePreviews;

    public function __construct(array $data)
    {
        $this->user = new TelegramUser($data['user']);
        $this->status = $data['status'];
        $this->customTitle = $data['custom_title'] ?? null;
        $this->isAnonymous = $data['is_anonymous'] ?? null;
        $this->untilDate = $data['until_date'] ?? null;
        $this->canBeEdited = $data['can_be_edited'] ?? null;
        $this->canPostMessages = $data['can_post_messages'] ?? null;
        $this->canEditMessages = $data['can_edit_messages'] ?? null;
        $this->canDeleteMessages = $data['can_delete_messages'] ?? null;
        $this->canRestrictMembers = $data['can_restrict_members'] ?? null;
        $this->canPromoteMembers = $data['can_promote_members'] ?? null;
        $this->canChangeInfo = $data['can_change_info'] ?? null;
        $this->canInviteUsers = $data['can_invite_users'] ?? null;
        $this->canPinMessages = $data['can_pin_messages'] ?? null;
        $this->isMember = $data['is_member'] ?? null;
        $this->canSendMessages = $data['can_send_messages'] ?? null;
        $this->canSendAudios = $data['can_send_audios'] ?? null;
        $this->canSendDocuments = $data['can_send_documents'] ?? null;
        $this->canSendPhotos = $data['can_send_photos'] ?? null;
        $this->canSendVideos = $data['can_send_videos'] ?? null;
        $this->canSendVideoNotes = $data['can_send_video_notes'] ?? null;
        $this->canSendVoiceNotes = $data['can_send_voice_notes'] ?? null;
        $this->canSendPolls = $data['can_send_polls'] ?? null;
        $this->canSendOtherMessages = $data['can_send_other_messages'] ?? null;
        $this->canAddWebPagePreviews = $data['can_add_web_page_previews'] ?? null;
    }

    public function isAdmin(): bool
    {
        return in_array($this->status, ['creator', 'administrator']);
    }

    public function can($permission): bool
    {
        $prop = 'can' . ucfirst($permission);
        return property_exists($this, $prop) ? $this->$prop : false;
    }
}

/**
 * Represents a Bot Command
 */
class TelegramBotCommand
{
    public string $command;
    public string $description;

    public function __construct(array $data)
    {
        $this->command = $data['command'];
        $this->description = $data['description'];
    }
}

/**
 * Represents a Poll
 */
class TelegramPoll
{
    public string $id;
    public string $question;
    public array $options;
    public int $totalVoterCount;
    public bool $isClosed;
    public bool $isAnonymous;
    public string $type; // regular, quiz
    public bool $allowsMultipleAnswers;
    public ?int $correctOptionId;
    public ?string $explanation;
    public ?array $explanationEntities;
    public ?int $openPeriod;
    public ?int $closeDate;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->question = $data['question'];
        $this->options = $data['options'];
        $this->totalVoterCount = $data['total_voter_count'];
        $this->isClosed = $data['is_closed'];
        $this->isAnonymous = $data['is_anonymous'];
        $this->type = $data['type'];
        $this->allowsMultipleAnswers = $data['allows_multiple_answers'];
        $this->correctOptionId = $data['correct_option_id'] ?? null;
        $this->explanation = $data['explanation'] ?? null;
        $this->explanationEntities = $data['explanation_entities'] ?? null;
        $this->openPeriod = $data['open_period'] ?? null;
        $this->closeDate = $data['close_date'] ?? null;
    }
}
