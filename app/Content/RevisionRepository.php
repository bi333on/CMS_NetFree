<?php

declare(strict_types=1);

namespace NetFree\Content;

use NetFree\Database;

/**
 * Ревизии страниц и записей (снимки контента, в т.ч. автосохранения).
 */
class RevisionRepository
{
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('revisions', $data);
    }

    public static function byEntity(string $type, int $entityId, int $limit = 50): array
    {
        return Database::select(
            'SELECT * FROM revisions WHERE entity_type = ? AND entity_id = ?
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, (int) $limit),
            [$type, $entityId]
        );
    }

    public static function byId(int $id): ?array
    {
        return Database::first('SELECT * FROM revisions WHERE id = ?', [$id]);
    }

    public static function latestAutosave(string $type, int $entityId): ?array
    {
        return Database::first(
            'SELECT * FROM revisions
             WHERE entity_type = ? AND entity_id = ? AND is_autosave = 1
             ORDER BY id DESC LIMIT 1',
            [$type, $entityId]
        );
    }

    public static function deleteAutosaves(string $type, int $entityId): int
    {
        return Database::execute(
            'DELETE FROM revisions WHERE entity_type = ? AND entity_id = ? AND is_autosave = 1',
            [$type, $entityId]
        );
    }
}
