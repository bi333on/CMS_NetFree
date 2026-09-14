<?php

declare(strict_types=1);

namespace NetFree\Content;

use NetFree\Database;
use NetFree\Hooks;

/**
 * Репозиторий страниц.
 */
class PageRepository
{
    protected static function applyHooks(array $page): array
    {
        return (array) app()->hooks->applyFilters('netfree.page_data', $page);
    }

    public static function bySlug(string $slug): ?array
    {
        $page = Database::first('SELECT * FROM pages WHERE slug = ? AND is_published = 1', [$slug]);
        return $page ? self::applyHooks($page) : null;
    }

    public static function byId(int $id): ?array
    {
        $page = Database::first('SELECT * FROM pages WHERE id = ?', [$id]);
        return $page ? self::applyHooks($page) : null;
    }

    public static function frontPage(): ?array
    {
        $page = Database::first('SELECT * FROM pages WHERE slug = ? AND is_published = 1', ['home']);
        return $page ? self::applyHooks($page) : null;
    }

    public static function all(): array
    {
        return Database::select('SELECT * FROM pages ORDER BY id DESC');
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        return Database::insert('pages', $data);
    }

    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('pages', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::delete('pages', ['id' => $id]);
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            return (bool) Database::first('SELECT id FROM pages WHERE slug = ? AND id != ?', [$slug, $excludeId]);
        }
        return (bool) Database::first('SELECT id FROM pages WHERE slug = ?', [$slug]);
    }
}
