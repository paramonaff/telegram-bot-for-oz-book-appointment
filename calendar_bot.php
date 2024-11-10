<?php
// Подключаем конфигурационный файл
require_once __DIR__ . '/config.php';

// Логирование функций
$logFile = 'telegram_bot_log.txt';

/**
 * Функция для логирования сообщений
 * @param string $message
 */
$log = function ($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Получаем данные от Telegram
$data = json_decode(file_get_contents("php://input"), true);

$log("Received content: " . json_encode($data));

/**
 * Запрос в Telegram
 * @param string $method
 * @param array $fields
 * @return mixed
 */
$query = function ($method, $fields = []) use ($bot_token, $log) {
    $ch = curl_init("https://api.telegram.org/bot" . $bot_token . "/" . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => count($fields),
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 10
    ]);
    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        $log("Telegram API CURL Error: $error");
    } else {
        $result = json_decode($result, true);
        if ($result === null || !isset($result['ok'])) {
            $log("Telegram API Response Error: " . json_encode($result));
        }
    }
    curl_close($ch);

    $log("Telegram API Query: $method - " . json_encode($fields) . " - Result: " . json_encode($result));

    return $result;
};

/**
 * Вывод уведомления
 * @param string $cbq_id
 * @param string|null $text
 */
$notice = function ($cbq_id, $text = null) use ($query, $log) {
    $data = [
        "callback_query_id" => $cbq_id,
        "text" => $text,
        "show_alert" => false
    ];
    $result = $query("answerCallbackQuery", $data);

    $log("Telegram API Query: answerCallbackQuery - " . json_encode($data) . " - Result: " . json_encode($result));
};

/**
 * Переопределение номера дня недели
 * @param DateTime $date
 * @return int
 */
$getNumDayOfWeek = function ($date) {
    $day = $date->format("w");
    return ($day == 0) ? 6 : $day - 1;
};

/**
 * Получение массива дней, разбитых по неделям
 * @param int $month
 * @param int $year
 * @return array
 * @throws Exception
 */
$getDays = function ($month, $year) use ($getNumDayOfWeek) {
    $date = new DateTime("$year-$month-01");
    $days = [];
    $line = 0;
    for ($i = 0; $i < $getNumDayOfWeek($date); $i++) {
        $days[$line][] = "-";
    }
    while ($date->format("m") == $month) {
        $days[$line][] = $date->format("d");
        if ($getNumDayOfWeek($date) % 7 == 6) {
            $line += 1;
        }
        $date->modify('+1 day');
    }
    if ($getNumDayOfWeek($date) != 0) {
        for ($i = $getNumDayOfWeek($date); $i < 7; $i++) {
            $days[$line][] = "-";
        }
    }
    return $days;
};

/**
 * Получение встреч на определенный день из постов типа 'clients'
 * @param int $day
 * @param int $month
 * @param int $year
 * @return array
 */
