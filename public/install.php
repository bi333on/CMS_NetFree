<?php
/**
 * NetFree — веб-установщик.
 *
 * Загрузите все файлы CMS на хостинг (или используйте netfree-install.php
 * для автоматической загрузки с GitHub) и откройте /install.php.
 *
 * После успешной установки файл install.php рекомендуется удалить.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('NETFREE_ROOT', dirname(__DIR__));
define('NETFREE_ENV_FILE', NETFREE_ROOT . '/config/env.php');

$errors = [];
$done = false;

// ---------------------------------------------------------------------------
// Шаг 1 — проверка требований
// ---------------------------------------------------------------------------
function requirement_ok(string $label, bool $ok, string $hint = ''): array
{
    return ['label' => $label, 'ok' => $ok, 'hint' => $hint];
}

$requirements = [
    requirement_ok('PHP 8.0+', version_compare(PHP_VERSION, '8.0.0', '>='), 'Текущая версия: ' . PHP_VERSION),
    requirement_ok('Расширение PDO', extension_loaded('pdo')),
    requirement_ok('Расширение pdo_mysql', extension_loaded('pdo_mysql')),
    requirement_ok('Расширение mbstring', extension_loaded('mbstring')),
    requirement_ok('Расширение openssl', extension_loaded('openssl')),
    requirement_ok('Расширение json', extension_loaded('json')),
    requirement_ok('Запись в корень проекта', is_writable(NETFREE_ROOT)),
    requirement_ok('Запись в config/', is_writable(NETFREE_ROOT . '/config') || !is_dir(NETFREE_ROOT . '/config')),
];

$allRequirementsOk = count(array_filter($requirements, fn($r) => $r['ok'])) === count($requirements);

$alreadyInstalled = is_file(NETFREE_ENV_FILE);

// ---------------------------------------------------------------------------
// Шаг 2 — обработка формы
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allRequirementsOk && !$alreadyInstalled) {
    $dbHost     = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbPort     = (int) ($_POST['db_port'] ?? 3306);
    $dbName     = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser     = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass     = (string) ($_POST['db_pass'] ?? '');
    $adminUser  = trim((string) ($_POST['admin_user'] ?? ''));
    $adminPass  = (string) ($_POST['admin_pass'] ?? '');
    $siteName   = trim((string) ($_POST['site_name'] ?? 'NetFree'));
    $siteUrl    = trim((string) ($_POST['site_url'] ?? ''));
    $timezone   = trim((string) ($_POST['timezone'] ?? 'UTC'));

    if ($dbName === '' || $dbUser === '' || $adminUser === '' || $adminPass === '') {
        $errors[] = 'Заполните все обязательные поля (база данных, логин и пароль администратора).';
    } elseif (strlen($adminPass) < 8) {
        $errors[] = 'Пароль администратора должен быть не короче 8 символов.';
    } elseif ($siteUrl === '') {
        $errors[] = 'Укажите URL сайта.';
    }

    // Проверка подключения к БД
    $pdoOk = false;
    if (!$errors) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $dbHost, $dbPort, $dbName
            );
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdoOk = true;
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, '1045')) {
                $errors[] = 'Ошибка доступа к MySQL (код 1045): неверный логин или пароль пользователя БД, либо у пользователя нет прав подключаться с этого хоста.';
                $errors[] = 'Проверьте: 1) пароль пользователя «' . htmlspecialchars($dbUser, ENT_QUOTES) . '»; 2) что пользователю разрешён доступ с хоста «' . htmlspecialchars($dbHost, ENT_QUOTES) . '» (на многих хостингах «localhost» и «127.0.0.1» — разные правила); 3) что пользователь привязан к базе «' . htmlspecialchars($dbName, ENT_QUOTES) . '».';
            } elseif (str_contains($msg, '1049')) {
                $errors[] = 'База данных «' . htmlspecialchars($dbName, ENT_QUOTES) . '» не найдена (код 1049). Создайте базу в панели хостинга и укажите её точное имя.';
            } elseif (str_contains($msg, '2002') || str_contains($msg, '2005')) {
                $errors[] = 'Не удалось подключиться к серверу MySQL «' . htmlspecialchars($dbHost, ENT_QUOTES) . ':' . (int) $dbPort . '». Проверьте хост и порт (на хостинге хост часто «localhost», а не IP).';
            } else {
                $errors[] = 'Не удалось подключиться к базе данных: ' . $msg;
            }
        }
    }

    if (!$errors && $pdoOk) {
        $cfg = [
            'host'    => $dbHost,
            'port'    => $dbPort,
            'name'    => $dbName,
            'user'    => $dbUser,
            'pass'    => $dbPass,
            'charset' => 'utf8mb4',
        ];

        // Генерация секретов
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

        try {
            // Гарантируем существование папки config/ (git не хранит пустые папки).
            $configDir = NETFREE_ROOT . '/config';
            if (!is_dir($configDir)) {
                if (!@mkdir($configDir, 0755, true)) {
                    throw new RuntimeException('Не удалось создать папку config/. Проверьте права на запись в корень проекта.');
                }
            }
            if (!is_writable($configDir)) {
                throw new RuntimeException('Папка config/ недоступна для записи. Установите права на запись (например, 755 или 775) на папку config/.');
            }

            // Создание схемы БД и демо-данных
            require NETFREE_ROOT . '/app/Schema.php';
            \NetFree\Schema::install($cfg, $adminUser, $adminPass, $siteName);

            // Запись конфигурации
            if (@file_put_contents(NETFREE_ENV_FILE, $envCode) === false) {
                throw new RuntimeException('Не удалось записать config/env.php. Проверьте права на папку config/.');
            }

            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка NetFree</title>
    <style>
        :root { --accent:#2d6cdf; --ok:#16a34a; --err:#dc2626; --bg:#f5f6f8; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--bg); color:#1a1a2e; line-height:1.6; }
        .wrap { max-width:720px; margin:40px auto; padding:0 20px; }
        .card { background:#fff; border-radius:10px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,.07); margin-bottom:20px; }
        h1 { margin-top:0; }
        h2 { margin-top:0; }
        label { display:block; font-weight:600; margin:14px 0 4px; }
        input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
        .req { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; }
        .req .badge { padding:2px 10px; border-radius:12px; color:#fff; font-size:12px; }
        .badge.ok { background:var(--ok); }
        .badge.bad { background:var(--err); }
        .btn { display:inline-block; background:var(--accent); color:#fff; border:none; padding:12px 24px; border-radius:6px; font-size:15px; cursor:pointer; }
        .error { background:#fde8e8; color:#991b1b; padding:10px 14px; border-radius:6px; margin-bottom:14px; }
        .success { background:#e7f6ec; color:#166534; padding:10px 14px; border-radius:6px; margin-bottom:14px; }
        code { background:#f3f4f6; padding:2px 6px; border-radius:4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Установка NetFree</h1>

    <?php if ($done): ?>
        <div class="card">
            <div class="success">Установка успешно завершена!</div>
            <p>Сайт готов к работе. <strong>Обязательно удалите файл <code>public/install.php</code>.</strong></p>
            <p><a class="btn" href="/">Перейти на сайт</a>
               <a class="btn" style="background:#6b7280" href="/admin">Перейти в админ-панель</a></p>
            <p>Админ-панель: <code>ваш-сайт/admin</code></p>
        </div>
    <?php elseif ($alreadyInstalled): ?>
        <div class="card">
            <div class="error">CMS уже установлена (найден config/env.php).</div>
            <p>Для повторной установки удалите файл <code>config/env.php</code> и при необходимости очистите базу данных.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>1. Проверка требований</h2>
            <?php foreach ($requirements as $r): ?>
                <div class="req">
                    <span><?= htmlspecialchars($r['label'], ENT_QUOTES) ?><?= $r['hint'] ? ' <small style="color:#6b7280">(' . htmlspecialchars($r['hint'], ENT_QUOTES) . ')</small>' : '' ?></span>
                    <span class="badge <?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? 'OK' : 'Ошибка' ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>2. Настройка</h2>

            <?php foreach ($errors as $e): ?>
                <div class="error"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
            <?php endforeach; ?>

            <?php if (!$allRequirementsOk): ?>
                <div class="error">Устраните ошибки в требованиях выше, затем обновите страницу.</div>
            <?php endif; ?>

            <form method="post" <?= $allRequirementsOk ? '' : 'style="opacity:.5;pointer-events:none"' ?>>
                <h3>База данных (MySQL)</h3>
                <div class="grid">
                    <div><label>Хост</label><input type="text" name="db_host" value="localhost" required></div>
                    <div><label>Порт</label><input type="number" name="db_port" value="3306" required></div>
                </div>
                <div class="grid">
                    <div><label>Имя базы *</label><input type="text" name="db_name" required></div>
                    <div><label>Пользователь *</label><input type="text" name="db_user" required></div>
                </div>
                <label>Пароль БД</label>
                <input type="password" name="db_pass">

                <h3>Администратор</h3>
                <div class="grid">
                    <div><label>Логин *</label><input type="text" name="admin_user" value="admin" required></div>
                    <div><label>Пароль * (мин. 8 символов)</label><input type="password" name="admin_pass" required></div>
                </div>

                <h3>Сайт</h3>
                <div class="grid">
                    <div><label>Название сайта</label><input type="text" name="site_name" value="NetFree"></div>
                    <div><label>URL сайта *</label><input type="text" name="site_url" placeholder="https://example.com" required></div>
                </div>
                <label>Часовой пояс</label>
                <input type="text" name="timezone" value="UTC">

                <p style="margin-top:20px;"><button type="submit" class="btn">Установить</button></p>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
