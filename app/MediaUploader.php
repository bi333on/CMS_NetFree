<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Content\CategoryRepository;
use NetFree\Content\PostRepository;
use RuntimeException;

/**
 * Загрузка медиафайлов с проверками безопасности.
 */
class MediaUploader
{
    /** Допустимые расширения и MIME-типы (по умолчанию без SVG). */
    protected const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
    ];

    protected const SVG_MIME = 'image/svg+xml';

    /** Максимальный размер файла (байты). */
    protected const MAX_SIZE = 10 * 1024 * 1024; // 10 МБ

    public static function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_SIZE) {
            throw new RuntimeException('Файл слишком большой (максимум 10 МБ).');
        }

        $original = (string) ($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        $allowed = self::ALLOWED;
        // SVG может нести скрипт — разрешаем только явной настройкой.
        if (SettingsRepository::get('media_allow_svg', '0') === '1') {
            $allowed['svg'] = [self::SVG_MIME];
        }

        if (!array_key_exists($ext, $allowed)) {
            throw new RuntimeException('Недопустимый тип файла.');
        }

        // Проверяем фактический MIME (не доверяем только расширению).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if (!in_array($detected, $allowed[$ext], true)) {
            throw new RuntimeException('Содержимое файла не соответствует типу. Отказано в загрузке.');
        }

        // Случайное имя файла — защита от path traversal и подмены расширения.
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        $uploadsDir = Application::getInstance()->basePath . '/public/uploads';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }

        $dest = $uploadsDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Не удалось сохранить файл. Проверьте права на public/uploads.');
        }

        // Размеры растровых изображений (SVG/PDF не имеют).
        $width = null;
        $height = null;
        if (str_starts_with($detected, 'image/') && $detected !== self::SVG_MIME) {
            $info = @getimagesize($dest);
            if (is_array($info) && isset($info[0], $info[1])) {
                $width  = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $id = \NetFree\Content\MediaRepository::create([
            'filename'      => $filename,
            'original_name' => $original,
            'mime'          => $detected,
            'size'          => $size,
            'alt'           => '',
            'title'         => '',
            'width'         => $width,
            'height'        => $height,
        ]);

        return [
            'id'   => $id,
            'url'  => '/uploads/' . $filename,
            'name' => $original,
        ];
    }
}