$getAppointmentsForDay = function ($day, $month, $year) use ($log) {
    // Форматируем дату для сравнения
    $date = sprintf('%02d.%02d.%04d', $day, $month, $year);

    // Получаем посты из WordPress
    require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
    $args = array(
        'post_type' => 'clients',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'oz_start_date_field_id',
                'value' => $date,
                'compare' => '='
            )
        )
    );

    $clients_query = new WP_Query($args);
    $appointments = [];

    if ($clients_query->have_posts()) {
        while ($clients_query->have_posts()) {
            $clients_query->the_post();
            $title = get_the_title();
            $oz_date_td = get_post_meta(get_the_ID(), 'oz_start_date_field_id', true);
            $oz_time_td = get_post_meta(get_the_ID(), 'oz_time_rot', true);
            $idServ = get_post_meta(get_the_ID(), 'oz_uslug_set', true);
            $w_time = get_post_meta($idServ, 'oz_serv_time', true);

            // Рассчитываем время окончания встречи
            $startTime = new DateTime($oz_time_td);
            $endTime = clone $startTime;
            $endTime->modify("+$w_time minutes");

            $appointments[] = [
                'title' => $title,
                'date' => $oz_date_td,
                'time' => $oz_time_td,
                'service_time' => $w_time,
                'end_time' => $endTime->format('H:i')
            ];
        }
    } else {
        $log("No appointments found for $date");
    }

    wp_reset_postdata();

    // Сортировка встреч по времени
    usort($appointments, function ($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });

    // Подсветим ближайшую запись к текущему времени (если есть записи)
    // Устанавливаем временную зону в Московскую
    date_default_timezone_set('Europe/Moscow');

    // Получаем текущее время в формате Unix timestamp (Московское время)
    $currentTime = time();

    foreach ($appointments as &$appointment) {
        $appointment['highlighted'] = false; // Сначала устанавливаем для всех записей как не подсвеченные

        // Преобразуем время записи в объект DateTime с учетом временной зоны
        $appointmentTime = new DateTime($appointment['time'], new DateTimeZone('Europe/Moscow'));
        $appointmentTimeUnix = $appointmentTime->getTimestamp();

        // Определяем тип дня
        $today = new DateTime();
        $today_day = intval($today->format('d'));
        $today_month = intval($today->format('m'));
        $today_year = intval($today->format('Y'));

        if ($year < $today_year || ($year == $today_year && $month < $today_month) || ($year == $today_year && $month == $today_month && $day < $today_day)) {
            // Прошедший день
            continue; // Не подсвечиваем встречи в прошедшем дне
        } elseif ($year == $today_year && $month == $today_month && $day == $today_day) {
            // Текущий день
            if ($appointmentTimeUnix >= $currentTime) {
                $appointment['highlighted'] = true; // Подсвечиваем текущую или будущую встречу в текущем дне
                break; // Прерываем цикл, как только найдена первая подходящая встреча
            }
        } elseif ($year > $today_year || ($year == $today_year && $month > $today_month) || ($year == $today_year && $month == $today_month && $day > $today_day)) {
            // Будущий день
            if ($appointmentTimeUnix > $currentTime) {
                $appointment['highlighted'] = true; // Подсвечиваем первую встречу после последней прошедшей в текущем месяце
                break; // Прерываем цикл, как только найдена первая подходящая встреча
            }
        }
    }

    return $appointments;
};

/**
 * Вывод календаря
 * @param int $month
 * @param int $year
 * @param int $chat_id
 * @param string|null $cbq_id
 * @param string|null $message_id
 * @param int|null $selected_day
 */
$viewCal = function ($month, $year, $chat_id, $cbq_id = null, $message_id = null, $selected_day = null) use ($getDays, $getAppointmentsForDay, $notice, $query, $log) {
    $dayLines = $getDays($month, $year);
    $current = new DateTime("$year-$month-01");
    $current_info = $current->format("m_Y");

    // Получаем текущую дату
    $today = new DateTime();
    $today_day = intval($today->format("d"));
    $today_month = intval($today->format("m"));
    $today_year = intval($today->format("Y"));

    // Кнопки навигации календаря
    $prevMonth = (clone $current)->modify('-1 month')->format('m_Y');
    $nextMonth = (clone $current)->modify('+1 month')->format('m_Y');
    $buttons = [
        [
            ["text" => "<<<", "callback_data" => "cal_$prevMonth"],
            ["text" => $current->format('F Y'), "callback_data" => "info_$current_info"],
            ["text" => ">>>", "callback_data" => "cal_$nextMonth"]
        ]
    ];
    // Добавляем кнопку "Ближайшая запись"
    $buttons[] = [
        ["text" => "Ближайшая запись", "callback_data" => "nearest_appointment"]
    ];

    foreach ($dayLines as $days) {
        $row = [];
        foreach ($days as $day) {
            if ($day == "-") {
                $row[] = ["text" => " ", "callback_data" => "inline"];
            } else {
                $dateString = sprintf("%02d", $day);
                $dayText = $dateString;

                // Подсветка текущей даты бирюзовым цветом
                if ($today_year == $year && $today_month == $month && $today_day == $day) {
                    $dayText = "($dayText)";
                }

                // Обозначение выбранной даты скобками
                if ($selected_day && $selected_day == $day) {
                    $dayText = "[$dayText]";
                }

                $row[] = ["text" => $dayText, "callback_data" => "day_" . $day . "_" . $current_info];
            }
        }
        $buttons[] = $row;
    }

    // Если выбран день, добавляем встречи в текст календаря
    $calendarText = "<b>Календарь:</b>\n\n" . $current->format("F Y");
    if ($selected_day) {
        $appointments = $getAppointmentsForDay($selected_day, $month, $year);
        $calendarText .= "\n\n<b>Запись на $selected_day.$month.$year:</b>\n";
        if (empty($appointments)) {
            $calendarText .= "Нет записи.";
        } else {
            foreach ($appointments as $appointment) {
                $timePrefix = '';
                if ($appointment['highlighted']) {
                    $timePrefix = '>>> '; // Подсветка ближайшей записи
                }
                $calendarText .= $timePrefix . $appointment['time'] . " - " . $appointment['end_time'] . " - " . $appointment['title'] . " (" . $appointment['service_time'] . " минут)\n";
            }
        }
    } //elseif ($selected_day === "nearest_appointment") {
        // Получаем текущую дату
    //    $today = new DateTime();
    //    $today_day = intval($today->format("d"));
    //    $today_month = intval($today->format("m"));
    //    $today_year = intval($today->format("Y"));

        // Подсвечиваем ближайшую запись
    //    $nearest_appointment_found = false;
    //    foreach ($dayLines as $days) {
    //        foreach ($days as $day) {
    //            if ($day != "-" && !is_null($day)) {
    //                $appointments = $getAppointmentsForDay($day, $month, $year);
    //                foreach ($appointments as $appointment) {
    //                    if ($appointment['highlighted']) {
    //                        $nearest_appointment_found = true;
    //                        $calendarText = "<b>Календарь:</b>\n\n" . $current->format("F Y");
    //                        $calendarText .= "\n\n<b>Запись на $day.$month.$year:</b>\n";
    //                        $calendarText .= '>>> ' . $appointment['time'] . " - " . $appointment['end_time'] . " - " . $appointment['title'] . " (" . $appointment['service_time'] . " минут)\n";
    //                    }
    //                }
    //            }
    //        }
    //    }
    //}
    // Формируем данные для отправки сообщения
    $data = [
        "chat_id" => $chat_id,
        "text" => $calendarText,
        "parse_mode" => "html",
        "reply_markup" => json_encode(["inline_keyboard" => $buttons])
    ];

    // Если есть message_id, то редактируем сообщение, иначе отправляем новое
    if (!is_null($message_id)) {
        $notice($cbq_id);
        $data["message_id"] = $message_id;
        $result = $query("editMessageText", $data);
        $log("Telegram API Query: editMessageText - " . json_encode($data) . " - Result: " . json_encode($result));
    } else {
        $result = $query("sendMessage", $data);
        $log("Telegram API Query: sendMessage - " . json_encode($data) . " - Result: " . json_encode($result));
    }

    // Логирование отправки календаря
    $log("Calendar sent: " . $current->format("F Y") . " - Chat ID: $chat_id");
};

