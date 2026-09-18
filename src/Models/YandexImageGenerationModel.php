<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\YandexCloudAiProvider\Http\Traits\WithYandexAuthenticationTrait;
use WordPress\YandexCloudAiProvider\Provider\YandexProvider;

/**
 * Class for a Yandex Cloud image generation model.
 *
 * Image generation in the Yandex Cloud Foundation Models API is asynchronous:
 * the request creates an operation, and the result is polled at the operation
 * URL until the operation is done.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface
{
    use WithYandexAuthenticationTrait;

    /**
     * The maximum number of attempts to poll an operation.
     *
     * @since 1.0.1
     *
     * @var int
     */
    private const OPERATION_MAX_ATTEMPTS = 18;

    /**
     * The delay between operation polling attempts, in seconds.
     *
     * @since 1.0.1
     *
     * @var int
     */
    private const OPERATION_POLL_INTERVAL = 5;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    final public function generateImageResult(array $prompt): GenerativeAiResult
    {
        $body = [
            'modelUri' => $this->getModelUri(),
            'generationOptions' => [
                'mimeType' => 'image/jpeg',
                'aspectRatio' => $this->getAspectRatio(),
            ],
            'messages' => [
                ['text' => $this->getPromptText($prompt)],
            ],
        ];

        $request = new Request(
            HttpMethodEnum::POST(),
            YandexProvider::url('imageGenerationAsync'),
            ['Content-Type' => 'application/json'],
            $body,
            $this->buildRequestOptions()
        );

        $response = $this->getHttpTransporter()->send(
            $this->authenticateRequest($request)
        );

        ResponseUtil::throwIfNotSuccessful($response);

        $data = $response->getData();

        if (!is_array($data) || empty($data['id'])) {
            throw ResponseException::fromMissingData($this->providerMetadata()->getName(), 'id');
        }

        return $this->waitImage((string) $data['id']);
    }

    /**
     * Polls the operation until the image is generated.
     *
     * @since 1.0.0
     *
     * @param string $operationId The Yandex Cloud operation ID.
     * @return GenerativeAiResult The parsed result.
     * @throws RuntimeException If the operation reports an error or times out.
     */
    private function waitImage(string $operationId): GenerativeAiResult
    {
        for ($attempt = 0; $attempt < self::OPERATION_MAX_ATTEMPTS; $attempt++) {
            sleep(self::OPERATION_POLL_INTERVAL);

            $request = new Request(
                HttpMethodEnum::GET(),
                YandexProvider::operationsUrl($operationId),
                [],
                null,
                $this->buildRequestOptions()
            );

            $response = $this->getHttpTransporter()->send(
                $this->authenticateRequest($request)
            );

            ResponseUtil::throwIfNotSuccessful($response);

            $data = $response->getData();

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['error']) && is_array($data['error'])) {
                $message = isset($data['error']['message']) ? (string) $data['error']['message'] : 'Не удалось сгенерировать изображение в Яндекс Клауд.';
                throw new RuntimeException($message);
            }

            if (!empty($data['done'])) {
                return $this->makeResult($data, $operationId);
            }
        }

        throw new RuntimeException('Превышено время ожидания генерации изображения в Яндекс Клауд.');
    }

    /**
     * Builds the generative AI result from a finished operation.
     *
     * @since 1.0.0
     *
     * @param array $data        The operation data.
     * @param string $operationId The Yandex Cloud operation ID.
     * @return GenerativeAiResult The parsed result.
     * @throws ResponseException If the operation response has an unexpected shape.
     */
    private function makeResult(array $data, string $operationId): GenerativeAiResult
    {
        $providerName = $this->providerMetadata()->getName();

        if (empty($data['response']) || !is_array($data['response'])) {
            throw ResponseException::fromMissingData($providerName, 'response');
        }

        if (empty($data['response']['image']) || !is_string($data['response']['image'])) {
            throw ResponseException::fromMissingData($providerName, 'response.image');
        }

        $file = new File($data['response']['image'], 'image/jpeg');
        $message = new Message(MessageRoleEnum::model(), [new MessagePart($file)]);
        $candidate = new Candidate($message, FinishReasonEnum::stop());

        return new GenerativeAiResult(
            $operationId,
            [$candidate],
            new TokenUsage(0, 0, 0),
            $this->providerMetadata(),
            $this->metadata(),
            $data
        );
    }

    /**
     * Gets the text prompt from the prompt messages.
     *
     * @since 1.0.0
     *
     * @param array $prompt The prompt presented as a list of messages.
     * @return string The text prompt.
     * @throws InvalidArgumentException If the prompt is empty or contains reference images.
     */
    private function getPromptText(array $prompt): string
    {
        if (count($prompt) < 1 || !($prompt[0] instanceof Message)) {
            throw new InvalidArgumentException('Пустой промпт.');
        }

        foreach ($prompt[0]->getParts() as $part) {
            if (!$part instanceof MessagePart) {
                continue;
            }

            if (null !== $part->getFile()) {
                throw new InvalidArgumentException('Ссылочные изображения не поддерживаются.');
            }

            if (null !== $part->getText()) {
                return $part->getText();
            }
        }

        throw new InvalidArgumentException('Текстовый промпт пуст.');
    }

    /**
     * Builds the aspect ratio generation option.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The width and height ratios.
     */
    private function getAspectRatio(): array
    {
        $config = $this->getConfig();
        $ratio = $config->getOutputMediaAspectRatio();

        if (null === $ratio || '' === $ratio) {
            $orientation = $config->getOutputMediaOrientation();

            if (null !== $orientation && $orientation->isLandscape()) {
                $ratio = '2:1';
            } elseif (null !== $orientation && $orientation->isPortrait()) {
                $ratio = '1:2';
            } else {
                $ratio = '1:1';
            }
        }

        $parts = explode(':', $ratio);

        if (2 !== count($parts)) {
            $parts = ['1', '1'];
        }

        return [
            'widthRatio' => (string) max(1, (int) $parts[0]),
            'heightRatio' => (string) max(1, (int) $parts[1]),
        ];
    }

    /**
     * Builds the Yandex Cloud model URI for image generation.
     *
     * @since 1.0.0
     *
     * @return string The model URI.
     */
    private function getModelUri(): string
    {
        $folderId = $this->getFolderId();
        $modelId = $this->metadata()->getId();

        if ('yandex-art' === $modelId) {
            return sprintf('art://%s/yandex-art/latest', $folderId);
        }

        return sprintf('art://%s/%s', $folderId, $modelId);
    }

    /**
     * Builds the request options for the image generation endpoints.
     *
     * @since 1.0.0
     *
     * @return RequestOptions The request options.
     */
    private function buildRequestOptions(): RequestOptions
    {
        $options = new RequestOptions();
        $options->setTimeout(30.0);
        $options->setConnectTimeout(15.0);

        return $options;
    }
}

// UPDATED by Opencode in 2026-09-18
