<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Менеджер плагинов (в стиле WordPress).
 * Обнаруживает плагины в plugins/ (официальные, закрытые) и
 * в storage/plugins/ (сторонние, открытые).
 */
class PluginManager
{
    protected string $basePath;
    protected array $plugins = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function load(): void
    {
        $dirs = [
            $this->basePath . '/plugins',
            $this->basePath . '/storage/plugins',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $pluginDir = $dir . '/' . $entry;
                if (!is_dir($pluginDir)) {
                    continue;
                }
                $this->loadPlugin($pluginDir, $entry);
            }
        }

        do_action('netfree.plugins_loaded');
    }

    protected function loadPlugin(string $dir, string $name): void
    {
        $entry = $dir . '/plugin.php';
        if (!is_file($entry)) {
            return;
        }

        $meta = $this->readMeta($dir . '/plugin.json');

        $this->plugins[$name] = [
            'name'    => $name,
            'dir'     => $dir,
            'meta'    => $meta,
            'enabled' => (bool) ($meta['enabled'] ?? true),
        ];

        if (($meta['enabled'] ?? true) === false) {
            return;
        }

        require $entry;

        if (is_callable($meta['on_activate'] ?? null) === false) {
            // Колбэки хуков плагин регистрирует сам в plugin.php.
        }

        do_action('netfree.plugin_loaded', $name, $meta);
    }

    protected function readMeta(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public function all(): array
    {
        return $this->plugins;
    }
}
