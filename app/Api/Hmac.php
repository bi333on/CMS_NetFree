<?php

declare(strict_types=1);

namespace NetFree\Api;

use NetFree\Application;

/**
 * HMAC-подпись запросов: защита от подделки и повторного воспроизведения.
 *
 * Клиент подписывает строку:
 *   {method}\n{path}\n{nonce}\n{timestamp}\n{body_hash}
 * заголовки: X-Nonce, X-Timestamp, X-Signature.
 */
class Hmac
{
    public static function sign(string $secret, string $method, string $path, string $nonce, string $timestamp, string $body): string
    {
        $canonical = strtoupper($method) . "\n"
                   . $path . "\n"
                   . $nonce . "\n"
                   . $timestamp . "\n"
                   . hash('sha256', $body);
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function verify(string $secret, string $method, string $path, string $nonce, string $timestamp, string $body, string $signature): bool
    {
        // Отклоняем слишком старые/будущие запросы (anti-replay по времени).
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > 300) {
            return false;
        }
        $expected = self::sign($secret, $method, $path, $nonce, $timestamp, $body);
        return hash_equals($expected, $signature);
    }
}
