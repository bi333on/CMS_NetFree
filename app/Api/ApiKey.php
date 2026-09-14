<?php

declare(strict_types=1);

namespace NetFree\Api;

use NetFree\Database;

/**
 * API-ключи: генерация, проверка, отзыв.
 */
class ApiKey
{
    public static function generate(string $name, string $permissions = 'read'): string
    {
        $key = 'ck_' . bin2hex(random_bytes(24));
        Database::insert('api_keys', [
            'name'        => $name,
            'key_hash'    => hash('sha256', $key),
            'permissions' => $permissions,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return $key;
    }

    public static function verify(string $key): ?array
    {
        $row = Database::first('SELECT * FROM api_keys WHERE key_hash = ?', [hash('sha256', $key)]);
        return $row ?: null;
    }

    public static function revoke(int $id): int
    {
        return Database::delete('api_keys', ['id' => $id]);
    }

    public static function all(): array
    {
        return Database::select('SELECT id, name, permissions, created_at FROM api_keys ORDER BY id DESC');
    }
}
