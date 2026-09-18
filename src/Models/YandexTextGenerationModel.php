<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\YandexCloudAiProvider\Http\Traits\WithYandexAuthenticationTrait;
use WordPress\YandexCloudAiProvider\Provider\YandexProvider;

/**
 * Class for a Yandex Cloud text generation model.
 *
 * Yandex Cloud Foundation Models API is not OpenAI compatible, so the request
 * and the response are built and parsed explicitly.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface
{
    use WithYandexAuthenticationTrait;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    final public function generateTextResult(array $prompt): GenerativeAiResult
    {
        $request = new Request(
            HttpMethodEnum::POST(),
            YandexProvider::url('completion'),
            ['Content-Type' => 'application/json'],
            $this->buildParams($prompt),
            $this->buildRequestOptions()
        );

        $response = $this->getHttpTransporter()->send(
            $this->authenticateRequest($request)
        );

        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponse($response);
    }

    /**
     * Builds the request parameters for the completion endpoint.
     *
     * @since 1.0.0
     *
     * @param array $prompt The prompt presented as a list of messages.
     * @return array<string, mixed> The request parameters.
     * @throws InvalidArgumentException If a custom option overrides a reserved parameter.
     */
    private function buildParams(array $prompt): array
    {
        $config = $this->getConfig();
        $modelId = $this->metadata()->getId();
        $systemInstruction = $config->getSystemInstruction();

        if ('application/json' === $config->getOutputMimeType()) {
            $systemInstruction = $this->applyJsonOutputHint($systemInstruction);
        }

        $params = [
            'modelUri' => sprintf('gpt://%s/%s/latest', $this->getFolderId(), $modelId),
            'completionOptions' => ['stream' => false],
            'messages' => $this->buildMessages($prompt, $systemInstruction),
        ];

        if (null !== $config->getMaxTokens()) {
            $params['completionOptions']['maxTokens'] = $config->getMaxTokens();
        }

        if (null !== $config->getTemperature()) {
            $params['completionOptions']['temperature'] = $config->getTemperature();
        }

        foreach ($config->getCustomOptions() as $key => $value) {
            if (isset($params[$key])) {
                throw new InvalidArgumentException(sprintf("Параметр '%s' уже занят.", $key));
            }

            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Extends the system instruction with a hint for JSON output.
     *
     * @since 1.0.0
     *
     * @param string|null $systemInstruction The configured system instruction, if any.
     * @return string The system instruction extended with the JSON output hint.
     */
    private function applyJsonOutputHint(?string $systemInstruction): string
    {
        $hint = 'IMPORTANT: Respond with valid JSON only, no markdown, no extra text, no code blocks.';
        $hint .= ' If <available-terms> is present in the user message, you MUST only suggest terms from that exact list. Do not invent new terms.';

        $schema = $this->getConfig()->getOutputSchema();

        if (null !== $schema) {
            $hint .= ' JSON schema: ' . json_encode($schema);
        }

        if (null === $systemInstruction || '' === $systemInstruction) {
            return $hint;
        }

        return $systemInstruction . "\n\n" . $hint;
    }

    /**
     * Builds the list of Yandex Cloud messages from the prompt.
     *
     * @since 1.0.0
     *
     * @param array $prompt          The prompt presented as a list of messages.
     * @param string|null $systemInstruction The system instruction, if any.
     * @return list<array<string, string>> The Yandex Cloud messages.
     */
    private function buildMessages(array $prompt, ?string $systemInstruction): array
    {
        $messages = [];

        if (null !== $systemInstruction && '' !== $systemInstruction) {
            $messages[] = ['role' => 'system', 'text' => $systemInstruction];
        }

        foreach ($prompt as $message) {
            $item = $this->messageToArray($message);

            if (null !== $item) {
                $messages[] = $item;
            }
        }

        return $messages;
    }

    /**
     * Converts a prompt message to a Yandex Cloud message.
     *
     * @since 1.0.0
     *
     * @param Message $message The prompt message.
     * @return array<string, string>|null The Yandex Cloud message, or null when it has no text content.
     */
    private function messageToArray(Message $message): ?array
    {
        $texts = [];

        foreach ($message->getParts() as $part) {
            $text = $this->partToText($part);

            if (null !== $text && '' !== $text) {
                $texts[] = $text;
            }
        }

        if ([] === $texts) {
            return null;
        }

        $role = MessageRoleEnum::model() === $message->getRole() ? 'assistant' : 'user';

        return ['role' => $role, 'text' => implode("\n", $texts)];
    }

    /**
     * Extracts the text content of a message part.
     *
     * @since 1.0.0
     *
     * @param MessagePart $part The message part.
     * @return string|null The text content, or null for parts without text.
     */
    private function partToText(MessagePart $part): ?string
    {
        $type = $part->getType();

        if ($type->isText()) {
            return $part->getText();
        }

        if ($type->isFunctionCall()) {
            $functionCall = $part->getFunctionCall();

            if (null === $functionCall) {
                return null;
            }

            return json_encode(['call' => $functionCall->getName(), 'args' => $functionCall->getArgs()]);
        }

        if ($type->isFunctionResponse()) {
            $functionResponse = $part->getFunctionResponse();

            if (null === $functionResponse) {
                return null;
            }

            return json_encode(['response' => $functionResponse->getResponse()]);
        }

        return null;
    }

    /**
     * Builds the request options for the completion endpoint.
     *
     * @since 1.0.0
     *
     * @return RequestOptions The request options.
     */
    private function buildRequestOptions(): RequestOptions
    {
        $options = new RequestOptions();
        $options->setTimeout(60.0);
        $options->setConnectTimeout(15.0);

        return $options;
    }

    /**
     * Parses the completion endpoint response into a generative AI result.
     *
     * @since 1.0.0
     *
     * @param Response $response The HTTP response.
     * @return GenerativeAiResult The parsed result.
     * @throws ResponseException If the response has an unexpected shape.
     */
    private function parseResponse(Response $response): GenerativeAiResult
    {
        $data = $response->getData();
        $providerName = $this->providerMetadata()->getName();

        if (!isset($data['result']) || !is_array($data['result'])) {
            throw ResponseException::fromMissingData($providerName, 'result');
        }

        $result = $data['result'];

        if (!isset($result['alternatives']) || !is_array($result['alternatives'])) {
            throw ResponseException::fromMissingData($providerName, 'result.alternatives');
        }

        $candidates = [];

        foreach ($result['alternatives'] as $alternative) {
            $candidate = $this->alternativeToCandidate($alternative);

            if (null !== $candidate) {
                $candidates[] = $candidate;
            }
        }

        $usage = $this->parseUsage($result);
        $id = isset($result['modelVersion']) ? (string) $result['modelVersion'] : '';

        unset($result['alternatives'], $result['usage']);

        return new GenerativeAiResult(
            $id,
            $candidates,
            $usage,
            $this->providerMetadata(),
            $this->metadata(),
            $result
        );
    }

    /**
     * Parses the token usage from the completion result.
     *
     * @since 1.0.1
     *
     * @param array $result The completion result.
     * @return TokenUsage The token usage, or zero values when usage is missing.
     */
    private function parseUsage(array $result): TokenUsage
    {
        if (!isset($result['usage']) || !is_array($result['usage'])) {
            return new TokenUsage(0, 0, 0);
        }

        $usage = $result['usage'];
        $input = (int) ($usage['inputTextTokens'] ?? 0);
        $completion = (int) ($usage['completionTokens'] ?? 0);
        $total = (int) ($usage['totalTokens'] ?? $input + $completion);

        return new TokenUsage($input, $completion, $total);
    }

    /**
     * Converts an alternative from the completion result to a candidate.
     *
     * @since 1.0.0
     *
     * @param array $alternative The alternative from the completion result.
     * @return Candidate|null The candidate, or null when the alternative has no message.
     */
    private function alternativeToCandidate(array $alternative): ?Candidate
    {
        if (!isset($alternative['message']) || !is_array($alternative['message'])) {
            return null;
        }

        $text = (string) ($alternative['message']['text'] ?? '');
        $text = $this->stripCodeFences($text);

        $role = (isset($alternative['message']['role']) && 'user' === $alternative['message']['role'])
            ? MessageRoleEnum::user()
            : MessageRoleEnum::model();

        $message = new Message($role, [new MessagePart($text)]);
        $finishReason = $this->toFinishReason((string) ($alternative['status'] ?? ''));

        return new Candidate($message, $finishReason);
    }

    /**
     * Removes a markdown code fence from the model response text.
     *
     * @since 1.0.0
     *
     * @param string $text The response text.
     * @return string The text without the code fence.
     */
    private function stripCodeFences(string $text): string
    {
        $trimmed = trim($text);

        if (1 === preg_match('/^```(?:json)?\s*([\s\S]*?)```$/s', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }

    /**
     * Maps a Yandex Cloud alternative status to a finish reason.
     *
     * @since 1.0.0
     *
     * @param string $status The Yandex Cloud alternative status.
     * @return FinishReasonEnum The finish reason.
     */
    private function toFinishReason(string $status): FinishReasonEnum
    {
        if ('ALTERNATIVE_STATUS_TRUNCATED_FINAL' === $status) {
            return FinishReasonEnum::length();
        }

        if ('ALTERNATIVE_STATUS_CONTENT_FILTER' === $status || 'ALTERNATIVE_STATUS_ERROR' === $status) {
            return FinishReasonEnum::error();
        }

        return FinishReasonEnum::stop();
    }
}

// UPDATED by Opencode in 2026-09-18
