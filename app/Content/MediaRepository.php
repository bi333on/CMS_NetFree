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

    /**
     * Поиск по имени файла с пагинацией (для модала медиа-библиотеки).
     */
    public static function search(string $q = '', int $limit = 0, int $offset = 0): array
    {
        $sql    = 'SELECT * FROM media';
        $params = [];
        if ($q !== '') {
            $sql     .= ' WHERE original_name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        return Database::select($sql, $params);
    }

    public static function count(string $q = ''): int
    {
        $sql    = 'SELECT COUNT(*) AS c FROM media';
        $params = [];
        if ($q !== '') {
            $sql     .= ' WHERE original_name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        return (int) (Database::first($sql, $params)['c'] ?? 0);
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

    public static function update(int $id, array $data): int
    {
        return Database::update('media', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::delete('media', ['id' => $id]);
    }
}
