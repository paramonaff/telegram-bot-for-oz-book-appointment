<?php
require_once __DIR__ . '/config.php'; // Загружается $bot_token из конфигурации
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'; // Подключение WordPress

// Формируем запрос для удаления вебхука
$response = wp_remote_post( "https://api.telegram.org/bot$bot_token/deleteWebhook" );

// Проверяем ответ
if (is_wp_error($response)) {
    echo 'Ошибка при удалении вебхука: ' . $response->get_error_message();
} else {
    echo 'Ответ от Telegram: ' . wp_remote_retrieve_body($response);
}
?>
