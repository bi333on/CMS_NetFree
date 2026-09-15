<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * JSON-дерево документа → семантичный HTML.
 * Единственный рендерер конструктора (на PHP) — браузер его не дублирует.
 */
class Renderer
{
    public static function render(array $doc): string
    {
        $html = '';
        foreach (($doc['sections'] ?? []) as $section) {
            $html .= self::renderSection($section);
        }
        return $html;
    }

    public static function renderSection(array $section): string
    {
        // Свободная секция (Zero Block): абсолютные слои внутри relative-контейнера.
        if (($section['type'] ?? 'section') === 'free') {
            return self::renderFreeSection($section);
        }

        $width = (string) ($section['settings']['width'] ?? 'boxed');
        $class = 'nf-section nf-section--' . ($width === 'full' ? 'full' : 'boxed');

        $anchor = trim((string) ($section['advanced']['anchor'] ?? ''));
        $inner  = $anchor !== ''
            ? '<span id="' . e($anchor) . '" class="nf-anchor" aria-hidden="true"></span>'
            : '';

        $gap = (int) ($section['settings']['gap'] ?? 24);
        $style = ' style="--nf-gap:' . $gap . 'px;"';

        $inner .= '<div class="nf-row">';
        foreach (($section['columns'] ?? []) as $column) {
            $inner .= self::renderColumn($column);
        }
        $inner .= '</div>';

        return '<section ' . self::attrs($section, $class) . $style . '>' . $inner . '</section>';
    }

    /**
     * Free-секция: контейнер position:relative + виджеты как абсолютные слои.
     */
    public static function renderFreeSection(array $section): string
    {
        $width = (string) ($section['settings']['width'] ?? 'boxed');
        $class = 'nf-section nf-section--free nf-section--' . ($width === 'full' ? 'full' : 'boxed');

        $anchor = trim((string) ($section['advanced']['anchor'] ?? ''));
        $inner  = $anchor !== ''
            ? '<span id="' . e($anchor) . '" class="nf-anchor" aria-hidden="true"></span>'
            : '';

        foreach (($section['widgets'] ?? []) as $widget) {
            $inner .= self::renderWidget($widget);
        }

        return '<section ' . self::attrs($section, $class) . '>' . $inner . '</section>';
    }

    public static function renderColumn(array $column): string
    {
        $inner = '';
        foreach (($column['widgets'] ?? []) as $widget) {
            $inner .= self::renderWidget($widget);
        }
        return '<div ' . self::attrs($column, 'nf-col') . '>' . $inner . '</div>';
    }

    public static function renderWidget(array $widget): string
    {
        $type = (string) ($widget['type'] ?? '');
        $def  = BlockRegistry::getInstance()->get($type);
        if (!$def || !isset($def['render']) || !is_callable($def['render'])) {
            return '';
        }
        return (string) call_user_func($def['render'], (array) ($widget['data'] ?? []), $widget);
    }

    /**
     * id и class узла. id — `nf-{id}` (по нему генерируется CSS и находятся оверлеи).
     */
    public static function attrs(array $node, string $classes = ''): string
    {
        $id      = (string) ($node['id'] ?? '');
        $cssClass = trim((string) ($node['advanced']['cssClass'] ?? ''));

        $parts = array_values(array_filter(array_merge(explode(' ', trim($classes)), [$cssClass])));
        $s = 'id="nf-' . e($id) . '"';
        if ($parts) {
            $s .= ' class="' . e(implode(' ', $parts)) . '"';
        }
        return $s;
    }
}
