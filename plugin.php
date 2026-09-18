<?php
/**
 * Plugin Name: ИИ провайдер для Яндекс Клауд
 * Plugin URI: https://github.com/KPFU-web/ai-provider-for-yandex
 * Description: Подключает модели Яндекс Клауд к WordPress AI Client. В поле ключа вводить: folder_id:api_key
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Version: 1.0.1
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-yandex
 *
 * @package WordPress\YandexCloudAiProvider
 */

declare(strict_types=1);

namespace WordPress\YandexCloudAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\YandexCloudAiProvider\Provider\YandexProvider;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the Yandex Cloud AI provider with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(YandexProvider::class)) {
        return;
    }

    $registry->registerProvider(YandexProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

// UPDATED by Opencode in 2026-09-18
