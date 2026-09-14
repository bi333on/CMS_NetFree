<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Api\ApiKey;
use NetFree\Api\Hmac;
use NetFree\Api\Jwt;
use NetFree\Api\RateLimiter;
use NetFree\Content\PageRepository;

/**
 * REST API: /api/v1/*
 * Авторизация: X-API-Key (плагины) или Authorization: Bearer <JWT> (клиенты).
 * Опционально HMAC-подпись заголовками X-Nonce / X-Timestamp / X-Signature.
 */
class ApiRouter
{
    protected Application $app;
    protected ?array $auth = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $path = substr($this->app->request->path, 4); // убираем "/api"
        $path = rtrim($path, '/') ?: '/';

        if (!str_starts_with($path, '/v1')) {
            $this->send(new Response(), 404, ['error' => 'Not found']);
            return;
        }
        $path = substr($path, 3);
        $path = rtrim($path, '/') ?: '/';

        if ($path === '/auth') {
            $this->handleAuth();
            return;
        }

        if (!$this->authenticate()) {
            $this->send(new Response(), 401, ['error' => 'Unauthorized']);
            return;
        }

        if (RateLimiter::tooMany('api:' . $this->clientId(), (int) $this->app->config->get('security.rate_limit', 60), (int) $this->app->config->get('security.rate_window', 60))) {
            $this->send(new Response(), 429, ['error' => 'Too many requests']);
            return;
        }

        $method = $this->app->request->method;
        $resp = new Response();

        if ($method === 'GET' && $path === '/pages') {
            $this->send($resp, 200, ['pages' => PageRepository::all()]);
            return;
        }
        if ($method === 'GET' && preg_match('#^/pages/([^/]+)$#', $path, $m)) {
            $page = PageRepository::bySlug($m[1]);
            if (!$page) {
                $this->send($resp, 404, ['error' => 'Page not found']);
                return;
            }
            $this->send($resp, 200, ['page' => $page]);
            return;
        }
        if ($method === 'POST' && $path === '/pages') {
            if (!$this->canWrite()) {
                $this->send($resp, 403, ['error' => 'Forbidden']);
                return;
            }
            $data = $this->app->request->json();
            $slug = slugify((string) ($data['slug'] ?? $data['title'] ?? ''));
            if (!$slug || PageRepository::slugExists($slug)) {
                $this->send($resp, 422, ['error' => 'Slug missing or already exists']);
                return;
            }
            $id = PageRepository::create([
                'title'        => (string) ($data['title'] ?? ''),
                'slug'         => $slug,
                'content'      => (string) ($data['content'] ?? ''),
                'meta_desc'    => (string) ($data['meta_desc'] ?? ''),
                'is_published' => (int) ($data['is_published'] ?? 1),
            ]);
            $this->send($resp, 201, ['id' => $id, 'slug' => $slug]);
            return;
        }
        if ($method === 'GET' && $path === '/options') {
            $opts = Database::select('SELECT `key`, `value` FROM options');
            $map = [];
            foreach ($opts as $row) {
                $map[$row['key']] = $row['value'];
            }
            $this->send($resp, 200, ['options' => $map]);
            return;
        }

        $this->send($resp, 404, ['error' => 'Not found']);
    }

    protected function handleAuth(): void
    {
        $resp = new Response();
        $data = $this->app->request->json();

        // Выдача JWT по логину/паролю администратора.
        $user = Database::first('SELECT * FROM users WHERE username = ?', [$data['username'] ?? '']);
        if ($user && password_verify((string) ($data['password'] ?? ''), $user['password_hash'])) {
            $token = Jwt::encode(['sub' => (int) $user['id'], 'role' => 'admin'], (string) $this->app->config->get('security.jwt_secret', ''), (int) $this->app->config->get('security.jwt_ttl', 3600));
            $this->send($resp, 200, ['token' => $token, 'expires_in' => (int) $this->app->config->get('security.jwt_ttl', 3600)]);
            return;
        }
        $this->send($resp, 401, ['error' => 'Invalid credentials']);
    }

    protected function authenticate(): bool
    {
        $req = $this->app->request;

        // 1) API-ключ
        $apiKey = $req->header('X-API-Key');
        if ($apiKey) {
            $row = ApiKey::verify($apiKey);
            if ($row) {
                $this->auth = ['type' => 'key', 'permissions' => $row['permissions']];
                return $this->verifyHmac($apiKey);
            }
            return false;
        }

        // 2) JWT
        $authHeader = $req->header('Authorization', '');
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
            $payload = Jwt::decode($m[1], (string) $this->app->config->get('security.jwt_secret', ''));
            if ($payload) {
                $this->auth = ['type' => 'jwt', 'role' => $payload['role'] ?? 'user'];
                return true;
            }
        }

        return false;
    }

    protected function verifyHmac(string $apiKey): bool
    {
        $req = $this->app->request;
        $signature = $req->header('X-Signature');
        if (!$signature) {
            // HMAC не обязателен, если передан валидный ключ.
            return true;
        }
        $nonce     = (string) $req->header('X-Nonce', '');
        $timestamp = (string) $req->header('X-Timestamp', '');
        return Hmac::verify(
            $apiKey,
            $req->method,
            $req->path,
            $nonce,
            $timestamp,
            $req->rawBody(),
            $signature
        );
    }

    protected function clientId(): string
    {
        return $this->auth['type'] === 'key'
            ? 'key'
            : 'jwt:' . ($this->auth['role'] ?? 'user');
    }

    protected function canWrite(): bool
    {
        if ($this->auth['type'] === 'key') {
            return str_contains((string) ($this->auth['permissions'] ?? ''), 'write');
        }
        return ($this->auth['role'] ?? '') === 'admin';
    }

    protected function send(Response $resp, int $status, array $data): void
    {
        $resp->json($data, $status)->send();
        exit;
    }
}
