<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Конфигурация. Значения по умолчанию + config/env.php (генерируется установщиком).
 */
class Config
{
    protected array $items = [];
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->load();
    }

    protected function load(): void
    {
        $defaults = [
            'site' => [
                'name' => 'NetFree',
                'url'  => 'http://localhost',
            ],
            'database' => [
                'host'    => '127.0.0.1',
                'port'    => 3306,
                'name'    => 'netfree',
                'user'    => 'root',
                'pass'    => '',
                'charset' => 'utf8mb4',
            ],
            'security' => [
                'secret'      => '',
                'jwt_secret'  => '',
                'hmac_secret' => '',
                'jwt_ttl'     => 3600,
                'rate_limit'  => 60,
                'rate_window' => 60,
            ],
            'app' => [
                'debug'    => false,
                'timezone' => 'UTC',
            ],
            'theme' => [
                'active' => 'default',
            ],
        ];

        $envFile = $this->basePath . '/config/env.php';
        $env = is_file($envFile) ? (require $envFile) : [];

        $this->items = array_replace_recursive($defaults, is_array($env) ? $env : []);
    }

    public function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->items;
        foreach ($segments as $seg) {
            if (is_array($value) && array_key_exists($seg, $value)) {
                $value = $value[$seg];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
