<?php
/**
 * NetFree — загрузчик установки (одним файлом).
 *
 * Назначение: вы загружаете на хостинг ОДИН этот файл, открываете его в браузере,
 * указываете ссылку на репозиторий GitHub (и токен для приватного репозитория) —
 * загрузчик скачивает архив CMS, распаковывает файлы в ту же директорию и
 * перенаправляет на веб-установщик (public/install.php).
 *
 * После установки обязательно удалите этот файл.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? 'form';
$errors = [];
$messages = [];

/**
 * Выполняет GET-запрос к GitHub API и возвращает ['code' => int, 'body' => string].
 */
function github_request(string $url, string $token): array
{
    $headers = [
        'User-Agent: NetFree-Installer/1.0',
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($data === false) {
        throw new RuntimeException('Ошибка загрузки: ' . $err);
    }
    return ['code' => $code, 'body' => $data];
}

/**
 * Скачивает zip-архив репозитория GitHub (API) и возвращает путь к файлу.
 * Для приватных репозиториев передаётся токен (PAT/fine-grained).
 * Если ветка не указана — определяется автоматически через default_branch.
 */
function download_repo(string $repoUrl, string $dest, string $token = '', string $branch = ''): string
{
    // Нормализуем ссылку.
    $repoUrl = rtrim($repoUrl, '/');
    $repoUrl = preg_replace('#\.git$#', '', $repoUrl);

    if (!preg_match('#github\.com/([^/]+)/([^/]+?)(?:/tree/([^/]+))?$#', $repoUrl, $m)) {
        throw new RuntimeException('Не удалось распознать URL репозитория GitHub.');
    }
    $owner = $m[1];
    $repo  = $m[2];
    // Ветка может быть задана в URL (…/tree/<branch>) или в поле формы.
    if ($branch === '' && isset($m[3]) && $m[3] !== '') {
        $branch = $m[3];
    }

    // 1) Если ветка не задана — узнаём ветку по умолчанию у репозитория.
    if ($branch === '') {
        $info = github_request("https://api.github.com/repos/{$owner}/{$repo}", $token);
        if ($info['code'] === 401 || $info['code'] === 403) {
            throw new RuntimeException('Доступ запрещён (HTTP ' . $info['code'] . '). Проверьте GitHub-токен: у него должен быть доступ к этому репозиторию.');
        }
        if ($info['code'] === 404) {
            throw new RuntimeException('Репозиторий не найден (404). Проверьте владельца и название, либо что токен имеет доступ к приватному репозиторию.');
        }
        if ($info['code'] !== 200) {
            throw new RuntimeException('GitHub вернул HTTP ' . $info['code'] . ' при получении информации о репозитории.');
        }
        $meta = json_decode($info['body'], true);
        if (!is_array($meta) || empty($meta['default_branch'])) {
            throw new RuntimeException('Не удалось определить ветку по умолчанию. Укажите ветку вручную в поле «Ветка».');
        }
        $branch = (string) $meta['default_branch'];
    }

    // 2) Скачиваем архив нужной ветки.
    $archive = github_request(
        "https://api.github.com/repos/{$owner}/{$repo}/zipball/" . rawurlencode($branch),
        $token
    );

    if ($archive['code'] === 401 || $archive['code'] === 403) {
        throw new RuntimeException('Доступ запрещён (HTTP ' . $archive['code'] . '). Для приватного репозитория нужен токен с правом чтения содержимого (Contents: Read / scope repo).');
    }
    if ($archive['code'] === 404) {
        throw new RuntimeException('Архив не найден (404). Проверьте название ветки «' . htmlspecialchars($branch, ENT_QUOTES) . '».');
    }
    if ($archive['code'] !== 200) {
        throw new RuntimeException('GitHub вернул HTTP ' . $archive['code'] . ' при скачивании архива.');
    }

    if (file_put_contents($dest, $archive['body']) === false) {
        throw new RuntimeException('Не удалось сохранить архив: ' . $dest);
    }
    return $dest;
}

/**
 * Распаковывает zip в указанную директорию, убирая верхнюю папку репозитория.
 */
function extract_zip(string $zipPath, string $targetDir): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Не удалось открыть архив (проверьте расширение zip).');
    }

    $base = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $parts = explode('/', trim($name, '/'));
        if ($parts && $parts[0] !== '') {
            $base = $parts[0];
            break;
        }
    }

    $extractBase = sys_get_temp_dir() . '/netfree_extract_' . bin2hex(random_bytes(4));
    @mkdir($extractBase, 0755, true);
    $zip->extractTo($extractBase);
    $zip->close();

    $source = $base !== '' ? $extractBase . '/' . $base : $extractBase;
    if (!is_dir($source)) {
        throw new RuntimeException('Не найдена распакованная папка CMS в архиве.');
    }
    copy_dir($source, $targetDir);
    remove_dir($extractBase);
}

