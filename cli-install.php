<?php
/**
 * NetFree — CLI-установщик (вызывается из install.sh).
 *
 * Создаёт схему БД, администратора, генерирует config/env.php.
 * Не требует браузера и форм.
 *
 * Использование:
 *   php cli-install.php \
 *     --db-host=localhost --db-port=3306 --db-name=netfree \
 *     --db-user=netfree --db-pass=ПАРОЛЬ \
 *     --admin-user=admin --admin-pass=ПАРОЛЬ \
 *     --site-name="NetFree" --site-url=https://example.com
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// cli-install.php лежит в КОРНЕ проекта, поэтому корень = __DIR__.
define('NETFREE_ROOT', __DIR__);

// ---------------------------------------------------------------------------
// Разбор аргументов
// ---------------------------------------------------------------------------
$opts = getopt('', [
    'db-host:', 'db-port:', 'db-name:', 'db-user:', 'db-pass:',
    'admin-user:', 'admin-pass:', 'site-name:', 'site-url:', 'timezone:',
]);

function arg(array $opts, string $key, string $default = ''): string
{
    return (string) ($opts[$key] ?? $default);
}

$dbHost    = arg($opts, 'db-host', 'localhost');
$dbPort    = (int) arg($opts, 'db-port', '3306');
$dbName    = arg($opts, 'db-name', 'netfree');
$dbUser    = arg($opts, 'db-user', 'netfree');
$dbPass    = arg($opts, 'db-pass', '');
$adminUser = arg($opts, 'admin-user', 'admin');
$adminPass = arg($opts, 'admin-pass', '');
$siteName  = arg($opts, 'site-name', 'NetFree');
$siteUrl   = arg($opts, 'site-url', 'http://localhost');
$timezone  = arg($opts, 'timezone', 'UTC');

if ($dbName === '' || $dbUser === '' || $adminUser === '' || $adminPass === '') {
    fwrite(STDERR, "Ошибка: укажите --db-name, --db-user, --admin-user, --admin-pass.\n");
    exit(1);
}

$cfg = [
    'host'    => $dbHost,
    'port'    => $dbPort,
    'name'    => $dbName,
    'user'    => $dbUser,
    'pass'    => $dbPass,
    'charset' => 'utf8mb4',
];

// ---------------------------------------------------------------------------
// Установка
// ---------------------------------------------------------------------------
try {
    // Гарантируем папку config/.
    $configDir = NETFREE_ROOT . '/config';
    if (!is_dir($configDir) && !@mkdir($configDir, 0755, true)) {
        throw new RuntimeException('Не удалось создать папку config/.');
    }

    // Схема БД + администратор + демо-страницы.
    require NETFREE_ROOT . '/app/Schema.php';
    \NetFree\Schema::install($cfg, $adminUser, $adminPass, $siteName);

    // Генерация env.php.
    $env = [
        'site' => [
            'name' => $siteName,
            'url'  => rtrim($siteUrl, '/'),
        ],
        'database' => $cfg,
        'security' => [
            'secret'      => bin2hex(random_bytes(32)),
            'jwt_secret'  => bin2hex(random_bytes(32)),
            'hmac_secret' => bin2hex(random_bytes(32)),
            'jwt_ttl'     => 3600,
            'rate_limit'  => 60,
            'rate_window' => 60,
        ],
        'app' => [
            'debug'    => false,
            'timezone' => $timezone,
        ],
        'theme' => [
            'active' => 'default',
        ],
    ];

    $envCode = "<?php\n\nreturn " . var_export($env, true) . ";\n";
    if (@file_put_contents(NETFREE_ROOT . '/config/env.php', $envCode) === false) {
        throw new RuntimeException('Не удалось записать config/env.php.');
    }

    echo "OK: NetFree установлен.\n";
    echo "  Админ: {$adminUser}\n";
    echo "  Сайт:  {$siteUrl}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка установки: ' . $e->getMessage() . "\n");
    exit(1);
}
