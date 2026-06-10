<?php

/**
 * Plugin Name: ИИ провайдер для Яндекс Клауд
 * Description: Подключает Яндекс GPT к WordPress AI Client. В поле ключа вводить: folder_id:api_key
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-yandex
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

add_action( 'init', function() {

	if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
		return;
	}

	$reg = \WordPress\AiClient\AiClient::defaultRegistry();

	if ( ! $reg->hasProvider( \WordPress\YandexCloudAiProvider\Provider\YandexProvider::class ) ) {
		$reg->registerProvider( \WordPress\YandexCloudAiProvider\Provider\YandexProvider::class );
	}

}, 5 );