function copy_dir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        @mkdir($dst, 0755, true);
    }
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            copy_dir($s, $d);
        } else {
            @copy($s, $d);
        }
    }
}

function remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . '/' . $item;
        is_dir($p) ? remove_dir($p) : @unlink($p);
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// Обработка формы
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repoUrl = trim((string) ($_POST['repo_url'] ?? ''));
    $token   = trim((string) ($_POST['github_token'] ?? ''));
    $branch  = trim((string) ($_POST['branch'] ?? ''));
    $rootDir = __DIR__;

    if ($repoUrl === '') {
        $errors[] = 'Укажите URL репозитория GitHub.';
    } else {
        try {
            if (!extension_loaded('zip')) {
                throw new RuntimeException('Расширение PHP zip не установлено — оно нужно для распаковки архива.');
            }
            if (!function_exists('curl_init')) {
                throw new RuntimeException('Расширение PHP curl не установлено — оно нужно для загрузки архива.');
            }

            $zipPath = sys_get_temp_dir() . '/netfree_' . bin2hex(random_bytes(4)) . '.zip';
            download_repo($repoUrl, $zipPath, $token, $branch);
            extract_zip($zipPath, $rootDir);
            @unlink($zipPath);

            header('Location: public/install.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Загрузка NetFree</title>
    <style>
        :root { --accent:#2d6cdf; --bg:#f5f6f8; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--bg); color:#1a1a2e; line-height:1.6; }
        .wrap { max-width:640px; margin:40px auto; padding:0 20px; }
        .card { background:#fff; border-radius:10px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
        h1 { margin-top:0; }
        label { display:block; font-weight:600; margin:14px 0 4px; }
        input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
        .btn { display:inline-block; background:var(--accent); color:#fff; border:none; padding:12px 24px; border-radius:6px; font-size:15px; cursor:pointer; margin-top:18px; }
        .error { background:#fde8e8; color:#991b1b; padding:10px 14px; border-radius:6px; margin-bottom:14px; }
        code { background:#f3f4f6; padding:2px 6px; border-radius:4px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Загрузка NetFree</h1>
        <p>Этот файл скачает файлы CMS из вашего репозитория GitHub в текущую директорию и запустит установку.</p>

        <?php foreach ($errors as $e): ?>
            <div class="error"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <label>URL репозитория GitHub *</label>
            <input type="text" name="repo_url" placeholder="https://github.com/username/netfree" required>
            <p style="color:#6b7280;font-size:13px;">Например: <code>https://github.com/bi333on/CMS_NetFree</code>. Ветка определяется автоматически.</p>

            <label>Ветка (необязательно)</label>
            <input type="text" name="branch" placeholder="оставьте пустым — определится сама">

            <label>GitHub-токен (обязателен для приватного репозитория) *</label>
            <input type="password" name="github_token" placeholder="ghp_... или github_pat_..." autocomplete="off">
            <p style="color:#6b7280;font-size:13px;">Токен с правом чтения содержимого: classic <code>repo</code> или fine-grained <code>Contents: Read-only</code>. Токен нигде не сохраняется.</p>

            <button type="submit" class="btn">Скачать и распаковать</button>
        </form>

        <p style="margin-top:24px;color:#6b7280;font-size:13px;">
            Требования: PHP 8.0+, расширения <code>curl</code> и <code>zip</code>. После установки удалите этот файл и <code>public/install.php</code>.
        </p>
    </div>
</div>
</body>
</html>
