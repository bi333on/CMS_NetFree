<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Улучшенная защита от CSRF с отладкой.
 */
class CsrfImproved
{
    protected const KEY = 'csrf_token';
    protected const LIFETIME = 7200; // 2 часа

    public static function token(): string
    {
        self::ensureSession();

        $tokenData = Session::get(self::KEY);

        // Проверяем, существует ли токен и не истёк ли он
        if (!$tokenData || !is_array($tokenData) || !isset($tokenData['token'], $tokenData['expires'])) {
            return self::regenerateToken();
        }

        // Проверяем срок действия
        if (time() > $tokenData['expires']) {
            return self::regenerateToken();
        }

        return $tokenData['token'];
    }

    protected static function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::KEY, [
            'token' => $token,
            'expires' => time() + self::LIFETIME,
        ]);
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token, bool $debug = false): bool
    {
        self::ensureSession();

        if (!$token) {
            if ($debug) {
                error_log('[CSRF] Token is empty');
            }
            return false;
        }

        $tokenData = Session::get(self::KEY);

        if (!$tokenData || !is_array($tokenData) || !isset($tokenData['token'])) {
            if ($debug) {
                error_log('[CSRF] No token in session');
            }
            return false;
        }

        // Проверяем срок действия
        if (time() > ($tokenData['expires'] ?? 0)) {
            if ($debug) {
                error_log('[CSRF] Token expired');
            }
            return false;
        }

        $valid = hash_equals($tokenData['token'], $token);

        if ($debug && !$valid) {
            error_log('[CSRF] Token mismatch. Expected: ' . substr($tokenData['token'], 0, 10) . '..., Got: ' . substr($token, 0, 10) . '...');
        }

        return $valid;
    }

    protected static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }
    }

    /**
     * Отладочная информация.
     */
    public static function debug(): array
    {
        self::ensureSession();

        $tokenData = Session::get(self::KEY);

        return [
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'session_id' => session_id(),
            'token_exists' => !empty($tokenData),
            'token_preview' => $tokenData ? substr($tokenData['token'] ?? '', 0, 10) . '...' : 'none',
            'expires_at' => $tokenData['expires'] ?? null,
            'expires_in' => ($tokenData['expires'] ?? 0) - time(),
            'is_expired' => ($tokenData['expires'] ?? 0) < time(),
        ];
    }
}
