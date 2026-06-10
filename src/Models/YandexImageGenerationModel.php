<?php

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
use WordPress\YandexCloudAiProvider\Auth\YandexCredentials;
use WordPress\YandexCloudAiProvider\Provider\YandexProvider;

class YandexImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface {

	final public function generateImageResult( array $prompt ): GenerativeAiResult {
		$body = [
			'modelUri'          => $this->getModelUri(),
			'generationOptions' => [
				'mimeType'    => 'image/jpeg',
				'aspectRatio' => $this->getAspectRatio(),
			],
			'messages'          => [
				[ 'text' => $this->getPromptText( $prompt ) ],
			],
		];

		$req = new Request(
			HttpMethodEnum::POST(),
			YandexProvider::url( 'imageGenerationAsync' ),
			[ 'Content-Type' => 'application/json' ],
			$body,
			$this->getRequestOpts()
		);

		$req = $this->getRequestAuthentication()->authenticateRequest( $req );
		$req = $this->fixAuth( $req );

		$res = $this->getHttpTransporter()->send( $req );
		ResponseUtil::throwIfNotSuccessful( $res );

		$data = $res->getData();
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'id' );
		}

		return $this->waitImage( (string) $data['id'] );
	}

	private function waitImage( string $id ): GenerativeAiResult {
		for ( $i = 0; $i < 18; $i++ ) {
			sleep( 5 );

			$req = new Request(
				HttpMethodEnum::GET(),
				'https://llm.api.cloud.yandex.net/operations/' . rawurlencode( $id ),
				[],
				null,
				$this->getRequestOpts()
			);

			$req = $this->getRequestAuthentication()->authenticateRequest( $req );
			$req = $this->fixAuth( $req );

			$res = $this->getHttpTransporter()->send( $req );
			ResponseUtil::throwIfNotSuccessful( $res );

			$data = $res->getData();
			if ( ! is_array( $data ) ) {
				continue;
			}

			if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
				$text = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'Yandex image generation error.';
				throw new RuntimeException( $text );
			}

			if ( ! empty( $data['done'] ) ) {
				return $this->makeResult( $data, $id );
			}
		}

		throw new RuntimeException( 'Yandex image generation timeout.' );
	}

	private function makeResult( array $data, string $id ): GenerativeAiResult {
		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'response' );
		}

		if ( empty( $data['response']['image'] ) || ! is_string( $data['response']['image'] ) ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'response.image' );
		}

		$file      = new File( $data['response']['image'], 'image/jpeg' );
		$msg       = new Message( MessageRoleEnum::model(), [ new MessagePart( $file ) ] );
		$candidate = new Candidate( $msg, FinishReasonEnum::stop() );

		return new GenerativeAiResult(
			$id,
			[ $candidate ],
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata(),
			$data
		);
	}

	private function getPromptText( array $prompt ): string {
		if ( count( $prompt ) < 1 || ! $prompt[0] instanceof Message ) {
			throw new InvalidArgumentException( 'Empty prompt.' );
		}

		foreach ( $prompt[0]->getParts() as $part ) {
			if ( ! $part instanceof MessagePart ) {
				continue;
			}

			if ( $part->getFile() ) {
				throw new InvalidArgumentException( 'Reference images are not supported.' );
			}

			if ( $part->getText() ) {
				return $part->getText();
			}
		}

		throw new InvalidArgumentException( 'Text prompt is empty.' );
	}

	private function getAspectRatio(): array {
		$cfg = $this->getConfig();
		$val = $cfg->getOutputMediaAspectRatio();

		if ( ! $val ) {
			$orientation = $cfg->getOutputMediaOrientation();
			if ( $orientation && $orientation->isLandscape() ) {
				$val = '2:1';
			} elseif ( $orientation && $orientation->isPortrait() ) {
				$val = '1:2';
			} else {
				$val = '1:1';
			}
		}

		$parts = explode( ':', $val );
		if ( count( $parts ) !== 2 ) {
			$parts = [ '1', '1' ];
		}

		return [
			'widthRatio'  => (string) max( 1, (int) $parts[0] ),
			'heightRatio' => (string) max( 1, (int) $parts[1] ),
		];
	}

	private function getModelUri(): string {
		$folder = $this->getFolderId();
		$model  = $this->metadata()->getId();

		if ( $model === 'yandex-art' ) {
			return "art://{$folder}/yandex-art/latest";
		}

		return "art://{$folder}/{$model}";
	}

	private function getRequestOpts(): RequestOptions {
		$opts = new RequestOptions();
		$opts->setTimeout( 30 );
		$opts->setConnectTimeout( 15 );
		return $opts;
	}

	private function fixAuth( Request $req ): Request {
		$val = $req->getHeaderAsString( 'Authorization' );

		if ( $val && str_starts_with( $val, 'Bearer ' ) ) {
			$credentials = YandexCredentials::requireFromRaw( substr( $val, 7 ) );
			return $req->withHeader( 'Authorization', 'Api-Key ' . $credentials->getApiKey() );
		}

		return $req;
	}

	private function getFolderId(): string {
		return YandexCredentials::requireFromRaw( $this->getRawKey() )->getFolderId();
	}

	private function getRawKey(): string {
		$auth = $this->getRequestAuthentication();
		if ( method_exists( $auth, 'getApiKey' ) ) {
			return $auth->getApiKey();
		}
		return '';
	}
}
