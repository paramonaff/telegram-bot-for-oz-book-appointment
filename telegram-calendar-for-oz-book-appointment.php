<?php
/**
 * Plugin Name: Telegram Calendar For Oz Book Appointment
 * Plugin URI: https://wannawake.ru/
 * Description: Плагин для интеграции Telegram-бота с календарем.
 * Version: 0.1
 * Author: Ugen Pon
 * Author URI: https://wannawake.ru/
 * License: GPL2
 */

error_reporting(E_ALL); 
ini_set('display_errors', 1);
// Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
 // Функция для логирования сообщений и ошибок
function logMessage($message) {
    $file = __DIR__ . '/telegram_bot_error.txt';
    file_put_contents($file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND | LOCK_EX);
}
// Подключаем основной файл с логикой бота
require_once __DIR__ . '/telegram_bot.php';

// Хук активации плагина
register_activation_hook(__FILE__, function() {
    logMessage("Telegram Calendar For Oz Book Appointment Plugin activated.");
    
    // Проверяем доступность wp_remote_post
    if (function_exists('wp_remote_post')) {
        logMessage("wp_remote_post is available.");
    } else {
        logMessage("wp_remote_post is NOT available.");
        
        // Пример cURL запроса
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['test' => 'test']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            logMessage("cURL Error: " . curl_error($ch));
        } else {
            logMessage("cURL request successful. Response: " . $response);
        }
        curl_close($ch);
    }

    // Добавляем отладочное сообщение перед подключением webhook
    logMessage("Trying to require 'set_tg_webhook.php'");
    
    try {
        require_once __DIR__ . '/set_tg_webhook.php';
        logMessage("Webhook setup script executed successfully.");
    } catch (Exception $e) {
        logMessage("Error during webhook setup: " . $e->getMessage());
        wp_die("Error during webhook setup: " . $e->getMessage());  // Остановим выполнение, чтобы увидеть ошибку
    }
});

// Хук деактивации плагина
register_deactivation_hook(__FILE__, function() {
    logMessage("Telegram Calendar For Oz Book Appointment Plugin deactivated.");
    
    // Удаляем вебхук при деактивации плагина
    try {
        require_once __DIR__ . '/stop_tg_webhook.php';
    } catch (Exception $e) {
        error_log("Error during webhook removal: " . $e->getMessage());
    }
});