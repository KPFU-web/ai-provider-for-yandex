<?php

namespace WordPress\YandexCloudAiProvider\Auth;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;

final class YandexCredentials {

	private string $folderId;
	private string $apiKey;

	private function __construct( string $folderId, string $apiKey ) {
		$this->folderId = $folderId;
		$this->apiKey   = $apiKey;
	}

	public static function fromRaw( string $raw ): ?self {
		$parts = array_map( 'trim', explode( ':', trim( $raw ), 2 ) );

		if ( count( $parts ) !== 2 || $parts[0] === '' || $parts[1] === '' ) {
			return null;
		}

		return new self( $parts[0], $parts[1] );
	}

	public static function requireFromRaw( string $raw ): self {
		$credentials = self::fromRaw( $raw );

		if ( ! $credentials ) {
			throw new InvalidArgumentException( 'Укажите ключ Яндекс Клауд в формате folder_id:api_key.' );
		}

		return $credentials;
	}

	public function getFolderId(): string {
		return $this->folderId;
	}

	public function getApiKey(): string {
		return $this->apiKey;
	}
}
