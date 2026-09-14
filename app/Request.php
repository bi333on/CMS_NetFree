<?php

declare(strict_types=1);

namespace NetFree;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $files;
    public array $server;
    public array $headers;
    protected ?array $jsonCache = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->server = $_SERVER;

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Учёт установки в поддиректорию.
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($base !== '' && $base !== '/' && $base !== '.' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $this->path = $path;

        $this->query = $_GET;
        $this->post  = $_POST;
        $this->files = $_FILES;
        $this->headers = function_exists('getallheaders') ? getallheaders() : $this->serverHeaders();
    }

    protected function serverHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = $v;
            }
        }
        return $headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return (string) $v;
            }
        }
        return $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    public function json(): array
    {
        if ($this->jsonCache === null) {
            $data = json_decode($this->rawBody(), true);
            $this->jsonCache = is_array($data) ? $data : [];
        }
        return $this->jsonCache;
    }

    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }
}
