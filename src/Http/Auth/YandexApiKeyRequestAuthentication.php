<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Http\Auth;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Yandex Cloud OpenAI-compatible request authentication.
 *
 * The stored credential is a `folder_id:api_key` string. The OpenAI-compatible
 * endpoint expects the raw API key (without the folder ID) sent as an
 * `Authorization: Api-Key` header, unlike the Bearer scheme used by the
 * Foundation Models gRPC API.
 *
 * @since 1.1.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
final class YandexApiKeyRequestAuthentication extends ApiKeyRequestAuthentication
{
    /**
     * {@inheritDoc}
     */
    public function authenticateRequest(Request $request): Request
    {
        $keys = array_map('trim', explode(':', $this->getApiKey(), 2));
        $apiKey = $keys[1] ?? $keys[0];

        return $request->withHeader('Authorization', 'Api-Key ' . $apiKey);
    }
}