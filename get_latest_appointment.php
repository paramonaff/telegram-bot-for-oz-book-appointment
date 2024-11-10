<?php
// Подключаем WordPress
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

// Функция для получения последней записи на встречу
function getLatestAppointment() {
    // Аргументы для запроса WP_Query
    $args = array(
        'post_type' => 'clients',
        'posts_per_page' => 1, // Получаем только последнюю запись
        'orderby' => 'date',
        'order' => 'DESC'
    );

    // Выполняем запрос
    $clients_query = new WP_Query($args);

    // Проверяем, есть ли посты
    if ($clients_query->have_posts()) {
        $clients_query->the_post();

        // Получаем значения метаполей для последнего поста
        $title = get_the_title();
        $oz_date_td = get_post_meta(get_the_ID(), 'oz_start_date_field_id', true);
        $oz_time_td = get_post_meta(get_the_ID(), 'oz_time_rot', true);
        $idServ = get_post_meta(get_the_ID(), 'oz_uslug_set', true);

        // Если ID услуги существует, получаем время
        $w_time = $idServ ? get_post_meta($idServ, 'oz_serv_time', true) : null;

        // Формируем массив данных для последней встречи
        $appointment_data = array(
            'title' => $title,
            'oz_date_td' => $oz_date_td,
            'oz_time_td' => $oz_time_td,
            'service_time' => $w_time
        );

        // Сбрасываем запрос
        wp_reset_postdata();

        return $appointment_data;
    } else {
        wp_reset_postdata();  // Сбрасываем пост данные, если нет постов
        return null; // Если нет встреч, возвращаем null
    }
}
?>
