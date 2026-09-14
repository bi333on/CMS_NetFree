<?php

declare(strict_types=1);

namespace NetFree\Content;

use NetFree\Database;

/**
 * Репозиторий записей (постов) — аналог WP Posts.
 */
class PostRepository
{
    public static function all(string $status = ''): array
    {
        if ($status !== '') {
            return Database::select(
                'SELECT p.*, c.name AS category_name FROM posts p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = ? ORDER BY p.created_at DESC',
                [$status]
            );
        }
        return Database::select(
            'SELECT p.*, c.name AS category_name FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.created_at DESC'
        );
    }

    public static function published(): array
    {
        return Database::select(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
             ORDER BY p.created_at DESC'
        );
    }

    protected static function applyHooks(array $post): array
    {
        return (array) app()->hooks->applyFilters('netfree.post_data', $post);
    }

    public static function bySlug(string $slug): ?array
    {
        $post = Database::first(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.status = "published"',
            [$slug]
        );
        return $post ? self::applyHooks($post) : null;
    }

    public static function byId(int $id): ?array
    {
        $post = Database::first(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?',
            [$id]
        );
        return $post ? self::applyHooks($post) : null;
    }

    public static function byCategory(string $categorySlug): array
    {
        return Database::select(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             WHERE c.slug = ? AND p.status = "published"
             ORDER BY p.created_at DESC',
            [$categorySlug]
        );
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        return Database::insert('posts', $data);
    }

    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('posts', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::delete('posts', ['id' => $id]);
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            return (bool) Database::first('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, $excludeId]);
        }
        return (bool) Database::first('SELECT id FROM posts WHERE slug = ?', [$slug]);
    }

    public static function count(): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS c FROM posts')['c'] ?? 0);
    }
}
