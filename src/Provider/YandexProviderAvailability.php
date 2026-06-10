<?php

namespace WordPress\YandexCloudAiProvider\Provider;

use Throwable;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\YandexCloudAiProvider\Auth\YandexCredentials;

class YandexProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface {

	use WithRequestAuthenticationTrait;

	public function isConfigured(): bool {
		try {
			$auth = $this->getRequestAuthentication();

			if ( ! method_exists( $auth, 'getApiKey' ) ) {
				return false;
			}

			return YandexCredentials::fromRaw( $auth->getApiKey() ) !== null;
		} catch ( Throwable $e ) {
			return false;
		}
	}
}
