<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Лёгкая система миграций: при старте приложения достраивает недостающие таблицы.
 * Идемпотентно (CREATE TABLE IF NOT EXISTS). Версия схемы хранится в options.
 */
class Migrations
{
    protected const VERSION = 4;

    public static function run(): void
    {
        $current = (int) (Database::first("SELECT `value` FROM options WHERE `key` = 'schema_version'")['value'] ?? 0);
        if ($current >= self::VERSION) {
            return;
        }

        self::v1();
        self::v2();
        self::v3();
        self::v4();

        Database::execute(
            "INSERT INTO options (`key`, `value`) VALUES ('schema_version', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [(string) self::VERSION]
        );
    }

    protected static function v1(): void
    {
        // Таблицы ядра уже создаются в Schema::install. Здесь ничего дополнительно.
    }

    protected static function columnExists(string $table, string $column): bool
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    protected static function v3(): void
    {
        // Добавляем обложку страницам.
        if (!self::columnExists('pages', 'featured_image')) {
            Database::execute(
                "ALTER TABLE pages ADD COLUMN featured_image VARCHAR(512) NOT NULL DEFAULT '' AFTER meta_desc"
            );
        }
    }

    /**
     * Конструктор: структурированный контент + CSS + режим редактора,
     * метаданные изображений, ревизии и токены предпросмотра.
     */
    protected static function v4(): void
    {
        // Страницы.
        if (!self::columnExists('pages', 'content_blocks')) {
            Database::execute('ALTER TABLE pages ADD COLUMN content_blocks LONGTEXT NULL AFTER content');
        }
        if (!self::columnExists('pages', 'content_css')) {
            Database::execute("ALTER TABLE pages ADD COLUMN content_css MEDIUMTEXT NOT NULL AFTER content_blocks");
        }
        if (!self::columnExists('pages', 'editor_mode')) {
            Database::execute("ALTER TABLE pages ADD COLUMN editor_mode VARCHAR(20) NOT NULL DEFAULT 'classic' AFTER content_css");
        }

        // Записи.
        if (!self::columnExists('posts', 'content_blocks')) {
            Database::execute('ALTER TABLE posts ADD COLUMN content_blocks LONGTEXT NULL AFTER content');
        }
        if (!self::columnExists('posts', 'content_css')) {
            Database::execute("ALTER TABLE posts ADD COLUMN content_css MEDIUMTEXT NOT NULL AFTER content_blocks");
        }
        if (!self::columnExists('posts', 'editor_mode')) {
            Database::execute("ALTER TABLE posts ADD COLUMN editor_mode VARCHAR(20) NOT NULL DEFAULT 'classic' AFTER content_css");
        }

        // Метаданные изображений.
        if (!self::columnExists('media', 'alt')) {
            Database::execute("ALTER TABLE media ADD COLUMN alt VARCHAR(255) NOT NULL DEFAULT '' AFTER size");
        }
        if (!self::columnExists('media', 'title')) {
            Database::execute("ALTER TABLE media ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER alt");
        }
        if (!self::columnExists('media', 'width')) {
            Database::execute('ALTER TABLE media ADD COLUMN width INT UNSIGNED NULL AFTER title');
        }
        if (!self::columnExists('media', 'height')) {
            Database::execute('ALTER TABLE media ADD COLUMN height INT UNSIGNED NULL AFTER width');
        }

        $pdo = Database::pdo();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS revisions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(20) NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL DEFAULT '',
                content LONGTEXT NOT NULL,
                content_blocks LONGTEXT NULL,
                content_css MEDIUMTEXT NULL,
                is_autosave TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_revisions_entity (entity_type, entity_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS preview_tokens (
                token CHAR(32) NOT NULL PRIMARY KEY,
                entity_type VARCHAR(20) NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                revision_id INT UNSIGNED NULL,
                expires_at DATETIME NOT NULL,
                KEY idx_preview_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    protected static function v2(): void
    {
        $pdo = Database::pdo();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                description TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_categories_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS posts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                content LONGTEXT NOT NULL,
                content_blocks LONGTEXT NULL,
                content_css MEDIUMTEXT NOT NULL DEFAULT '',
                editor_mode VARCHAR(20) NOT NULL DEFAULT 'classic',
                excerpt VARCHAR(500) NOT NULL DEFAULT '',
                category_id INT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'published',
                featured_image VARCHAR(512) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_posts_slug (slug),
                KEY idx_posts_category (category_id),
                KEY idx_posts_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS media (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime VARCHAR(128) NOT NULL,
                size INT UNSIGNED NOT NULL,
                alt VARCHAR(255) NOT NULL DEFAULT '',
                title VARCHAR(255) NOT NULL DEFAULT '',
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_media_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}
