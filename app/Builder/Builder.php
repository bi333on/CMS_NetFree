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

    protected static ?string $blocksCss = null;

    protected static function blocksCss(): string
    {
        if (self::$blocksCss === null) {
            $path = \NetFree\Application::getInstance()->basePath . '/core-assets/nf-blocks.css';
            self::$blocksCss = is_file($path) ? (string) file_get_contents($path) : '';
        }
        return self::$blocksCss;
    }

    public static function printHeadCss(): void
    {
        $css = self::blocksCss();
        if ($css !== '') {
            echo "\n<style id=\"nf-blocks-css\">" . $css . "</style>";
        }

        if (self::$headCss !== '') {
            echo "\n<style id=\"nf-page-css\">" . self::$headCss . "</style>\n";
            self::$headCss = '';
        }
    }

    /**
     * Кладёт готовый CSS в очередь <head> (используется предпросмотром ревизий).
     */
    public static function enqueueRawCss(string $css): void
    {
        $css = trim($css);
        if ($css !== '') {
            self::$headCss .= "\n" . $css;
        }
    }
}
