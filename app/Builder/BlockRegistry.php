<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * Реестр виджетов конструктора.
 *
 * Определение виджета — чистый PHP, включая описание полей инспектора.
 * Инспектор в браузере строится по schemaJson(), поэтому плагин добавляет
 * виджет без единой строчки JS: достаточно register() + do_action.
 */
class BlockRegistry
{
    protected static ?BlockRegistry $instance = null;

    /** @var array<string, array> */
    protected array $blocks = [];

    protected bool $booted = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Регистрирует ядровые виджеты и даёт плагинам добавить свои.
     * Ленивый: вызывается при первом обращении (после загрузки плагинов).
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->registerCore();
        do_action('netfree.register_blocks', $this);
        $this->booted = true;
    }

    public function register(string $type, array $def): void
    {
        $this->blocks[$type] = $def;
    }

    public function has(string $type): bool
    {
        $this->boot();
        return isset($this->blocks[$type]);
    }

    public function get(string $type): ?array
    {
        $this->boot();
        return $this->blocks[$type] ?? null;
    }

    public function all(): array
    {
        $this->boot();
        return $this->blocks;
    }

    /**
     * JSON-схема реестра для инспектора браузера (без render-колбэков).
     */
    public function schemaJson(): string
    {
        $this->boot();
        $out = [];
        foreach ($this->blocks as $type => $def) {
            $out[$type] = [
                'type'     => $type,
                'label'    => $def['label'] ?? $type,
                'category' => $def['category'] ?? 'Прочее',
                'icon'     => $def['icon'] ?? '',
                'fields'   => $def['fields'] ?? [],
                'design'   => $def['design'] ?? [],
                'advanced' => $def['advanced'] ?? [],
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function registerCore(): void
    {
        $this->register('heading', [
            'label'    => 'Заголовок',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 5v2h5v12h2V7h5V5H4zm13 0v2h2v10h-2v2h6v-2h-2V7h2V5h-6z"/></svg>',
            'fields'   => [
                'text'  => ['type' => 'text', 'label' => 'Текст', 'default' => 'Заголовок'],
                'level' => ['type' => 'select', 'label' => 'Уровень',
                            'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6'],
                            'default' => 'h2'],
            ],
            'design'   => ['typography', 'spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $level = in_array($d['level'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $d['level'] : 'h2';
                return sprintf('<%1$s %2$s>%3$s</%1$s>', $level, Renderer::attrs($node, 'nf-widget nf-heading'), e((string) ($d['text'] ?? 'Заголовок')));
            },
        ]);

        $this->register('text', [
            'label'    => 'Текст',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M5 4h14v2H5V4zm0 5h14v2H5V9zm0 5h10v2H5v-2zm0 5h14v2H5v-2z"/></svg>',
            'fields'   => [
                'text' => ['type' => 'richtext', 'label' => 'Текст', 'default' => ''],
            ],
            'design'   => ['typography', 'spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-text') . '>' . (string) ($d['text'] ?? '') . '</div>';
            },
        ]);

        $this->register('image', [
            'label'    => 'Изображение',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M21 5H3v14h18V5zm-2 2v8.6l-3.2-3.2-5.3 5.3L7 14.2 5 16.2V7h14z"/></svg>',
            'fields'   => [
                'src'     => ['type' => 'url', 'label' => 'Изображение (URL)', 'default' => ''],
                'alt'     => ['type' => 'text', 'label' => 'Alt-текст', 'default' => ''],
                'caption' => ['type' => 'text', 'label' => 'Подпись', 'default' => ''],
                'href'    => ['type' => 'url', 'label' => 'Ссылка', 'default' => ''],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $src = (string) ($d['src'] ?? '');
                if ($src === '') {
                    return '';
                }
                $img = '<img src="' . e($src) . '" alt="' . e((string) ($d['alt'] ?? '')) . '">';
                $caption = trim((string) ($d['caption'] ?? ''));
                if ($caption !== '') {
                    $img .= '<figcaption>' . e($caption) . '</figcaption>';
                }
                $html = '<figure ' . Renderer::attrs($node, 'nf-widget nf-image') . '>' . $img . '</figure>';
                $href = (string) ($d['href'] ?? '');
                if ($href !== '') {
                    $html = '<a href="' . e($href) . '" class="nf-image-link">' . $html . '</a>';
                }
                return $html;
            },
        ]);

        $this->register('button', [
            'label'    => 'Кнопка',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 5h16v4h-2V7H6v10h12v-2h2v4H4V5z"/></svg>',
            'fields'   => [
                'text'   => ['type' => 'text', 'label' => 'Текст', 'default' => 'Кнопка'],
                'href'   => ['type' => 'url', 'label' => 'Ссылка', 'default' => '#'],
                'target' => ['type' => 'select', 'label' => 'Открывать',
                             'options' => ['_self' => 'В этой вкладке', '_blank' => 'В новой вкладке'],
                             'default' => '_self'],
            ],
            'design'   => ['typography', 'spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $href   = (string) ($d['href'] ?? '#');
                $target = ($d['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                $rel    = $target === '_blank' ? ' rel="noopener"' : '';
                return sprintf(
                    '<a %s href="%s" target="%s"%s>%s</a>',
                    Renderer::attrs($node, 'nf-widget nf-btn'),
                    e($href),
                    e($target),
                    $rel,
                    e((string) ($d['text'] ?? 'Кнопка'))
                );
            },
        ]);

        $this->register('divider', [
            'label'    => 'Разделитель',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M2 11h20v2H2v-2z"/></svg>',
            'fields'   => [],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                return '<hr ' . Renderer::attrs($node, 'nf-widget nf-divider') . '>';
            },
        ]);

        $this->register('spacer', [
            'label'    => 'Отступ',
            'category' => 'Раскладка',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M6 5h2v14H6V5zm10 0h2v14h-2V5z"/></svg>',
            'fields'   => [
                'height' => ['type' => 'number', 'label' => 'Высота, px', 'default' => 24],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $h = max(0, (int) ($d['height'] ?? 24));
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-spacer') . ' style="height:' . $h . 'px;"></div>';
            },
        ]);

        $this->register('html', [
            'label'    => 'HTML',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M8 6L2 12l6 6 1.4-1.4L4.8 12l4.6-4.6L8 6zm8 0l-1.4 1.4L19.2 12l-4.6 4.6L16 18l6-6-6-6z"/></svg>',
            'fields'   => [
                'html' => ['type' => 'html', 'label' => 'HTML', 'default' => ''],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-html') . '>' . (string) ($d['html'] ?? '') . '</div>';
            },
        ]);

        $this->register('list', [
            'label'    => 'Список',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 5h2v2H4V5zm4 0h12v2H8V5zM4 11h2v2H4v-2zm4 0h12v2H8v-2zm-4 6h2v2H4v-2zm4 0h12v2H8v-2z"/></svg>',
            'fields'   => [
                'html' => ['type' => 'html', 'label' => 'HTML списка', 'default' => ''],
            ],
            'design'   => ['typography', 'spacing'],
            'render'   => function (array $d, array $node): string {
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-list') . '>' . (string) ($d['html'] ?? '') . '</div>';
            },
        ]);

        $this->register('quote', [
            'label'    => 'Цитата',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg>',
            'fields'   => [
                'text' => ['type' => 'richtext', 'label' => 'Текст цитаты', 'default' => ''],
                'cite' => ['type' => 'text', 'label' => 'Источник', 'default' => ''],
            ],
            'design'   => ['typography', 'spacing'],
            'render'   => function (array $d, array $node): string {
                return '<blockquote ' . Renderer::attrs($node, 'nf-widget nf-quote') . '>' . (string) ($d['text'] ?? '') . '</blockquote>';
            },
        ]);
    }
}
