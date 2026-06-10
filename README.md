# AI Provider for Yandex Cloud

Плагин подключает модели Яндекс Клауд к WordPress AI Client.

## Требования

- WordPress с AI Client
- PHP с поддержкой текущей версии WordPress
- Folder ID в Яндекс Клауд
- API-ключ сервисного аккаунта

## Установка

1. Скопируйте папку плагина в `wp-content/plugins/ai-provider-for-yandex`.
2. Активируйте плагин в админке WordPress.
3. Откройте страницу Connectors.
4. Подключите провайдер `Яндекс Клауд`.

## Ключ доступа

В поле API Key нужно указать оба значения одной строкой:

```text
folder_id:api_key
```

`folder_id` используется для формирования `modelUri`, а `api_key` отправляется в Яндекс Клауд как `Authorization: Api-Key`.

## Поддерживаемые модели

Текст:

- YandexGPT Pro
- YandexGPT Lite
- YandexGPT Pro 32K
- Llama 3.3 70B
- Llama 3.1 8B
- Qwen3 235B
- GPT OSS 120B
- GPT OSS 20B

Изображения:

- YandexART
- YandexART 2.0
