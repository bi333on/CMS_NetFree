<?php
/**
 * NetFree bootstrap — подключает автозагрузчик и глобальные функции.
 */

declare(strict_types=1);

if (!defined('NETFREE')) {
    define('NETFREE', true);
}

require_once __DIR__ . '/Autoloader.php';

\NetFree\Autoloader::register(__DIR__);

require_once __DIR__ . '/functions.php';
