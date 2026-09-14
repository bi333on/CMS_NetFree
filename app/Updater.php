<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Обновление ядра с GitHub.
 *
 * Скачивает zip-архив репозитория, распаковывает в временную папку,
 * заменяет файлы ядра и вызывает миграции. Приватный репозиторий — через токен.
 */
class Updater
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Выполняет обновление. Возвращает количество скопированных файлов.
     */
    public function update(string $token = ''): int
    {
        $repoUrl = SettingsRepository::get('update_repo', 'https://github.com/bi333on/CMS_NetFree');

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Расширение PHP curl не установлено — обновление невозможно.');
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Расширение PHP zip не установлено — обновление невозможно.');
        }

        // Парсим owner/repo.
        $repoUrl = rtrim($repoUrl, '/');
        $repoUrl = preg_replace('#\.git$#', '', $repoUrl);
        if (!preg_match('#github\.com/([^/]+)/([^/]+?)(?:/tree/([^/]+))?$#', $repoUrl, $m)) {
            throw new \RuntimeException('Неверный URL репозитория обновлений.');
        }
        $owner = $m[1];
        $repo  = $m[2];
        $branch = $m[3] ?? 'master';

        $headers = [
            'User-Agent: NetFree-Updater/1.0',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        // Узнаём ветку по умолчанию.
        $ch = curl_init("https://api.github.com/repos/{$owner}/{$repo}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $info = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code !== 200) {
            if ($code === 404 && $token === '') {
                throw new \RuntimeException('Репозиторий не найден или приватный. Введите GitHub-токен для доступа к репозиторию.');
            }
            throw new \RuntimeException('Не удалось получить данные репозитория (HTTP ' . $code . '). Проверьте токен.');
        }
        $meta = json_decode($info, true);
        $branch = is_array($meta) && !empty($meta['default_branch']) ? (string) $meta['default_branch'] : $branch;

        // Скачиваем архив.
        $zipUrl = "https://api.github.com/repos/{$owner}/{$repo}/zipball/" . rawurlencode($branch);
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $zipData = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code !== 200 || $zipData === false) {
            throw new \RuntimeException('Не удалось скачать архив обновления (HTTP ' . $code . ').');
        }

        // Сохраняем во временный файл.
        $tmpZip = sys_get_temp_dir() . '/netfree_update_' . bin2hex(random_bytes(4)) . '.zip';
        if (file_put_contents($tmpZip, $zipData) === false) {
            throw new \RuntimeException('Не удалось сохранить архив обновления.');
        }

        // Распаковка во временную папку.
        $tmpDir = sys_get_temp_dir() . '/netfree_update_' . bin2hex(random_bytes(4));
        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            throw new \RuntimeException('Не удалось открыть архив обновления.');
        }
        if (!$zip->extractTo($tmpDir)) {
            throw new \RuntimeException('Не удалось распаковать архив обновления.');
        }
        $zip->close();
        @unlink($tmpZip);

        // Определяем корневую папку внутри архива.
        $base = '';
        foreach (scandir($tmpDir) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($tmpDir . '/' . $entry)) {
                $base = $entry;
                break;
            }
        }
        $sourceRoot = $base !== '' ? $tmpDir . '/' . $base : $tmpDir;

        // Копируем файлы (кроме config/env.php, storage и uploads).
        $copied = $this->copyTree($sourceRoot, $this->app->basePath);

        // Очистка временных файлов.
        $this->removeTree($tmpDir);

        // Прогоняем миграции.
        Migrations::run();

        SettingsRepository::set('last_update', date('Y-m-d H:i:s'));

        // Сбрасываем OPcache, чтобы PHP не отдавал старые версии файлов.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $copied;
    }

    protected function copyTree(string $src, string $dst): int
    {
        $copied = 0;
        if (!is_dir($src)) {
            return $copied;
        }
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            // Не перезаписываем локальную конфигурацию и пользовательские данные.
            if ($item === 'config' || $item === 'storage' || $item === 'uploads') {
                continue;
            }
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            if (is_dir($s)) {
                if (!is_dir($d) && !@mkdir($d, 0755, true)) {
                    throw new \RuntimeException('Не удалось создать каталог: ' . $d);
                }
                $copied += $this->copyTree($s, $d);
            } else {
                if (!@copy($s, $d)) {
                    throw new \RuntimeException('Не удалось перезаписать файл (проверьте права): ' . $d);
                }
                $copied++;
            }
        }
        return $copied;
    }

    protected function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->removeTree($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
