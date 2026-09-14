<?php

declare(strict_types=1);

namespace NetFree\Api;

use Exception;

/**
 * Минимальный JWT (HS256), без внешних зависимостей.
 */
class Jwt
{
    public static function encode(array $payload, string $secret, int $ttl = 3600): string
    {
        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttl,
        ]);

        $header  = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64(json_encode($payload));
        $sig     = self::sign($header . '.' . $payload, $secret);

        return $header . '.' . $payload . '.' . $sig;
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $sig] = $parts;

        if (!hash_equals(self::sign($header . '.' . $payload, $secret), $sig)) {
            return null;
        }

        $data = json_decode(self::unb64($payload), true);
        if (!is_array($data)) {
            return null;
        }
        if (($data['exp'] ?? 0) < time()) {
            return null;
        }
        return $data;
    }

    protected static function sign(string $input, string $secret): string
    {
        return self::b64(hash_hmac('sha256', $input, $secret, true));
    }

    protected static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    protected static function unb64(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'));
    }
}
