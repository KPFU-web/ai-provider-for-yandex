<?php

/**
 * PSR-4 autoloader for the AI Provider for Yandex Cloud package.
 *
 * @since 1.0.0
 *
 * @package WordPress\YandexCloudAiProvider
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'WordPress\\YandexCloudAiProvider\\';
    $baseDir = __DIR__ . '/';

    $length = strlen($prefix);

    if (0 !== strncmp($class, $prefix, $length)) {
        return;
    }

    $relativeClass = substr($class, $length);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// UPDATED by Opencode in 2026-09-18