// Обработка входящих данных от Telegram
if (isset($data['message'])) {
    $chat_id = $data['message']['chat']['id']; // Получаем id чата
    if (isset($data['message']['text'])) {
        // Проверяем текст сообщения
        if ($data['message']['text'] == "/start") {
            // Получаем текущую дату
            $now_date = getdate();
            // Выводим календарь на текущий месяц
            $viewCal($now_date['mon'], $now_date['year'], $chat_id);

            $log("Handling /start command");
        }
    }
} elseif (isset($data['callback_query'])) {
    $chat_id = $data['callback_query']['message']['chat']['id']; // Получаем id чата из callback_query
    $cbq_id = $data['callback_query']['id']; // Получаем id callback_query
    $c_data = $data['callback_query']['data']; // Получаем данные из callback_query
    $params = explode("_", $c_data); // Разбиваем данные на параметры

    $log("Обработка callback_query: $cbq_id - Данные: $c_data");
    $log("Callback query data: $c_data");

    if ($params[0] == "cal") {
        // Если данные для календаря, выводим календарь
        $viewCal($params[1], $params[2], $chat_id, $cbq_id, $data['callback_query']['message']['message_id']);

        $log("Handling callback query for calendar update");
    } elseif ($params[0] == "day") {
        // Если данные для дня, выводим встречи на этот день
        $day = $params[1];
        $month = $params[2];
        $year = $params[3];
        $viewCal($month, $year, $chat_id, $cbq_id, $data['callback_query']['message']['message_id'], $day);

        $log("Handling callback query for day appointments");
    } elseif ($c_data == "nearest_appointment") {
        // Обработка для кнопки "Ближайшая запись"
        $notice($cbq_id, "Функционал 'Ближайшая запись' пока не реализован");
        //$viewCal(0, 0, $chat_id, $c_data, $data['callback_query']['message']['message_id'], "nearest_appointment");

        $log("Handling callback query for nearest appointment");
    } else {
        // Другие случаи, отправляем общее уведомление
        $notice($cbq_id, "Это уведомление для бота");
        $log("Notice sent for callback_query_id: $cbq_id - Text: Это уведомление для бота");
    }
}
?>
