<?php

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider\Provider;

use Throwable;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\YandexCloudAiProvider\Auth\YandexCredentials;

/**
 * Class that checks whether the Yandex Cloud provider is configured with a valid API key.
 *
 * Yandex Cloud does not offer an authenticated endpoint to probe with, so the
 * provider is considered configured when the stored API key can be parsed as
 * a `folder_id:api_key` credential.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */
class YandexProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface
{
    use WithRequestAuthenticationTrait;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function isConfigured(): bool
    {
        try {
            $authentication = $this->getRequestAuthentication();

            if (!$authentication instanceof ApiKeyRequestAuthentication) {
                return false;
            }

            return null !== YandexCredentials::fromRaw($authentication->getApiKey());
        } catch (Throwable) {
            return false;
        }
    }
}

// UPDATED by Opencode in 2026-09-18
