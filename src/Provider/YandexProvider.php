<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\YandexCloudAiProvider\Metadata\YandexModelMetadataDirectory;
use WordPress\YandexCloudAiProvider\Models\YandexImageGenerationModel;
use WordPress\YandexCloudAiProvider\Models\YandexOpenAiTextGenerationModel;
use WordPress\YandexCloudAiProvider\Models\YandexTextGenerationModel;

/**
 * Class for the AI provider for Yandex Cloud.
 *
 * Yandex Cloud Foundation Models API is not OpenAI compatible, so the provider
 * uses dedicated model classes that speak the Yandex protocol directly.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexProvider extends AbstractApiProvider
{
    /**
     * The base domain of the Yandex Cloud AI API.
     *
     * @since 1.0.1
     *
     * @var string
     */
    private const BASE_DOMAIN = 'https://llm.api.cloud.yandex.net';

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return self::BASE_DOMAIN . '/foundationModels/v1';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();

        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                if (self::usesOpenAiApi($modelMetadata->getId())) {
                    return new YandexOpenAiTextGenerationModel($modelMetadata, $providerMetadata);
                }

                return new YandexTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        foreach ($capabilities as $capability) {
            if ($capability->isImageGeneration()) {
                return new YandexImageGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities for model: ' . $modelMetadata->getId()
        );
    }

    /**
     * Whether the given model is served through the OpenAI-compatible HTTP API.
     *
     * The OpenAI-compatible endpoint (ai.api.cloud.yandex.net/v1) hosts the
     * newer Foundation models (DeepSeek, Qwen, GPT-OSS, YandexGPT 5.x), while
     * the classic models are only reachable through the gRPC Foundation Models
     * completion API.
     *
     * @since 1.1.0
     *
     * @param string $modelId The model ID.
     * @return bool True when the model must be requested via the OpenAI API.
     */
    private static function usesOpenAiApi(string $modelId): bool
    {
        $grcpOnly = [
            'yandexgpt',
            'yandexgpt-lite',
            'yandexgpt-32k',
            'llama',
            'llama-lite',
        ];

        return !in_array($modelId, $grcpOnly, true);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'yandex-cloud',
            'Яндекс Клауд',
            ProviderTypeEnum::cloud(),
            'https://console.yandex.cloud/folders',
            RequestAuthenticationMethod::apiKey(),
            'Для подключения укажите ключ в формате folder_id:api_key.'
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new YandexProviderAvailability();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new YandexModelMetadataDirectory();
    }

    /**
     * Builds the URL of a Yandex Cloud operation endpoint.
     *
     * Yandex Cloud image generation is asynchronous: the generation request
     * returns an operation ID, and the result is polled at the operation URL.
     *
     * @since 1.0.1
     *
     * @param string $operationId The Yandex Cloud operation ID.
     * @return string The complete operation URL.
     */
    public static function operationsUrl(string $operationId): string
    {
        return self::BASE_DOMAIN . '/operations/' . rawurlencode($operationId);
    }
}

// UPDATED by Opencode in 2026-09-18
