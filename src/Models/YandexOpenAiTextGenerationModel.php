<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Models;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\YandexCloudAiProvider\Http\Auth\YandexApiKeyRequestAuthentication;
use WordPress\YandexCloudAiProvider\Http\Traits\WithYandexAuthenticationTrait;

/**
 * Class for Yandex Cloud text generation models served via the OpenAI-compatible
 * HTTP API.
 *
 * Yandex Cloud exposes the newer Foundation models (DeepSeek, Qwen, GPT-OSS,
 * YandexGPT 5.x) through an OpenAI-compatible endpoint that is not the gRPC
 * Foundation Models API. This model uses the OpenAI chat completions protocol:
 * the model is addressed as `gpt://{folder_id}/{model_id}/latest` and the API
 * key is sent as an `Authorization: Api-Key` header.
 *
 * @since 1.1.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexOpenAiTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    use WithYandexAuthenticationTrait;

    /**
     * The base domain of the Yandex Cloud OpenAI-compatible AI API.
     *
     * @since 1.1.0
     *
     * @var string
     */
    private const OPENAI_BASE_URL = 'https://ai.api.cloud.yandex.net/v1';

    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        $authentication = parent::getRequestAuthentication();

        if (!$authentication instanceof ApiKeyRequestAuthentication) {
            return $authentication;
        }

        if ($authentication instanceof YandexApiKeyRequestAuthentication) {
            return $authentication;
        }

        return new YandexApiKeyRequestAuthentication($authentication->getApiKey());
    }

    /**
     * {@inheritDoc}
     *
     * Builds the `gpt://{folder_id}/{model_id}/latest` model URI required by
     * the Yandex Cloud OpenAI-compatible endpoint.
     *
     * @since 1.1.0
     *
     * @param array $prompt The prompt presented as a list of messages.
     * @return array<string, mixed> The request parameters.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);
        $params['model'] = sprintf('gpt://%s/%s/latest', $this->getFolderId(), $this->metadata()->getId());

        return $params;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        return new Request(
            $method,
            self::OPENAI_BASE_URL . '/' . ltrim($path, '/'),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}