<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * Связывает конструктор с темой:
 * при выборке страницы/записи в режиме builder рендерит блоки в `content`,
 * а сгенерированный CSS ставит в очередь вывода в <head>.
 */
class Builder
{
    protected static string $headCss = '';

    /**
     * Регистрирует фильтры/экшены. Вызывается из Application::boot().
     */
    public static function boot(): void
    {
        add_filter('netfree.page_data', [self::class, 'prepareEntity'], 5, 1);
        add_filter('netfree.post_data', [self::class, 'prepareEntity'], 5, 1);
        add_action('netfree.head', [self::class, 'printHeadCss'], 10);
    }

    public static function prepareEntity(array $entity): array
    {
        if (($entity['editor_mode'] ?? 'classic') !== 'builder') {
            return $entity;
        }

        $raw = trim((string) ($entity['content_blocks'] ?? ''));
        if ($raw === '' || $raw === 'null') {
            return $entity;
        }

        $doc = Document::parse($raw);
        if (!$doc['sections']) {
            return $entity;
        }

        $entity['content'] = Renderer::render($doc);

        // content_css генерируется авторитетно на сохранении; при его отсутствии
        // (JSON вписан руками) — генерируем на лету.
        $css = trim((string) ($entity['content_css'] ?? ''));
        if ($css === '') {
            $css = CssGenerator::generate($doc);
        }
        if ($css !== '') {
            self::$headCss .= "\n" . $css;
        }

        return $entity;
    }

    public static function printHeadCss(): void
    {
        echo "\n<link rel=\"stylesheet\" href=\"" . e(url('nf-assets/nf-blocks.css?v=' . NF_VERSION)) . "\">";

        if (self::$headCss !== '') {
            echo "\n<style id=\"nf-page-css\">" . self::$headCss . "</style>\n";
            self::$headCss = '';
        }
    }
}
