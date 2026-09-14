<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * Design-свойства → CSS. Селекторы по id `#nf-{id}` (специфичность 100),
 * поэтому правила уверенно перебивают стили темы без !important.
 */
class CssGenerator
{
    /** Свойства, которым числовое значение добавляет px. */
    protected const PX = [
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
        'borderRadius', 'minHeight', 'fontSize', 'letterSpacing',
    ];

    protected const BREAKPOINTS = [
        'tablet' => '1024px',
        'mobile' => '767px',
    ];

    public static function generate(array $doc): string
    {
        $css = '';
        foreach (($doc['sections'] ?? []) as $section) {
            $css .= self::nodeCss($section);
            foreach (($section['columns'] ?? []) as $column) {
                $css .= self::columnCss($column);
                foreach (($column['widgets'] ?? []) as $widget) {
                    $css .= self::nodeCss($widget);
                }
            }
        }
        return $css;
    }

    /** Свойства, которым в live-редакторе нужно добавлять px. */
    public static function pxProps(): array
    {
        return self::PX;
    }

    /** Брейкпоинты в том виде, в каком их понимает live-редактор. */
    public static function breakpoints(): array
    {
        return self::BREAKPOINTS;
    }

    /**
     * Таблица соответствия design-ключей → CSS-свойства.
     * Экспортируется в JSON для live-редактора в браузере.
     */
    public static function propMap(): array
    {
        return [
            'paddingTop'    => 'padding-top',
            'paddingRight'  => 'padding-right',
            'paddingBottom' => 'padding-bottom',
            'paddingLeft'   => 'padding-left',
            'marginTop'     => 'margin-top',
            'marginRight'   => 'margin-right',
            'marginBottom'  => 'margin-bottom',
            'marginLeft'    => 'margin-left',
            'border'        => 'border',
            'borderRadius'  => 'border-radius',
            'boxShadow'     => 'box-shadow',
            'minHeight'     => 'min-height',
            'verticalAlign' => 'vertical-align',
            'fontSize'      => 'font-size',
            'fontWeight'    => 'font-weight',
            'lineHeight'    => 'line-height',
            'letterSpacing' => 'letter-spacing',
            'textTransform' => 'text-transform',
            'color'         => 'color',
            'align'         => 'text-align',
            'width'         => 'width',
        ];
    }

    /**
     * CSS для обычного узла (design + скрытие на устройствах).
     */
    protected static function nodeCss(array $node): string
    {
        $selector = '#nf-' . $node['id'];
        $css = '';

        $design = (array) ($node['design'] ?? []);
        if ($design) {
            $css .= self::designRules($selector, $design);
        }

        foreach ((array) ($node['advanced']['hideOn'] ?? []) as $bp) {
            $css .= self::mediaOpen($bp) . $selector . '{display:none!important}' . self::mediaClose();
        }

        return $css;
    }

    /**
     * CSS колонки: ширина (flex) + обычные design-правила.
     */
    protected static function columnCss(array $column): string
    {
        $selector = '#nf-' . $column['id'];
        $width = (array) ($column['settings']['width'] ?? []);
        $css = '';

        if (isset($width['desktop'])) {
            $css .= $selector . '{flex:0 0 ' . $width['desktop'] . '%;max-width:' . $width['desktop'] . "%;}\n";
        }
        foreach (self::BREAKPOINTS as $bp => $max) {
            if (isset($width[$bp])) {
                $css .= '@media (max-width:' . $max . '){' . $selector . '{flex:0 0 ' . $width[$bp] . '%;max-width:' . $width[$bp] . "%;}}\n";
            }
        }

        $css .= self::nodeCss($column);
        return $css;
    }

    protected static function designRules(string $selector, array $design): string
    {
        $css = '';

        if (!empty($design['desktop'])) {
            $css .= self::block($selector, $design['desktop']);
        }

        foreach (self::BREAKPOINTS as $bp => $max) {
            if (!empty($design[$bp])) {
                $css .= '@media (max-width:' . $max . '){' . self::block($selector, $design[$bp]) . "}\n";
            }
        }

        return $css;
    }

    protected static function block(string $selector, array $props): string
    {
        $decls = self::declarations($props);
        if ($decls === '') {
            return '';
        }
        return $selector . '{' . $decls . "}\n";
    }

    protected static function declarations(array $props): string
    {
        $map   = self::propMap();
        $decls = '';

        foreach ($props as $key => $value) {
            if ($key === 'background') {
                $decls .= self::backgroundDecls($value);
                continue;
            }
            if (!isset($map[$key])) {
                continue;
            }
            $cssProp = $map[$key];
            $decls .= self::decl($cssProp, $key, $value);
        }

        return $decls;
    }

    protected static function decl(string $cssProp, string $key, $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (in_array($key, self::PX, true)) {
            $value = self::unit($value);
        }
        if (is_string($value)) {
            return $cssProp . ':' . $value . ';';
        }
        if (is_numeric($value)) {
            return $cssProp . ':' . $value . ';';
        }
        return '';
    }

    protected static function unit($value): string
    {
        if (is_numeric($value)) {
            return $value . 'px';
        }
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            return trim($value) . 'px';
        }
        return (string) $value;
    }

    protected static function backgroundDecls($value): string
    {
        if (!is_array($value)) {
            return '';
        }
        $type = (string) ($value['type'] ?? 'color');
        $val  = (string) ($value['value'] ?? '');

        switch ($type) {
            case 'gradient':
                return $val !== '' ? 'background-image:' . $val . ';' : '';
            case 'image':
                return $val !== '' ? "background-image:url('" . str_replace("'", "\\'", $val) . "');background-size:cover;background-position:center;" : '';
            case 'color':
            default:
                return $val !== '' ? 'background-color:' . $val . ';' : '';
        }
    }

    protected static function mediaOpen(string $bp): string
    {
        $max = self::BREAKPOINTS[$bp] ?? '1024px';
        return '@media (max-width:' . $max . '){';
    }

    protected static function mediaClose(): string
    {
        return "}\n";
    }
}
