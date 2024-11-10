<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

// Логирование метода запроса и заголовков для отладки
logMessage("Request method: " . $_SERVER['REQUEST_METHOD']);
logMessage("Request headers: " . json_encode(getallheaders()));
logMessage("Received raw input: " . file_get_contents("php://input"));

// Логирование всего входящего запроса для проверки
file_put_contents('log.txt', print_r($_SERVER, true), FILE_APPEND);
file_put_contents('log.txt', file_get_contents('php://input') . "\n", FILE_APPEND);

// Функция для логирования сообщений и ошибок
function logMessage($message) {
    $file = __DIR__ . '/telegram_bot_error.txt';
    file_put_contents($file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND | LOCK_EX);
}

// Функция для отправки сообщения через API Telegram
function sendMessage($chat_id, $text, $bot_token) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    query("sendMessage", $data, $bot_token);
}

// Функция для выполнения запросов к API Telegram
function query($method, $data, $bot_token) {
    $url = "https://api.telegram.org/bot$bot_token/$method";
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Добавим проверку на ошибки при выполнении запроса
    if ($result === false) {
        $error_message = "Error querying Telegram API: " . error_get_last()['message'];
        logMessage($error_message);
        return false;
    }

    // Добавим проверку HTTP-статуса ответа Telegram
    $response = json_decode($result, true);
    if (!$response || !$response['ok']) {
        $error_message = "Telegram API error: " . $response['description'];
        logMessage($error_message);
        return false;
    }

    return $result;
}

// Получаем данные от Telegram
$content = file_get_contents("php://input");
//logMessage("Received content: " . $content);
logMessage("Raw input content: " . var_export($content, true));

$data = json_decode($content, true);
// Проверка на наличие ошибок декодирования JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage("JSON decode error: " . json_last_error_msg());
}

if (!$data) {
    logMessage("Failed to decode JSON from input.");
    exit;
}

$message = $data['message'] ?? null;
$callback_query = $data['callback_query'] ?? null;

if ($message) {
    $chat_id = $message['chat']['id'] ?? null;
    $text = $message['text'] ?? null;

    if (!$chat_id || !$text) {
        logMessage("Invalid message data. Message: " . print_r($message, true));
        exit;
    }

    // Обработка команды /event
    if (strpos($text, '/event') === 0) {
        require_once  __DIR__ . '/get_latest_appointment.php';
        $latest_appointment = getLatestAppointment();
        $response = "Привет! Это календарь " . get_bloginfo('name') . ".\n\n";
        if (!empty($latest_appointment)) {
            $response .= "Последняя запись на встречу:\n";
            $response .= "Название: " . $latest_appointment['title'] . "\n";
            $response .= "Дата: " . $latest_appointment['oz_date_td'] . "\n";
            $response .= "Время: " . $latest_appointment['oz_time_td'] . "\n";
            $response .= "Продолжительность: " . $latest_appointment['service_time'] . " минут\n";
        } else {
            $response .= "Нет запланированных встреч.";
        }
        sendMessage($chat_id, $response, $bot_token);
    }

    // Обработка команды /menu
    if (strpos($text, '/menu') === 0) {
        $response = "Меню:\n";
        $response .= "/event - Показать последнюю запись на встречу\n";
        $response .= "/calendar - Показать календарь";
        sendMessage($chat_id, $response, $bot_token);
    }

    // Обработка команды /calendar
    if (strpos($text, '/calendar') === 0) {
        require_once  __DIR__ . '/calendar_bot.php';
        logMessage("Handling /calendar command");

        // Получаем текущую дату
        $now_date = getdate();
        logMessage("Current date: " . print_r($now_date, true));

        // Выводим календарь текущего месяца
        $viewCal($now_date['mon'], $now_date['year'], $chat_id);
        logMessage("Called viewCal with parameters: month=" . $now_date['mon'] . ", year=" . $now_date['year'] . ", chat_id=" . $chat_id);
    }
} elseif ($callback_query) {
    logMessage("Handling callback query");

    $query_data = $callback_query['data'];
    $chat_id = $callback_query['message']['chat']['id'] ?? null;
    $cbq_id = $callback_query['id'];
    $message_id = $callback_query['message']['message_id'];

    logMessage("Callback query data: " . $query_data);

    require_once  __DIR__ . '/calendar_bot.php';

    $queryData = explode("_", $query_data);
    if (isset($queryData[0])) {
        if ($queryData[0] == "cal") {
            $date = explode("_", $queryData[1]);
            if (count($date) == 2) {
                $month = $date[0];
                $year = $date[1];
                $viewCal($month, $year, $chat_id, $cbq_id, $message_id);
            } else {
                logMessage("Invalid callback data format: " . $query_data);
            }
        } elseif ($queryData[0] == "day") {
            if (isset($queryData[1])) {
                $notice($cbq_id, "Day selected: " . $queryData[1]);
            } else {
                logMessage("Invalid callback data format: " . $query_data);
            }
        } else {
            logMessage("Unrecognized callback query data: " . $query_data);
        }
    } else {
        logMessage("Invalid callback data format: " . $query_data);
    }
} else {
    logMessage("No valid message or callback query found in request.");
}
?>
