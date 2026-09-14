<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Схема БД (MySQL) и начальные данные.
 */
class Schema
{
    public static function install(array $cfg, string $adminUsername, string $adminPassword, string $siteName): void
    {
        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int) ($cfg['port'] ?? 3306),
                $cfg['name'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            ),
            $cfg['user'] ?? '',
            $cfg['pass'] ?? '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]
        );

        $pdo->exec('SET NAMES utf8mb4');

        // Таблицы
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS pages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                content LONGTEXT NOT NULL,
                meta_desc VARCHAR(320) NOT NULL DEFAULT '',
                is_published TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_pages_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(190) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_users_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_keys (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                key_hash CHAR(64) NOT NULL,
                permissions VARCHAR(64) NOT NULL DEFAULT 'read',
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_api_keys_hash (key_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_rate_limits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bucket VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_rate_bucket_time (bucket, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS options (
                `key` VARCHAR(190) NOT NULL PRIMARY KEY,
                `value` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Администратор (идемпотентно — повторный запуск не создаёт дублей)
        $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, email, created_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = username'
        );
        $stmt->execute([$adminUsername, $hash, '', date('Y-m-d H:i:s')]);

        // Демо-страницы (идемпотентно)
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare(
            'INSERT INTO pages (title, slug, content, meta_desc, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE slug = slug'
        );

        $insert->execute([
            'Главная',
            'home',
            '<h2>Добро пожаловать в ' . $siteName . '</h2><p>Это главная страница сайта, созданная на CMS NetFree.</p>',
            'Главная страница сайта',
            $now,
            $now,
        ]);

        $insert->execute([
            'О нас',
            'about',
            '<h2>О нас</h2><p>Здесь можно рассказать о вашем проекте. Контент редактируется в админ-панели.</p>',
            'О нас',
            $now,
            $now,
        ]);

        // Базовые настройки (идемпотентно)
        $pdo->prepare(
            'INSERT INTO options (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = `value`'
        )->execute(['site_name', $siteName]);
    }
}
