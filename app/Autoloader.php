<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Простой PSR-4 автозагрузчик: NetFree\Foo\Bar -> app/Foo/Bar.php
 * Composer не обязателен для работы ядра.
 */
class Autoloader
{
    protected static string $baseDir;

    public static function register(string $appDir): void
    {
        self::$baseDir = rtrim($appDir, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (str_starts_with($class, 'NetFree\\')) {
            $relative = substr($class, 8);
            $file = self::$baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
}
