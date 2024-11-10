<?php
// Подключаем файл конфигурации
require_once  __DIR__ . '/config.php';

$ch = curl_init("https://api.telegram.org/bot$bot_token/getWebhookInfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
