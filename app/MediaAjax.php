<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Content\MediaRepository;

/**
 * AJAX-эндпоинты для медиа-модала редактора.
 */
class MediaAjax
{
    /**
     * Список медиафайлов (JSON) с поиском и пагинацией.
     */
    public function list(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        $q       = trim((string) ($app->request->query['q'] ?? ''));
        $perPage = max(1, min(100, (int) ($app->request->query['per_page'] ?? 40)));
        $page    = max(1, (int) ($app->request->query['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = MediaRepository::count($q);
        $items = array_map(function (array $m) {
            return [
                'id'     => (int) $m['id'],
                'url'    => '/uploads/' . $m['filename'],
                'name'   => $m['original_name'],
                'mime'   => $m['mime'],
                'alt'    => $m['alt'] ?? '',
                'title'  => $m['title'] ?? '',
                'width'  => $m['width'] ?? null,
                'height' => $m['height'] ?? null,
            ];
        }, MediaRepository::search($q, $perPage, $offset));

        return (new Response())->json([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Загрузка файла (JSON).
     */
    public function upload(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        try {
            $file = $app->request->files['file'] ?? null;
            if (!$file) {
                return (new Response())->json(['error' => 'Файл не выбран.'], 422);
            }
            $uploaded = MediaUploader::upload($file);
            return (new Response())->json(['item' => $uploaded]);
        } catch (\Throwable $e) {
            return (new Response())->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Обновление метаданных (alt/title) медиафайла.
     */
    public function update(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        $id = (int) $app->request->input('id', '0');
        $item = MediaRepository::byId($id);
        if (!$item) {
            return (new Response())->json(['error' => 'Not found'], 404);
        }

        $data = [];
        if ($app->request->has('alt')) {
            $data['alt'] = trim((string) $app->request->input('alt', ''));
        }
        if ($app->request->has('title')) {
            $data['title'] = trim((string) $app->request->input('title', ''));
        }

        if ($data) {
            MediaRepository::update($id, $data);
        }

        return (new Response())->json(['ok' => true]);
    }
}
