<?php

namespace WordPress\YandexCloudAiProvider\Metadata;

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class YandexModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	private function getTextModels(): array {
		$caps = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		$opts = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
		];

		return [
			new ModelMetadata( 'yandexgpt',           'YandexGPT Pro',    $caps, $opts ),
			new ModelMetadata( 'yandexgpt-lite',      'YandexGPT Lite',   $caps, $opts ),
			new ModelMetadata( 'yandexgpt-32k',       'YandexGPT Pro 32K',$caps, $opts ),
			new ModelMetadata( 'llama',               'Llama 3.3 70B',    $caps, $opts ),
			new ModelMetadata( 'llama-lite',          'Llama 3.1 8B',     $caps, $opts ),
			new ModelMetadata( 'qwen3-235b-a22b-fp8', 'Qwen3 235B',       $caps, $opts ),
			new ModelMetadata( 'gpt-oss-120b',        'GPT OSS 120B',     $caps, $opts ),
			new ModelMetadata( 'gpt-oss-20b',         'GPT OSS 20B',      $caps, $opts ),
		];
	}

	private function getImageModels(): array {
		$caps = [
			CapabilityEnum::imageGeneration(),
		];

		$opts = [
			new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::image() ] ] ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'image/jpeg' ] ),
			new SupportedOption( OptionEnum::outputFileType(), [ FileTypeEnum::inline() ] ),
			new SupportedOption( OptionEnum::outputMediaOrientation(), [
				MediaOrientationEnum::square(),
				MediaOrientationEnum::landscape(),
				MediaOrientationEnum::portrait(),
			] ),
			new SupportedOption( OptionEnum::outputMediaAspectRatio(), [ '1:1', '2:1', '1:2', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16' ] ),
			new SupportedOption( OptionEnum::customOptions() ),
		];

		return [
			new ModelMetadata( 'yandex-art', 'YandexART', $caps, $opts ),
			new ModelMetadata( 'yandex-art-2.0', 'YandexART 2.0', $caps, $opts ),
		];
	}

	private function getModels(): array {
		return array_merge( $this->getTextModels(), $this->getImageModels() );
	}

	public function listModelMetadata(): array {
		return $this->getModels();
	}

	public function hasModelMetadata( string $id ): bool {
		foreach ( $this->getModels() as $m ) {
			if ( $m->getId() === $id ) return true;
		}
		return false;
	}

	public function getModelMetadata( string $id ): ModelMetadata {
		foreach ( $this->getModels() as $m ) {
			if ( $m->getId() === $id ) return $m;
		}

		$caps = [ CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ];
		$opts = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
		];

		return new ModelMetadata( $id, $id, $caps, $opts );
	}
}
