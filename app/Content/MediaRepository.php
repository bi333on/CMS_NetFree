<?php

declare(strict_types=1);

namespace NetFree\Content;

use NetFree\Database;

/**
 * Репозиторий медиафайлов (библиотека файлов).
 */
class MediaRepository
{
    public static function all(): array
    {
        return Database::select('SELECT * FROM media ORDER BY created_at DESC');
    }

    public static function byId(int $id): ?array
    {
        return Database::first('SELECT * FROM media WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('media', $data);
    }

    public static function delete(int $id): int
    {
        return Database::delete('media', ['id' => $id]);
    }
}
