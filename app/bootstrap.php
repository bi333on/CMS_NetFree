<?php
/**
 * NetFree bootstrap — подключает автозагрузчик и глобальные функции.
 */

declare(strict_types=1);

if (!defined('NETFREE')) {
    define('NETFREE', true);
}

// Версия ядра — используется для кэш-бастинга ассетов конструктора (?v=).
if (!defined('NF_VERSION')) {
    define('NF_VERSION', '0.4.0');
}

require_once __DIR__ . '/Autoloader.php';

\NetFree\Autoloader::register(__DIR__);

require_once __DIR__ . '/functions.php';
