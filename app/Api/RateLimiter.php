<?php

declare(strict_types=1);

namespace NetFree\Api;

use NetFree\Database;
use NetFree\Session;

/**
 * Простой rate limiter на основе сессии/БД.
 * В MVP — подсчёт в сессии (админ) и по ключу/IP через таблицу api_rate_limits.
 */
class RateLimiter
{
    public static function tooMany(string $bucket, int $limit, int $window): bool
    {
        $now = time();
        $cutoff = date('Y-m-d H:i:s', $now - $window);

        $key = $bucket;
        Database::execute(
            'DELETE FROM api_rate_limits WHERE bucket = ? AND created_at < ?',
            [$key, $cutoff]
        );

        $count = (int) (Database::first(
            'SELECT COUNT(*) AS c FROM api_rate_limits WHERE bucket = ?',
            [$key]
        )['c'] ?? 0);

        if ($count >= $limit) {
            return true;
        }

        Database::insert('api_rate_limits', [
            'bucket'     => $key,
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);
        return false;
    }
}
