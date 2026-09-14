<?php

declare(strict_types=1);

namespace NetFree\Content;

use NetFree\Database;

/**
 * Репозиторий категорий (рубрик) — аналог WP Categories.
 */
class CategoryRepository
{
    public static function all(): array
    {
        return Database::select('SELECT * FROM categories ORDER BY name ASC');
    }

    public static function byId(int $id): ?array
    {
        return Database::first('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function bySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('categories', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::update('categories', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        // Отвязываем записи, чтобы не было висячих ссылок.
        Database::execute('UPDATE posts SET category_id = NULL WHERE category_id = ?', [$id]);
        return Database::delete('categories', ['id' => $id]);
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            return (bool) Database::first('SELECT id FROM categories WHERE slug = ? AND id != ?', [$slug, $excludeId]);
        }
        return (bool) Database::first('SELECT id FROM categories WHERE slug = ?', [$slug]);
    }
}
