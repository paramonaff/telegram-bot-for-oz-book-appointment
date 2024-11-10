<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем WordPress
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

// Получаем дату из параметра запроса
$specified_date = isset($_GET['date']) ? $_GET['date'] : date('d.m.Y');

// Преобразуем указанную дату в объект DateTime
$specified_date_obj = DateTime::createFromFormat('d.m.Y', $specified_date);
if (!$specified_date_obj) {
    echo 'Неверный формат даты. Пожалуйста, используйте формат дд.мм.гггг.';
    exit;
}
// Преобразуем объект DateTime в строку формата d.m.Y
$specified_date_formatted = $specified_date_obj->format('d.m.Y');

// Сначала получаем посты из категории 'clients'
$args = array(
    'post_type' => 'clients',
    'posts_per_page' => -1 // Получаем все посты из категории
);

$clients_query = new WP_Query($args);

// Проверяем, есть ли посты
if ($clients_query->have_posts()) {
    // Флаг для проверки наличия постов на указанную дату
    $posts_found = false;

    // Открываем файл test_tg_appointment.html для записи в UTF-8
    $file = fopen('test_tg_appointment.html', 'w');
    
    // Проверяем, успешно ли открыт файл
    if ($file !== false) {
        // Записываем начальную часть HTML-файла с указанием кодировки UTF-8
        fwrite($file, '<!DOCTYPE html>');
        fwrite($file, '<html lang="ru">');
        fwrite($file, '<head>');
        fwrite($file, '<meta charset="UTF-8">'); // Установка кодировки UTF-8
        fwrite($file, '<title>Тестовый файл</title>');
        fwrite($file, '</head>');
        fwrite($file, '<body>');

        // Начинаем таблицу
        fwrite($file, '<table>');
        // Записываем заголовки таблицы
        fwrite($file, '<tr><th>Title</th><th>Oz Date TD</th><th>Oz Time TD</th><th>Service Time</th></tr>');

        // Цикл по всем постам
        while ($clients_query->have_posts()) {
            $clients_query->the_post();
            // Получаем значения метаполей
            $title = get_the_title();
            $oz_date_td = get_post_meta(get_the_ID(), 'oz_start_date_field_id', true);
            $oz_time_td = get_post_meta(get_the_ID(), 'oz_time_rot', true);
            $idServ = get_post_meta(get_the_ID(), 'oz_uslug_set', true);
            $w_time = get_post_meta($idServ, 'oz_serv_time', true);

            // Проверяем, совпадает ли дата поста с указанной датой
            if ($oz_date_td === $specified_date_formatted) {
                $posts_found = true;

                // Записываем строку таблицы для текущего поста
                fwrite($file, '<tr>');
                fwrite($file, '<td>' . $title . '</td>'); // Заголовок поста
                fwrite($file, '<td>' . $oz_date_td . '</td>'); // Значение метаполя "oz_start_date_field_id"
                fwrite($file, '<td>' . $oz_time_td . '</td>'); // Значение метаполя "oz_time_rot"
                fwrite($file, '<td>' . $w_time . '</td>'); // Время услуги "w_time"
                fwrite($file, '</tr>');
            }
        }

        // Заканчиваем таблицу
        fwrite($file, '</table>');

        // Записываем закрывающую часть HTML-файла
        fwrite($file, '</body></html>');

        // Закрываем файл
        fclose($file);

        if ($posts_found) {
            echo 'Данные успешно записаны в файл.';
        } else {
            echo 'Нет постов в категории "clients" на указанную дату.';
        }
    } else {
        echo 'Не удалось открыть файл для записи.';
    }
} else {
    echo 'Нет постов в категории "clients".';
}

// Сбрасываем запрос
wp_reset_postdata();
?>
