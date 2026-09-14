<?php

declare(strict_types=1);

namespace NetFree;

class Router
{
    protected array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => $path, 'handler' => $handler];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function any(string $path, callable $handler): void
    {
        $this->add('ANY', $path, $handler);
    }

    public function dispatch(string $method, string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== strtoupper($method)) {
                continue;
            }
            $params = $this->match($route['path'], $path);
            if ($params !== null) {
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }

    protected function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $path, $matches)) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }
            return $params;
        }
        return null;
    }
}
