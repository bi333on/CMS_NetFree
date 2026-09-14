<?php
/**
 * NetFree — front controller.
 *
 * Единственная точка входа для веб-сервера.
 */

declare(strict_types=1);

define('NETFREE_START', microtime(true));
define('NETFREE_ROOT', dirname(__DIR__));

require NETFREE_ROOT . '/app/bootstrap.php';

$app = NetFree\Application::getInstance(NETFREE_ROOT);
$app->run();
