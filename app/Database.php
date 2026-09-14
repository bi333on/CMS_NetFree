<?php

declare(strict_types=1);

namespace NetFree;

use PDO;
use RuntimeException;

/**
 * Обёртка над PDO (MySQL). Статический доступ как в простых приложениях.
 */
class Database
{
    protected static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = $cfg['host'] ?? '127.0.0.1';
        $port    = (int) ($cfg['port'] ?? 3306);
        $name    = $cfg['name'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('База данных не подключена.');
        }
        return self::$pdo;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $rows = self::select($sql, $params);
        return $rows[0] ?? null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ("
              . implode(',', array_fill(0, count($cols), '?')) . ")";
        self::execute($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set  = [];
        $cond = [];
        foreach (array_keys($data) as $c) {
            $set[] = "`{$c}` = ?";
        }
        foreach (array_keys($where) as $c) {
            $cond[] = "`{$c}` = ?";
        }
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set)
             . " WHERE " . implode(' AND ', $cond);
        return self::execute($sql, array_merge(array_values($data), array_values($where)));
    }

    public static function delete(string $table, array $where): int
    {
        $cond = [];
        foreach (array_keys($where) as $c) {
            $cond[] = "`{$c}` = ?";
        }
        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $cond);
        return self::execute($sql, array_values($where));
    }
}
