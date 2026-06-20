<?php

/**
 * TelegramBot InputFile Helper
 * 
 * Handles local files, URLs, and file IDs uniformly for the Telegram Bot API.
 * 
 * Usage:
 *   $file = InputFile::local('/path/to/image.jpg');
 *   $file = InputFile::url('https://example.com/image.jpg');
 *   $file = InputFile::id('file_id_from_telegram');
 */
class InputFile
{
    private mixed $data;
    private ?string $filename;

    private function __construct(mixed $data, ?string $filename = null)
    {
        $this->data = $data;
        $this->filename = $filename;
    }

    /**
     * Create from a local file path.
     * Uses CURLFile for proper multipart/form-data handling.
     *
     * @param string $path Absolute or relative path to the file
     * @param string|null $name Optional filename to send to Telegram
     * @return self
     * @throws InvalidArgumentException If file does not exist or is unreadable
     */
    public static function local(string $path, ?string $name = null): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("File not found: {$path}");
        }
        if (!is_readable($path)) {
            throw new InvalidArgumentException("File not readable: {$path}");
        }

        $filename = $name ?? basename($path);
        // CURLFile handles the @ prefix logic internally in modern PHP
        return new self(new CURLFile($path, mime_content_type($path), $filename), $filename);
    }

    /**
     * Create from a URL.
     * Telegram will download the file from this URL.
     *
     * @param string $url Public URL of the file
     * @return self
     */
    public static function url(string $url): self
    {
        return new self($url, null);
    }

    /**
     * Create from an existing Telegram file_id.
     *
     * @param string $fileId The file_id from Telegram
     * @return self
     */
    public static function id(string $fileId): self
    {
        return new self($fileId, null);
    }

    /**
     * Create from raw content (string).
     * Useful for generated images or text files.
     *
     * @param string $content The raw file content
     * @param string $filename The filename to present to Telegram
     * @return self
     */
    public static function content(string $content, string $filename): self
    {
        // We store this in a temp stream for CURL to read
        $tempPath = tempnam(sys_get_temp_dir(), 'tg_bot_');
        file_put_contents($tempPath, $content);
        // Register shutdown function to cleanup? 
        // Better to let CURLFile handle it, but we need to keep the file until request is done.
        // For simplicity in this helper, we return a CURLFile directly.
        return new self(new CURLFile($tempPath, 'application/octet-stream', $filename), $filename);
    }

    /**
     * Get the data to be sent in the request.
     *
     * @return mixed CURLFile object, string (URL/ID), or resource
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the filename associated with this file.
     *
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Check if this input requires a multipart/form-data request.
     * (True if it's a local file or raw content, False if it's a URL or ID)
     *
     * @return bool
     */
    public function isUpload(): bool
    {
        return $this->data instanceof CURLFile;
    }
}
