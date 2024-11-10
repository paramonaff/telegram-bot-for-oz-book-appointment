<?php
require_once __DIR__ . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'; // Подключение WordPress

// Получаем URL для вебхука
$webhook_url = plugin_dir_url(__FILE__) . 'telegram_bot.php';

// Формируем запрос для установки вебхука
$response = wp_remote_post( "https://api.telegram.org/bot$bot_token/setWebhook", [
    'body' => [
        'url' => $webhook_url
    ]
]);

// Проверяем ответ
if (is_wp_error($response)) {
    echo 'Ошибка при установке вебхука: ' . $response->get_error_message();
} else {
    echo 'Ответ от Telegram: ' . wp_remote_retrieve_body($response);
}
?>