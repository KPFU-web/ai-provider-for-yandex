<?php

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
use WordPress\YandexCloudAiProvider\Auth\YandexCredentials;
use WordPress\YandexCloudAiProvider\Provider\YandexProvider;

class YandexTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	final public function generateTextResult( array $prompt ): GenerativeAiResult {

		$params = $this->buildParams( $prompt );

		$opts = new RequestOptions();
		$opts->setTimeout( 60.0 );
		$opts->setConnectTimeout( 15.0 );

		$req = new Request(
			HttpMethodEnum::POST(),
			YandexProvider::url( 'completion' ),
			[ 'Content-Type' => 'application/json' ],
			$params,
			$opts
		);

		$req = $this->getRequestAuthentication()->authenticateRequest( $req );
		$req = $this->fixAuth( $req );

		$res = $this->getHttpTransporter()->send( $req );
		ResponseUtil::throwIfNotSuccessful( $res );

		return $this->parseRes( $res );
	}

	private function buildParams( array $prompt ): array {
		$cfg      = $this->getConfig();
		$model    = $this->metadata()->getId();
		$folder   = $this->getFolderId();

		$system = $cfg->getSystemInstruction();

		$mime = null;
		if ( method_exists( $cfg, 'getOutputMimeType' ) ) {
			$mime = $cfg->getOutputMimeType();
		}

		if ( $mime === 'application/json' ) {
			$schema = null;
			if ( method_exists( $cfg, 'getOutputSchema' ) ) {
				$schema = $cfg->getOutputSchema();
			}
			$json_hint = 'IMPORTANT: Respond with valid JSON only, no markdown, no extra text, no code blocks.';
			$json_hint .= ' If <available-terms> is present in the user message, you MUST only suggest terms from that exact list. Do not invent new terms.';
			if ( $schema ) {
				$json_hint .= ' JSON schema: ' . json_encode( $schema );
			}
			$system = $system ? $system . "\n\n" . $json_hint : $json_hint;
		}

		$params = [
			'modelUri'          => "gpt://{$folder}/{$model}/latest",
			'completionOptions' => [ 'stream' => false ],
			'messages'          => $this->buildMessages( $prompt, $system ),
		];

		if ( $cfg->getMaxTokens() !== null ) {
			$params['completionOptions']['maxTokens'] = $cfg->getMaxTokens();
		}

		if ( $cfg->getTemperature() !== null ) {
			$params['completionOptions']['temperature'] = $cfg->getTemperature();
		}

		foreach ( $cfg->getCustomOptions() as $k => $v ) {
			if ( isset( $params[ $k ] ) ) {
				throw new InvalidArgumentException( "Параметр '{$k}' уже занят." );
			}
			$params[ $k ] = $v;
		}

		return $params;
	}

	private function buildMessages( array $msgs, ?string $system ): array {
		$res = [];

		if ( $system ) {
			$res[] = [ 'role' => 'system', 'text' => $system ];
		}

		foreach ( $msgs as $msg ) {
			$item = $this->msgToArray( $msg );
			if ( $item ) $res[] = $item;
		}

		return $res;
	}

	private function msgToArray( Message $msg ): ?array {
		$parts = $msg->getParts();
		if ( empty( $parts ) ) return null;

		$texts = [];
		foreach ( $parts as $p ) {
			$t = $this->partToText( $p );
			if ( $t !== null && $t !== '' ) $texts[] = $t;
		}

		if ( empty( $texts ) ) return null;

		return [
			'role' => $msg->getRole() === MessageRoleEnum::model() ? 'assistant' : 'user',
			'text' => implode( "\n", $texts ),
		];
	}

	private function partToText( MessagePart $p ): ?string {
		$type = $p->getType();

		if ( $type->isText() ) return $p->getText();

		if ( $type->isFunctionCall() ) {
			$fc = $p->getFunctionCall();
			return $fc ? json_encode( [ 'call' => $fc->getName(), 'args' => $fc->getArgs() ] ) : null;
		}

		if ( $type->isFunctionResponse() ) {
			$fr = $p->getFunctionResponse();
			return $fr ? json_encode( [ 'response' => $fr->getResponse() ] ) : null;
		}

		return null;
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

	private function parseRes( Response $res ): GenerativeAiResult {
		$data = $res->getData();

		if ( ! isset( $data['result'] ) || ! is_array( $data['result'] ) ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'result' );
		}

		$result = $data['result'];

		if ( ! isset( $result['alternatives'] ) || ! is_array( $result['alternatives'] ) ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'result.alternatives' );
		}

		$candidates = [];
		foreach ( $result['alternatives'] as $i => $alt ) {
			$c = $this->altToCandidate( $alt );
			if ( $c ) $candidates[] = $c;
		}

		$usage = new TokenUsage( 0, 0, 0 );
		if ( isset( $result['usage'] ) && is_array( $result['usage'] ) ) {
			$u     = $result['usage'];
			$in    = (int) ( $u['inputTextTokens']  ?? 0 );
			$out   = (int) ( $u['completionTokens'] ?? 0 );
			$total = (int) ( $u['totalTokens']      ?? $in + $out );
			$usage = new TokenUsage( $in, $out, $total );
		}

		$id    = isset( $result['modelVersion'] ) ? (string) $result['modelVersion'] : '';
		$extra = $result;
		unset( $extra['alternatives'], $extra['usage'] );

		return new GenerativeAiResult( $id, $candidates, $usage, $this->providerMetadata(), $this->metadata(), $extra );
	}

	private function stripCodeFences( string $text ): string {
		$t = trim( $text );
		if ( preg_match( '/^```(?:json)?\s*([\s\S]*?)```$/s', $t, $m ) ) {
			return trim( $m[1] );
		}
		return $text;
	}

	private function altToCandidate( array $alt ): ?Candidate {
		if ( ! isset( $alt['message'] ) ) return null;

		$text = (string) ( $alt['message']['text'] ?? '' );
		$text = $this->stripCodeFences( $text );
		$role = ( isset( $alt['message']['role'] ) && $alt['message']['role'] === 'user' )
			? MessageRoleEnum::user()
			: MessageRoleEnum::model();

		$msg    = new Message( $role, [ new MessagePart( $text ) ] );
		$reason = $this->toFinishReason( $alt['status'] ?? '' );

		return new Candidate( $msg, $reason );
	}

	private function toFinishReason( string $status ): FinishReasonEnum {
		if ( $status === 'ALTERNATIVE_STATUS_TRUNCATED_FINAL' ) return FinishReasonEnum::length();
		if ( $status === 'ALTERNATIVE_STATUS_CONTENT_FILTER'  ) return FinishReasonEnum::error();
		if ( $status === 'ALTERNATIVE_STATUS_ERROR'           ) return FinishReasonEnum::error();
		return FinishReasonEnum::stop();
	}
}
