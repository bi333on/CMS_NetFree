<?php

declare(strict_types=1);

namespace NetFree\Builder;

use NetFree\Content\HtmlSanitizer;

/**
 * Разбор, валидация и нормализация JSON-документа конструктора.
 * Единственный вход недоверенных данных: проставляет недостающие id,
 * выкидывает неизвестные ключи и типы, прогоняет поля через санитайзер.
 */
class Document
{
    /** Ключи design-свойств, которые понимает генератор CSS. */
    protected const DESIGN_KEYS = [
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
        'background', 'border', 'borderRadius', 'boxShadow',
        'minHeight', 'verticalAlign',
        'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform', 'color', 'align',
        'width',
    ];

    public static function parse(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? self::normalize($data) : self::empty();
    }

    public static function empty(): array
    {
        return ['version' => 1, 'sections' => []];
    }

    public static function normalize(array $doc): array
    {
        $out = ['version' => 1, 'sections' => []];

        foreach (($doc['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $out['sections'][] = self::section($section);
        }

        return $out;
    }

    protected static function section(array $s): array
    {
        $type = (string) ($s['type'] ?? 'section') === 'free' ? 'free' : 'section';

        // Свободная секция (Zero Block): плоский список виджетов, без колонок.
        if ($type === 'free') {
            $widgets = [];
            foreach (($s['widgets'] ?? []) as $widget) {
                if (!is_array($widget)) {
                    continue;
                }
                $normalized = self::widget($widget, true);
                if ($normalized !== null) {
                    $widgets[] = $normalized;
                }
            }

            return [
                'id'       => self::id($s),
                'type'     => 'free',
                'settings' => [
                    'width'    => (string) ($s['settings']['width'] ?? 'boxed') === 'full' ? 'full' : 'boxed',
                    'height'   => self::pixels($s['settings']['height'] ?? null),
                    'minHeight'=> self::pixels($s['settings']['minHeight'] ?? null),
                ],
                'design'   => self::design((array) ($s['design'] ?? [])),
                'advanced' => self::advanced((array) ($s['advanced'] ?? [])),
                'widgets'  => $widgets,
            ];
        }

        $columns = [];
        foreach (($s['columns'] ?? []) as $column) {
            if (!is_array($column)) {
                continue;
            }
            $columns[] = self::column($column);
        }

        return [
            'id'       => self::id($s),
            'type'     => 'section',
            'settings' => [
                'width' => (string) ($s['settings']['width'] ?? 'boxed') === 'full' ? 'full' : 'boxed',
                'gap'   => max(0, (int) ($s['settings']['gap'] ?? 24)),
            ],
            'design'   => self::design((array) ($s['design'] ?? [])),
            'advanced' => self::advanced((array) ($s['advanced'] ?? [])),
            'columns'  => $columns,
        ];
    }

    protected static function column(array $c): array
    {
        $width = (array) ($c['settings']['width'] ?? []);
        $widgets = [];
        foreach (($c['widgets'] ?? []) as $widget) {
            if (!is_array($widget)) {
                continue;
            }
            $normalized = self::widget($widget);
            if ($normalized !== null) {
                $widgets[] = $normalized;
            }
        }

        return [
            'id'       => self::id($c),
            'type'     => 'column',
            'settings' => [
                'width' => [
                    'desktop' => self::percent($width['desktop'] ?? 100),
                    'tablet'  => self::percent($width['tablet'] ?? null),
                    'mobile'  => self::percent($width['mobile'] ?? null),
                ],
            ],
            'design'   => self::design((array) ($c['design'] ?? [])),
            'advanced' => self::advanced((array) ($c['advanced'] ?? [])),
            'widgets'  => $widgets,
        ];
    }

    protected static function widget(array $w, bool $free = false): ?array
    {
        $type = (string) ($w['type'] ?? '');
        $def  = BlockRegistry::getInstance()->get($type);
        if (!$def) {
            return null; // неизвестный тип — выкидываем
        }

        $data = [];
        $dataRaw = is_array($w['data'] ?? null) ? $w['data'] : [];
        foreach ((array) ($def['fields'] ?? []) as $key => $field) {
            $value = $dataRaw[$key] ?? $field['default'] ?? '';
            $data[$key] = self::field($value, (array) $field);
        }

        $widget = [
            'id'       => self::id($w),
            'type'     => $type,
            'data'     => $data,
            'design'   => self::design((array) ($w['design'] ?? [])),
            'advanced' => self::advanced((array) ($w['advanced'] ?? [])),
        ];

        // Свободный виджет: абсолютное позиционирование внутри free-секции.
        if ($free) {
            $widget['settings'] = [
                'pos' => self::position((array) ($w['settings']['pos'] ?? [])),
                'z'   => (int) ($w['settings']['z'] ?? 0),
            ];
        }

        return $widget;
    }

    /**
     * Позиция свободного виджета: x/y/w в % (0..100), h в px или null.
     */
    protected static function position(array $pos): array
    {
        return [
            'x' => self::clamp((float) ($pos['x'] ?? 0), 0, 100),
            'y' => self::clamp((float) ($pos['y'] ?? 0), 0, 100),
            'w' => self::clamp((float) ($pos['w'] ?? 50), 1, 100),
            'h' => self::pixels($pos['h'] ?? null),
        ];
    }

    protected static function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }

    protected static function pixels($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    protected static function field($value, array $field): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        switch ($field['type'] ?? 'text') {
            case 'richtext':
                return HtmlSanitizer::clean($value, 'rich');
            case 'html':
                return HtmlSanitizer::clean($value, 'html');
            case 'url':
                return HtmlSanitizer::safeUrl(trim($value)) ? trim($value) : '';
            case 'number':
                return (string) (int) $value;
            default:
                return $value;
        }
    }

    protected static function design(array $design): array
    {
        $out = [];
        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            if (!isset($design[$bp]) || !is_array($design[$bp])) {
                continue;
            }
            $out[$bp] = [];
            foreach ($design[$bp] as $key => $value) {
                if (in_array($key, self::DESIGN_KEYS, true)) {
                    $out[$bp][$key] = $value;
                }
            }
        }
        return $out;
    }

    protected static function advanced(array $a): array
    {
        $out = ['anchor' => '', 'cssClass' => '', 'hideOn' => [], 'showOnly' => []];

        if (isset($a['anchor']) && is_string($a['anchor'])) {
            $anchor = preg_replace('/[^a-zA-Z0-9\-_:.]/', '', (string) $a['anchor']);
            $out['anchor'] = is_string($anchor) ? $anchor : '';
        }
        if (isset($a['cssClass']) && is_string($a['cssClass'])) {
            $out['cssClass'] = trim((string) $a['cssClass']);
        }
        if (isset($a['hideOn']) && is_array($a['hideOn'])) {
            $out['hideOn'] = array_values(array_intersect($a['hideOn'], ['tablet', 'mobile']));
        }
        if (isset($a['showOnly']) && is_array($a['showOnly'])) {
            $out['showOnly'] = array_values(array_intersect($a['showOnly'], ['tablet', 'mobile']));
        }

        return $out;
    }

    protected static function id(array $node): string
    {
        $id = trim((string) ($node['id'] ?? ''));
        if ($id === '') {
            return self::randomId();
        }
        $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $id);
        return $id !== '' && $id !== null ? $id : self::randomId();
    }

    protected static function randomId(): string
    {
        return 'n' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    protected static function percent($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (int) $value;
        return max(0, min(100, $v));
    }
}
