<?php

/**
 * TelegramResponse Class
 * 
 * Handles and parses responses from the Telegram Bot API.
 */
class TelegramResponse
{
    private bool $success;
    private mixed $result;
    private ?int $errorCode;
    private ?string $description;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->rawResponse = $response;
        $this->success = $response['ok'] ?? false;
        $this->result = $response['result'] ?? null;
        $this->errorCode = $response['error_code'] ?? null;
        $this->description = $response['description'] ?? null;
    }

    /**
     * Check if the request was successful
     */
    public function isOk(): bool
    {
        return $this->success;
    }

    /**
     * Get the result data (message, user, chat, etc.)
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Get the error code if failed
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * Get the error description if failed
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the raw response array
     */
    public function getRaw(): array
    {
        return $this->rawResponse;
    }

    /**
     * Throw an exception if the request failed
     * @throws RuntimeException
     */
    public function throwIfError(): self
    {
        if (!$this->success) {
            throw new RuntimeException(
                sprintf("Telegram API Error %d: %s", $this->errorCode, $this->description),
                $this->errorCode ?? 0
            );
        }
        return $this;
    }
}
