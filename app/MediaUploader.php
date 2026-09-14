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
    /** Допустимые расширения и MIME-типы. */
    protected const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml'],
        'pdf'  => ['application/pdf'],
    ];

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
        if (!array_key_exists($ext, self::ALLOWED)) {
            throw new RuntimeException('Недопустимый тип файла.');
        }

        // Проверяем фактический MIME (не доверяем только расширению).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if (!in_array($detected, self::ALLOWED[$ext], true)) {
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

        $id = \NetFree\Content\MediaRepository::create([
            'filename'      => $filename,
            'original_name' => $original,
            'mime'          => $detected,
            'size'          => $size,
        ]);

        return [
            'id'   => $id,
            'url'  => '/uploads/' . $filename,
            'name' => $original,
        ];
    }
}
