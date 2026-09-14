<?php
/**
 * Плагин SEO Basic — официальный (закрытый) плагин NetFree.
 * Демонстрирует расширение функционала через хуки.
 */

declare(strict_types=1);

use NetFree\Content\PageRepository;

// Добавляем meta description и OG-теги в <head> публичной части.
add_action('netfree.head', function () {
    global $netfree_current_page;
    if (empty($netfree_current_page)) {
        return;
    }
    $meta = $netfree_current_page['meta_desc'] ?? '';
    $title = $netfree_current_page['title'] ?? '';
    echo "\n" . '<meta name="description" content="' . e($meta) . '">';
    echo "\n" . '<meta property="og:title" content="' . e($title) . '">';
    echo "\n" . '<meta property="og:description" content="' . e($meta) . '">';
});

// Сохраняем текущую страницу в глобал для использования в хуке head.
add_filter('netfree.page_data', function (array $page) {
    global $netfree_current_page;
    $netfree_current_page = $page;
    return $page;
});

// Пример фильтрации контента.
add_filter('netfree.page_content', function (string $content, array $page) {
    return $content;
}, 10, 2);
