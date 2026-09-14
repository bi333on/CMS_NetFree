<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Рендер темы. Ищет шаблоны в themes/{name} и публичных шаблонах.
 */
class Theme
{
    protected string $name;
    protected string $root;

    public function __construct(string $name, ?string $basePath = null)
    {
        $this->name = $name;
        $this->root = rtrim($basePath ?? Application::getInstance()->basePath, '/\\') . '/themes/' . $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(string $template): string
    {
        return $this->root . '/' . ltrim($template, '/') . '.php';
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->path($template);
        if (!is_file($file)) {
            return 'Template not found: ' . e($template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
