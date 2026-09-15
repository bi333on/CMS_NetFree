<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Продвинутая обработка медиа-файлов.
 * Поддержка WebP, обрезка, ресайз, оптимизация.
 */
class MediaProcessor
{
    protected string $uploadsDir;
    protected array $config;

    public function __construct(string $uploadsDir, array $config = [])
    {
        $this->uploadsDir = rtrim($uploadsDir, '/');
        $this->config = array_merge([
            'webp_enabled'     => extension_loaded('gd') && function_exists('imagewebp'),
            'webp_quality'     => 80,
            'jpeg_quality'     => 85,
            'max_width'        => 2400,
            'max_height'       => 2400,
            'thumbnail_width'  => 300,
            'thumbnail_height' => 300,
            'allowed_types'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        ], $config);
    }

    /**
     * Обрабатывает загруженный файл: валидация, ресайз, создание превью, WebP.
     */
    public function process(array $file): array
    {
        if (!$this->validateFile($file)) {
            throw new \RuntimeException('Invalid file');
        }

        $info = [
            'original_name' => basename($file['name']),
            'mime_type'     => $file['type'],
            'size'          => $file['size'],
            'uploaded_at'   => date('Y-m-d H:i:s'),
        ];

        // SVG не обрабатываем, просто копируем
        if ($file['type'] === 'image/svg+xml') {
            $filename = $this->generateFilename($file['name']);
            $path = $this->uploadsDir . '/' . $filename;
            move_uploaded_file($file['tmp_name'], $path);

            return array_merge($info, [
                'filename' => $filename,
                'path'     => $path,
                'url'      => $this->pathToUrl($path),
                'width'    => null,
                'height'   => null,
            ]);
        }

        // Загружаем изображение
        $image = $this->loadImage($file['tmp_name'], $file['type']);
        if (!$image) {
            throw new \RuntimeException('Failed to load image');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Ресайз если превышает максимум
        if ($originalWidth > $this->config['max_width'] || $originalHeight > $this->config['max_height']) {
            $image = $this->resize(
                $image,
                $this->config['max_width'],
                $this->config['max_height']
            );
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Сохраняем основное изображение
        $filename = $this->generateFilename($file['name'], '.jpg');
        $path = $this->uploadsDir . '/' . $filename;
        $this->saveImage($image, $path, 'image/jpeg', $this->config['jpeg_quality']);

        $result = array_merge($info, [
            'filename' => $filename,
            'path'     => $path,
            'url'      => $this->pathToUrl($path),
            'width'    => $width,
            'height'   => $height,
        ]);

        // Создаём WebP версию
        if ($this->config['webp_enabled']) {
            $webpFilename = $this->generateFilename($file['name'], '.webp');
            $webpPath = $this->uploadsDir . '/' . $webpFilename;
            $this->saveImage($image, $webpPath, 'image/webp', $this->config['webp_quality']);

            $result['webp_url'] = $this->pathToUrl($webpPath);
            $result['webp_filename'] = $webpFilename;
        }

        // Создаём thumbnail
        $thumb = $this->createThumbnail(
            $image,
            $this->config['thumbnail_width'],
            $this->config['thumbnail_height']
        );

        $thumbFilename = $this->generateFilename($file['name'], '_thumb.jpg');
        $thumbPath = $this->uploadsDir . '/' . $thumbFilename;
        $this->saveImage($thumb, $thumbPath, 'image/jpeg', $this->config['jpeg_quality']);

        $result['thumb_url'] = $this->pathToUrl($thumbPath);
        $result['thumb_filename'] = $thumbFilename;

        // Освобождаем память
        imagedestroy($image);
        imagedestroy($thumb);

        return $result;
    }

    /**
     * Обрезка изображения до указанных размеров (crop).
     */
    public function crop(string $sourcePath, int $x, int $y, int $width, int $height): array
    {
        $mimeType = $this->getMimeType($sourcePath);
        $source = $this->loadImage($sourcePath, $mimeType);

        if (!$source) {
            throw new \RuntimeException('Failed to load source image');
        }

        $cropped = imagecreatetruecolor($width, $height);

        // Прозрачность для PNG
        if ($mimeType === 'image/png') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
        }

        imagecopyresampled(
            $cropped,
            $source,
            0, 0,
            $x, $y,
            $width, $height,
            $width, $height
        );

        $filename = $this->generateFilename(basename($sourcePath), '_cropped.jpg');
        $path = $this->uploadsDir . '/' . $filename;
        $this->saveImage($cropped, $path, 'image/jpeg', $this->config['jpeg_quality']);

        imagedestroy($source);
        imagedestroy($cropped);

        return [
            'filename' => $filename,
            'path'     => $path,
            'url'      => $this->pathToUrl($path),
            'width'    => $width,
            'height'   => $height,
        ];
    }

    /**
     * Изменение размера с сохранением пропорций.
     */
    protected function resize($image, int $maxWidth, int $maxHeight)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        if ($ratio >= 1) {
            return $image; // Не увеличиваем
        }

        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Прозрачность
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        imagedestroy($image);

        return $resized;
    }

    /**
     * Создание thumbnail с обрезкой по центру.
     */
    protected function createThumbnail($image, int $width, int $height)
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $sourceRatio = $sourceWidth / $sourceHeight;
        $thumbRatio = $width / $height;

        if ($sourceRatio > $thumbRatio) {
            // Широкое изображение — обрезаем по бокам
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $thumbRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            // Высокое изображение — обрезаем сверху/снизу
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $thumbRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $thumb = imagecreatetruecolor($width, $height);

        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        imagecopyresampled(
            $thumb,
            $image,
            0, 0,
            $cropX, $cropY,
            $width, $height,
            $cropWidth, $cropHeight
        );

        return $thumb;
    }

    /**
     * Загрузка изображения из файла.
     */
    protected function loadImage(string $path, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/gif':
                return @imagecreatefromgif($path);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            default:
                return false;
        }
    }

    /**
     * Сохранение изображения в файл.
     */
    protected function saveImage($image, string $path, string $mimeType, int $quality): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($image, $path, $quality);
                break;
            case 'image/png':
                $pngQuality = (int) round((100 - $quality) / 11);
                imagepng($image, $path, $pngQuality);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagewebp($image, $path, $quality);
                }
                break;
        }
    }

    /**
     * Валидация загруженного файла.
     */
    protected function validateFile(array $file): bool
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if (!in_array($file['type'], $this->config['allowed_types'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Генерация уникального имени файла.
     */
    protected function generateFilename(string $originalName, string $suffix = ''): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
        $name = substr($name, 0, 50);

        $hash = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        $timestamp = date('Ymd_His');

        if ($suffix !== '') {
            $ext = ltrim($suffix, '.');
        }

        return $name . '_' . $timestamp . '_' . $hash . '.' . $ext;
    }

    /**
     * Конвертация пути в URL.
     */
    protected function pathToUrl(string $path): string
    {
        $relativePath = str_replace($this->uploadsDir, '', $path);
        return '/uploads' . str_replace('\\', '/', $relativePath);
    }

    /**
     * Определение MIME-типа файла.
     */
    protected function getMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return (string) mime_content_type($path);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Оптимизация существующего изображения.
     */
    public function optimize(string $path): bool
    {
        $mimeType = $this->getMimeType($path);
        $image = $this->loadImage($path, $mimeType);

        if (!$image) {
            return false;
        }

        // Пересохраняем с оптимизацией
        $this->saveImage($image, $path, $mimeType, $this->config['jpeg_quality']);
        imagedestroy($image);

        return true;
    }
}
