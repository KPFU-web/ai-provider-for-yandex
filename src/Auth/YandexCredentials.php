<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Auth;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;

/**
 * Immutable value object for Yandex Cloud API credentials.
 *
 * The AI Client stores a provider's credentials as a single API key string.
 * For Yandex Cloud that string combines the folder ID and the service account
 * API key in the `folder_id:api_key` format.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
final class YandexCredentials
{
    /**
     * The Yandex Cloud folder ID.
     *
     * @var string
     */
    private string $folderId;

    /**
     * The Yandex Cloud service account API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Constructor.
     *
     * The constructor is private; use the `fromRaw()` and `requireFromRaw()`
     * methods to create instances.
     *
     * @since 1.0.0
     *
     * @param string $folderId The Yandex Cloud folder ID.
     * @param string $apiKey   The Yandex Cloud service account API key.
     */
    private function __construct(string $folderId, string $apiKey)
    {
        $this->folderId = $folderId;
        $this->apiKey = $apiKey;
    }

    /**
     * Parses credentials from a raw `folder_id:api_key` string.
     *
     * @since 1.0.0
     *
     * @param string $raw The raw credential string.
     * @return YandexCredentials|null The parsed credentials, or null when the string is not in the `folder_id:api_key` format.
     */
    public static function fromRaw(string $raw): ?self
    {
        $parts = array_map('trim', explode(':', trim($raw), 2));

        if (count($parts) !== 2 || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }

        return new self($parts[0], $parts[1]);
    }

    /**
     * Parses credentials from a raw `folder_id:api_key` string or throws an exception.
     *
     * @since 1.0.0
     *
     * @param string $raw The raw credential string.
     * @return YandexCredentials The parsed credentials.
     * @throws InvalidArgumentException If the string is not in the `folder_id:api_key` format.
     */
    public static function requireFromRaw(string $raw): self
    {
        $credentials = self::fromRaw($raw);

        if (null === $credentials) {
            throw new InvalidArgumentException('Укажите ключ Яндекс Клауд в формате folder_id:api_key.');
        }

        return $credentials;
    }

    /**
     * Gets the Yandex Cloud folder ID.
     *
     * @since 1.0.0
     *
     * @return string The folder ID.
     */
    public function getFolderId(): string
    {
        return $this->folderId;
    }

    /**
     * Gets the Yandex Cloud service account API key.
     *
     * @since 1.0.0
     *
     * @return string The API key.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}

// UPDATED by Opencode in 2026-09-18
