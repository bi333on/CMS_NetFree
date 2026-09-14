<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Content\CategoryRepository;
use NetFree\Content\PostRepository;

/**
 * Настройки сайта (key-value в таблице options).
 */
class SettingsRepository
{
    public static function get(string $key, string $default = ''): string
    {
        $row = Database::first('SELECT `value` FROM options WHERE `key` = ?', [$key]);
        return $row ? (string) $row['value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            'INSERT INTO options (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
    }

    public static function all(): array
    {
        $rows = Database::select('SELECT `key`, `value` FROM options ORDER BY `key` ASC');
        $map = [];
        foreach ($rows as $r) {
            $map[$r['key']] = $r['value'];
        }
        return $map;
    }
}
