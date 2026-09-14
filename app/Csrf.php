<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Защита от CSRF.
 */
class Csrf
{
    protected const KEY = 'csrf_token';

    public static function token(): string
    {
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
        if (!$token || !Session::get(self::KEY)) {
            return false;
        }
        return hash_equals((string) Session::get(self::KEY), $token);
    }
}
