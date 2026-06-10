<?php

namespace WordPress\YandexCloudAiProvider\Provider;

use WordPress\AiClient\AiClient;
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
use WordPress\YandexCloudAiProvider\Models\YandexTextGenerationModel;

class YandexProvider extends AbstractApiProvider {

	protected static function baseUrl(): string {
		return 'https://llm.api.cloud.yandex.net/foundationModels/v1';
	}

	protected static function createModel( ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata ): ModelInterface {
		foreach ( $modelMetadata->getSupportedCapabilities() as $cap ) {
			if ( $cap->isTextGeneration() ) {
				return new YandexTextGenerationModel( $modelMetadata, $providerMetadata );
			}
			if ( $cap->isImageGeneration() ) {
				return new YandexImageGenerationModel( $modelMetadata, $providerMetadata );
			}
		}
		throw new RuntimeException( 'Эта модель не поддерживается.' );
	}

	protected static function createProviderMetadata(): ProviderMetadata {
		$args = [
			'yandex-cloud',
			'Яндекс Клауд',
			ProviderTypeEnum::cloud(),
			'https://console.yandex.cloud/folders',
			RequestAuthenticationMethod::apiKey(),
		];

		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$args[] = 'Для подключения укажите ключ в формате folder_id:api_key.';
		}

		return new ProviderMetadata( ...$args );
	}

	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new YandexProviderAvailability();
	}

	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new YandexModelMetadataDirectory();
	}
}
