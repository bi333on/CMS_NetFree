<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Лёгкая система миграций: при старте приложения достраивает недостающие таблицы.
 * Идемпотентно (CREATE TABLE IF NOT EXISTS). Версия схемы хранится в options.
 */
class Migrations
{
    protected const VERSION = 2;

    public static function run(): void
    {
        $current = (int) (Database::first("SELECT `value` FROM options WHERE `key` = 'schema_version'")['value'] ?? 0);
        if ($current >= self::VERSION) {
            return;
        }

        self::v1();
        self::v2();

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
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_media_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}
