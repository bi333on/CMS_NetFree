<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Защита от CSRF с улучшенной отладкой.
 */
class Csrf
{
    protected const KEY = 'csrf_token';

    public static function token(): string
    {
        // Убедимся что сессия активна
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }

        if (!Session::get(self::KEY)) {
            Session::set(self::KEY, bin2hex(random_bytes(32)));
        }
        return (string) Session::get(self::KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        // Убедимся что сессия активна
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }

        if (!$token || !Session::get(self::KEY)) {
            // Отладка: логируем проблему
            if (defined('NF_DEBUG') && NF_DEBUG) {
                error_log('[CSRF] Token empty or not in session. Token: ' . ($token ? 'present' : 'missing') . ', Session token: ' . (Session::get(self::KEY) ? 'present' : 'missing'));
            }
            return false;
        }

        $sessionToken = (string) Session::get(self::KEY);
        $isValid = hash_equals($sessionToken, $token);

        // Отладка неудачной проверки
        if (!$isValid && defined('NF_DEBUG') && NF_DEBUG) {
            error_log('[CSRF] Mismatch! Expected: ' . substr($sessionToken, 0, 10) . '..., Got: ' . substr($token, 0, 10) . '...');
        }

        return $isValid;
    }

    /**
     * Получить отладочную информацию (только для разработки).
     */
    public static function debug(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }

        return [
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'session_id' => session_id(),
            'token_in_session' => !empty(Session::get(self::KEY)),
            'token_preview' => Session::get(self::KEY) ? substr((string) Session::get(self::KEY), 0, 10) . '...' : 'none',
            'current_token' => self::token(),
        ];
    }
}

