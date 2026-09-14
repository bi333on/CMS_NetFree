<?php
/**
 * Глобальные функции-хелперы (аналог функций WordPress).
 */

declare(strict_types=1);

use NetFree\Application;
use NetFree\Csrf;

if (!function_exists('app')) {
    function app(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return Application::getInstance()->config->get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return rtrim((string) config('site.url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Application::getInstance()->auth->user();
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return Application::getInstance()->auth->check();
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
    {
        Application::getInstance()->hooks->addAction($tag, $cb, $priority, $accepted_args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
    {
        Application::getInstance()->hooks->addFilter($tag, $cb, $priority, $accepted_args);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void
    {
        Application::getInstance()->hooks->doAction($tag, ...$args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args)
    {
        return Application::getInstance()->hooks->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('get_header')) {
    function get_header(array $data = []): void
    {
        echo Application::getInstance()->theme->render('header', $data);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(array $data = []): void
    {
        echo Application::getInstance()->theme->render('footer', $data);
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_get')) {
    function flash_get(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace([' ', '_'], '-', $text);
        $text = preg_replace('/[^a-z0-9\-а-яё]/u', '', $text);
        $text = preg_replace('/-+/u', '-', $text);
        return trim($text, '-');
    }
}

if (!function_exists('format_date')) {
    /**
     * Форматирует дату в человекочитаемый вид (русская локаль).
     */
    function format_date(?string $datetime, string $format = 'd.m.Y'): string
    {
        if (!$datetime) {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }

        $months = [
            'Jan' => 'янв', 'Feb' => 'фев', 'Mar' => 'мар', 'Apr' => 'апр',
            'May' => 'май', 'Jun' => 'июн', 'Jul' => 'июл', 'Aug' => 'авг',
            'Sep' => 'сен', 'Oct' => 'окт', 'Nov' => 'ноя', 'Dec' => 'дек',
        ];

        $out = date($format, $ts);
        if (str_contains($format, 'M')) {
            $out = str_replace(array_keys($months), array_values($months), $out);
        }
        return $out;
    }
}

