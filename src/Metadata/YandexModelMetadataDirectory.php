<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Metadata;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the Yandex Cloud model metadata directory.
 *
 * Yandex Cloud does not expose a public endpoint for listing the available
 * models, so the supported models are registered statically.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
    /**
     * The cached map of model ID to model metadata.
     *
     * @since 1.0.1
     *
     * @var array<string, ModelMetadata>|null
     */
    private ?array $modelsMap = null;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     *
     * @return list<ModelMetadata> Array of model metadata.
     */
    public function listModelMetadata(): array
    {
        return array_values($this->getModelsMap());
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function hasModelMetadata(string $modelId): bool
    {
        return isset($this->getModelsMap()[$modelId]);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function getModelMetadata(string $modelId): ModelMetadata
    {
        $modelsMap = $this->getModelsMap();

        if (!isset($modelsMap[$modelId])) {
            throw new InvalidArgumentException(
                sprintf('No model with ID %s was found in the provider', $modelId)
            );
        }

        return $modelsMap[$modelId];
    }

    /**
     * Returns the map of model ID to model metadata.
     *
     * The map is built once and cached for the lifetime of the instance.
     *
     * @since 1.0.1
     *
     * @return array<string, ModelMetadata> Map of model ID to model metadata.
     */
    private function getModelsMap(): array
    {
        if (null === $this->modelsMap) {
            $this->modelsMap = [];

            $models = array_merge($this->getTextModels(), $this->getImageModels());

            foreach ($models as $model) {
                $this->modelsMap[$model->getId()] = $model;
            }
        }

        return $this->modelsMap;
    }

    /**
     * Returns the metadata for the text generation models.
     *
     * @since 1.0.0
     *
     * @return list<ModelMetadata> Array of model metadata.
     */
    private function getTextModels(): array
    {
        $capabilities = [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        return [
            new ModelMetadata('yandexgpt', 'YandexGPT Pro', $capabilities, $options),
            new ModelMetadata('yandexgpt-lite', 'YandexGPT Lite', $capabilities, $options),
            new ModelMetadata('yandexgpt-32k', 'YandexGPT Pro 32K', $capabilities, $options),
            new ModelMetadata('yandexgpt-5-lite', 'YandexGPT 5 Lite', $capabilities, $options),
            new ModelMetadata('yandexgpt-5-pro', 'YandexGPT 5 Pro', $capabilities, $options),
            new ModelMetadata('yandexgpt-5.1', 'YandexGPT 5.1', $capabilities, $options),
            new ModelMetadata('llama', 'Llama 3.3 70B', $capabilities, $options),
            new ModelMetadata('llama-lite', 'Llama 3.1 8B', $capabilities, $options),
            new ModelMetadata('qwen3-235b-a22b-fp8', 'Qwen3 235B', $capabilities, $options),
            new ModelMetadata('qwen3.6-35b-a3b', 'Qwen3.6 35B', $capabilities, $options),
            new ModelMetadata('deepseek-v4-flash', 'DeepSeek V4 Flash', $capabilities, $options),
            new ModelMetadata('gpt-oss-120b', 'GPT OSS 120B', $capabilities, $options),
            new ModelMetadata('gpt-oss-20b', 'GPT OSS 20B', $capabilities, $options),
        ];
    }

    /**
     * Returns the metadata for the image generation models.
     *
     * @since 1.0.0
     *
     * @return list<ModelMetadata> Array of model metadata.
     */
    private function getImageModels(): array
    {
        $capabilities = [
            CapabilityEnum::imageGeneration(),
        ];

        $options = [
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
            new SupportedOption(OptionEnum::outputMimeType(), ['image/jpeg']),
            new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]),
            new SupportedOption(OptionEnum::outputMediaOrientation(), [
                MediaOrientationEnum::square(),
                MediaOrientationEnum::landscape(),
                MediaOrientationEnum::portrait(),
            ]),
            new SupportedOption(OptionEnum::outputMediaAspectRatio(), ['1:1', '2:1', '1:2', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16']),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        return [
            new ModelMetadata('yandex-art', 'YandexART', $capabilities, $options),
            new ModelMetadata('yandex-art-2.0', 'YandexART 2.0', $capabilities, $options),
        ];
    }
}

// UPDATED by Opencode in 2026-09-18
