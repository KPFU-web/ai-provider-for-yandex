<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Http\Traits;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\YandexCloudAiProvider\Auth\YandexCredentials;

/**
 * Trait for Yandex Cloud based models that requires the `WithRequestAuthenticationTrait`.
 *
 * The WordPress AI Client passes the stored credential value as an API key and
 * sends it as an `Authorization: Bearer` header. The Yandex Cloud Foundation
 * Models API expects the API key in an `Authorization: Api-Key` header instead,
 * and the folder ID (stored in the same `folder_id:api_key` value) is needed to
 * build the model URI. This trait parses the credential value once and rewrites
 * the request authentication accordingly.
 *
 * @since 1.0.1
 *
 * @package WordPress\YandexCloudAiProvider
 */
trait WithYandexAuthenticationTrait
{
    /**
     * Gets the parsed Yandex Cloud credentials for the configured API key.
     *
     * @since 1.0.1
     *
     * @return YandexCredentials The parsed credentials.
     * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the configured key is not in the `folder_id:api_key` format.
     */
    protected function getCredentials(): YandexCredentials
    {
        return YandexCredentials::requireFromRaw($this->getRawApiKey());
    }

    /**
     * Gets the Yandex Cloud folder ID from the configured API key.
     *
     * @since 1.0.1
     *
     * @return string The folder ID.
     * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the configured key is not in the `folder_id:api_key` format.
     */
    protected function getFolderId(): string
    {
        return $this->getCredentials()->getFolderId();
    }

    /**
     * Authenticates a request for the Yandex Cloud API.
     *
     * Delegates to the request authentication provided by the AI Client, then
     * rewrites the resulting `Authorization: Bearer` header into the
     * `Authorization: Api-Key` header expected by Yandex Cloud.
     *
     * @since 1.0.1
     *
     * @param Request $request The request to authenticate.
     * @return Request The authenticated request.
     */
    protected function authenticateRequest(Request $request): Request
    {
        $request = $this->getRequestAuthentication()->authenticateRequest($request);

        return $this->rewriteAuthorizationHeader($request);
    }

    /**
     * Rewrites the `Authorization: Bearer` header into the Yandex Cloud `Api-Key` format.
     *
     * @since 1.0.1
     *
     * @param Request $request The request with a `Bearer` authorization header.
     * @return Request The request with the rewritten authorization header.
     */
    private function rewriteAuthorizationHeader(Request $request): Request
    {
        $authorization = $request->getHeaderAsString('Authorization');

        if (null !== $authorization && str_starts_with($authorization, 'Bearer ')) {
            $credentials = YandexCredentials::requireFromRaw(substr($authorization, 7));

            return $request->withHeader('Authorization', 'Api-Key ' . $credentials->getApiKey());
        }

        return $request;
    }

    /**
     * Gets the raw `folder_id:api_key` credential value from the request authentication.
     *
     * @since 1.0.1
     *
     * @return string The raw credential value, or an empty string when it cannot be read.
     */
    private function getRawApiKey(): string
    {
        $authentication = $this->getRequestAuthentication();

        if ($authentication instanceof ApiKeyRequestAuthentication) {
            return $authentication->getApiKey();
        }

        return '';
    }
}

// UPDATED by Opencode in 2026-09-18
