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
     * Список медиафайлов (JSON).
     */
    public function list(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        $items = array_map(function (array $m) {
            return [
                'id'   => (int) $m['id'],
                'url'  => '/uploads/' . $m['filename'],
                'name' => $m['original_name'],
                'mime' => $m['mime'],
            ];
        }, MediaRepository::all());

        return (new Response())->json(['items' => $items]);
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
}
