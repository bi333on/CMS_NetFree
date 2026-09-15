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
                'text' => ['type' => 'richtext', 'label' => 'Текст', 'default' => '<p>Текст абзаца. Нажмите, чтобы отредактировать.</p>'],
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
                'width'   => ['type' => 'number', 'label' => 'Ширина (px)', 'default' => ''],
                'height'  => ['type' => 'number', 'label' => 'Высота (px)', 'default' => ''],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $src = (string) ($d['src'] ?? '');
                if ($src === '') {
                    return '';
                }
                $attrs = 'src="' . e($src) . '" alt="' . e((string) ($d['alt'] ?? '')) . '"';
                if ((int) ($d['width'] ?? 0) > 0) {
                    $attrs .= ' width="' . (int) $d['width'] . '"';
                }
                if ((int) ($d['height'] ?? 0) > 0) {
                    $attrs .= ' height="' . (int) $d['height'] . '"';
                }
                $img = '<img ' . $attrs . '>';
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
                'html' => ['type' => 'html', 'label' => 'HTML', 'default' => '<p>Произвольный HTML-код</p>'],
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
                'html' => ['type' => 'html', 'label' => 'HTML списка', 'default' => '<ul><li>Первый пункт</li><li>Второй пункт</li><li>Третий пункт</li></ul>'],
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
                'text' => ['type' => 'richtext', 'label' => 'Текст цитаты', 'default' => 'Мудрая мысль, которую стоит процитировать.'],
                'cite' => ['type' => 'text', 'label' => 'Источник', 'default' => ''],
            ],
            'design'   => ['typography', 'spacing'],
            'render'   => function (array $d, array $node): string {
                return '<blockquote ' . Renderer::attrs($node, 'nf-widget nf-quote') . '>' . (string) ($d['text'] ?? '') . '</blockquote>';
            },
        ]);

        $this->register('gallery', [
            'label'    => 'Галерея',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 5h4v4H4V5zm6 0h4v4h-4V5zm6 0h4v4h-4V5zM4 11h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 17h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z"/></svg>',
            'fields'   => [
                'urls'    => ['type' => 'textarea', 'label' => 'URL изображений (по одному в строке)', 'default' => ''],
                'columns' => ['type' => 'select', 'label' => 'Колонок',
                              'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($d['urls'] ?? ''));
                $urls = array_values(array_filter(array_map('trim', (array) $lines)));
                if (!$urls) {
                    return '';
                }
                $cols = in_array((string) ($d['columns'] ?? '3'), ['2', '3', '4'], true) ? (int) $d['columns'] : 3;
                $items = '';
                foreach ($urls as $url) {
                    $items .= '<figure class="nf-gallery-item"><img src="' . e($url) . '" alt="" loading="lazy"></figure>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-gallery nf-gallery--cols-' . $cols) . '>' . $items . '</div>';
            },
        ]);

        $this->register('video', [
            'label'    => 'Видео',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 6h16v12H4V6zm2 2v8h12V8H6zm2 1l6 3-6 3V9z"/></svg>',
            'fields'   => [
                'url' => ['type' => 'url', 'label' => 'Ссылка (YouTube/Vimeo или .mp4)', 'default' => ''],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $url = (string) ($d['url'] ?? '');
                if ($url === '') {
                    return '';
                }
                $inner = '';
                if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
                    $inner = '<iframe src="https://www.youtube.com/embed/' . e($m[1]) . '" frameborder="0" allowfullscreen></iframe>';
                } elseif (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
                    $inner = '<iframe src="https://player.vimeo.com/video/' . e($m[1]) . '" frameborder="0" allowfullscreen></iframe>';
                } elseif (preg_match('#\.(mp4|webm|ogg)$#i', $url)) {
                    $inner = '<video controls preload="metadata" src="' . e($url) . '"></video>';
                } else {
                    $inner = '<iframe src="' . e($url) . '" frameborder="0" allowfullscreen></iframe>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-video') . '>' . $inner . '</div>';
            },
        ]);

        $this->register('accordion', [
            'label'    => 'Аккордеон',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 5h16v2H4V5zm0 5h16v2H4v-2zm0 5h16v2H4v-2zm0 5h10v2H4v-2z"/></svg>',
            'fields'   => [
                'items' => ['type' => 'textarea', 'label' => 'Пункты: «Заголовок || Текст» (по строке)', 'default' => "Первый пункт || Содержимое первого пункта\nВторой пункт || Содержимое второго пункта"],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($d['items'] ?? ''));
                $lines = array_values(array_filter(array_map('trim', (array) $lines)));
                if (!$lines) {
                    return '';
                }
                $html = '';
                foreach ($lines as $i => $line) {
                    $parts = explode('||', $line, 2);
                    $title = trim($parts[0] ?? '');
                    $content = trim($parts[1] ?? '');
                    $html .= '<details class="nf-acc-item"' . ($i === 0 ? ' open' : '') . '>'
                        . '<summary>' . e($title) . '</summary>'
                        . '<div class="nf-acc-body">' . e($content) . '</div>'
                        . '</details>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-accordion') . '>' . $html . '</div>';
            },
        ]);

        $this->register('tabs', [
            'label'    => 'Табы',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>',
            'fields'   => [
                'items' => ['type' => 'textarea', 'label' => 'Вкладки: «Заголовок || Текст» (по строке)', 'default' => "Вкладка 1 || Содержимое первой вкладки\nВкладка 2 || Содержимое второй вкладки"],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($d['items'] ?? ''));
                $lines = array_values(array_filter(array_map('trim', (array) $lines)));
                if (!$lines) {
                    return '';
                }
                $uid = substr((string) $node['id'], -6);
                $inputs = '';
                $labels = '';
                $panels = '';
                foreach ($lines as $i => $line) {
                    $parts = explode('||', $line, 2);
                    $title = trim($parts[0] ?? '');
                    $content = trim($parts[1] ?? '');
                    $id = 'nf-tab-' . $uid . '-' . $i;
                    $inputs .= '<input type="radio" name="nf-tabs-' . $uid . '" id="' . e($id) . '"' . ($i === 0 ? ' checked' : '') . '>';
                    $labels .= '<label class="nf-tabs-label" for="' . e($id) . '">' . e($title) . '</label>';
                    $panels .= '<div class="nf-tabs-panel">' . e($content) . '</div>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-tabs') . '>'
                    . $inputs
                    . '<div class="nf-tabs-labels">' . $labels . '</div>'
                    . '<div class="nf-tabs-panels">' . $panels . '</div>'
                    . '</div>';
            },
        ]);

        $this->register('posts', [
            'label'    => 'Лента записей',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16v2H4V4zm0 4h16v2H4V8zm0 4h10v2H4v-2zm0 4h7v2H4v-2z"/></svg>',
            'fields'   => [
                'count' => ['type' => 'number', 'label' => 'Количество', 'default' => 3],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $count = max(1, min(12, (int) ($d['count'] ?? 3)));
                $posts = array_slice(\NetFree\Content\PostRepository::published(), 0, $count);
                if (!$posts) {
                    return '';
                }
                $items = '';
                foreach ($posts as $post) {
                    $items .= '<article class="nf-post-card">'
                        . '<a class="nf-post-card-title" href="' . e(url('blog/' . ($post['slug'] ?? ''))) . '">' . e($post['title'] ?? '') . '</a>'
                        . '<div class="nf-post-card-excerpt">' . e($post['excerpt'] ?? '') . '</div>'
                        . '</article>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-posts') . '>' . $items . '</div>';
            },
        ]);

        $this->register('cta', [
            'label'    => 'CTA',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
            'fields'   => [
                'title'      => ['type' => 'text', 'label' => 'Заголовок', 'default' => 'Призыв к действию'],
                'text'       => ['type' => 'richtext', 'label' => 'Текст', 'default' => '<p>Краткое описание предложения.</p>'],
                'buttonText' => ['type' => 'text', 'label' => 'Текст кнопки', 'default' => 'Подробнее'],
                'buttonUrl'  => ['type' => 'url', 'label' => 'Ссылка кнопки', 'default' => '#'],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $title = trim((string) ($d['title'] ?? ''));
                $text = (string) ($d['text'] ?? '');
                $btnText = trim((string) ($d['buttonText'] ?? ''));
                $btnUrl = (string) ($d['buttonUrl'] ?? '');
                $html = '';
                if ($title !== '') {
                    $html .= '<h3 class="nf-cta-title">' . e($title) . '</h3>';
                }
                if ($text !== '') {
                    $html .= '<div class="nf-cta-text">' . $text . '</div>';
                }
                if ($btnText !== '' && $btnUrl !== '') {
                    $html .= '<a class="nf-btn" href="' . e($btnUrl) . '">' . e($btnText) . '</a>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-cta') . '>' . $html . '</div>';
            },
        ]);

        $this->register('form', [
            'label'    => 'Форма',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16v16H4V4zm2 2v2h12V6H6zm0 4v2h12v-2H6zm0 4v2h8v-2H6z"/></svg>',
            'fields'   => [
                'title'      => ['type' => 'text', 'label' => 'Заголовок', 'default' => ''],
                'action'     => ['type' => 'url', 'label' => 'URL обработчика', 'default' => ''],
                'buttonText' => ['type' => 'text', 'label' => 'Текст кнопки', 'default' => 'Отправить'],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $title = trim((string) ($d['title'] ?? ''));
                $action = (string) ($d['action'] ?? '');
                $btnText = trim((string) ($d['buttonText'] ?? ''));
                if ($btnText === '') {
                    $btnText = 'Отправить';
                }
                $html = '';
                if ($title !== '') {
                    $html .= '<h3 class="nf-form-title">' . e($title) . '</h3>';
                }
                $html .= '<form class="nf-form" method="post"' . ($action !== '' ? ' action="' . e($action) . '"' : '') . '>'
                    . '<input type="text" name="name" placeholder="Имя" required>'
                    . '<input type="email" name="email" placeholder="Email" required>'
                    . '<textarea name="message" placeholder="Сообщение" rows="4"></textarea>'
                    . '<button type="submit" class="nf-btn">' . e($btnText) . '</button>'
                    . '</form>';
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-form') . '>' . $html . '</div>';
            },
        ]);

        $this->register('card', [
            'label'    => 'Карточка',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16v16H4V4zm2 2v5h12V6H6zm0 7v5h12v-5H6z"/></svg>',
            'fields'   => [
                'image'    => ['type' => 'url', 'label' => 'Изображение', 'default' => ''],
                'title'    => ['type' => 'text', 'label' => 'Заголовок', 'default' => 'Заголовок карточки'],
                'text'     => ['type' => 'richtext', 'label' => 'Текст', 'default' => '<p>Описание карточки. Расскажите о продукте или услуге.</p>'],
                'btnText'  => ['type' => 'text', 'label' => 'Текст кнопки', 'default' => 'Подробнее'],
                'btnUrl'   => ['type' => 'url', 'label' => 'Ссылка', 'default' => '#'],
            ],
            'design'   => ['spacing', 'typography'],
            'render'   => function (array $d, array $node): string {
                $html = '';
                $img = (string) ($d['image'] ?? '');
                if ($img !== '') {
                    $html .= '<div class="nf-card-img"><img src="' . e($img) . '" alt="' . e((string) ($d['title'] ?? '')) . '" loading="lazy"></div>';
                }
                $html .= '<div class="nf-card-body">';
                $title = trim((string) ($d['title'] ?? ''));
                if ($title !== '') {
                    $html .= '<h3 class="nf-card-title">' . e($title) . '</h3>';
                }
                $text = (string) ($d['text'] ?? '');
                if ($text !== '') {
                    $html .= '<div class="nf-card-text">' . $text . '</div>';
                }
                $btnText = trim((string) ($d['btnText'] ?? ''));
                $btnUrl = (string) ($d['btnUrl'] ?? '');
                if ($btnText !== '' && $btnUrl !== '') {
                    $html .= '<a class="nf-btn" href="' . e($btnUrl) . '">' . e($btnText) . '</a>';
                }
                $html .= '</div>';
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-card') . '>' . $html . '</div>';
            },
        ]);

        $this->register('icon', [
            'label'    => 'Иконка',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/></svg>',
            'fields'   => [
                'icon' => ['type' => 'select', 'label' => 'Иконка',
                           'options' => [
                               'check' => '✓ Галочка',
                               'star' => '★ Звезда',
                               'heart' => '♥ Сердце',
                               'info' => 'ℹ Информация',
                               'warning' => '⚠ Предупреждение',
                               'phone' => '📞 Телефон',
                               'email' => '✉ Email',
                               'location' => '📍 Локация',
                           ],
                           'default' => 'check'],
                'size' => ['type' => 'number', 'label' => 'Размер, px', 'default' => 48],
                'color' => ['type' => 'text', 'label' => 'Цвет', 'default' => '#3b82f6'],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $icons = [
                    'check' => '✓',
                    'star' => '★',
                    'heart' => '♥',
                    'info' => 'ℹ',
                    'warning' => '⚠',
                    'phone' => '📞',
                    'email' => '✉',
                    'location' => '📍',
                ];
                $icon = (string) ($d['icon'] ?? 'check');
                $size = max(16, (int) ($d['size'] ?? 48));
                $color = (string) ($d['color'] ?? '#3b82f6');
                $symbol = $icons[$icon] ?? $icons['check'];
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-icon') . ' style="font-size:' . $size . 'px;color:' . e($color) . ';">' . e($symbol) . '</div>';
            },
        ]);

        $this->register('progress', [
            'label'    => 'Прогресс-бар',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 6h16v4H4V6zm0 8h12v4H4v-4z"/></svg>',
            'fields'   => [
                'label'   => ['type' => 'text', 'label' => 'Название', 'default' => 'Навык'],
                'value'   => ['type' => 'number', 'label' => 'Значение (%)', 'default' => 75],
                'color'   => ['type' => 'text', 'label' => 'Цвет', 'default' => '#3b82f6'],
                'height'  => ['type' => 'number', 'label' => 'Высота, px', 'default' => 24],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $label = trim((string) ($d['label'] ?? ''));
                $value = max(0, min(100, (int) ($d['value'] ?? 75)));
                $color = (string) ($d['color'] ?? '#3b82f6');
                $height = max(8, (int) ($d['height'] ?? 24));
                $html = '';
                if ($label !== '') {
                    $html .= '<div class="nf-progress-label">' . e($label) . ' <span>' . $value . '%</span></div>';
                }
                $html .= '<div class="nf-progress-bar" style="height:' . $height . 'px;">'
                    . '<div class="nf-progress-fill" style="width:' . $value . '%;background-color:' . e($color) . ';"></div>'
                    . '</div>';
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-progress') . '>' . $html . '</div>';
            },
        ]);

        $this->register('counter', [
            'label'    => 'Счётчик',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>',
            'fields'   => [
                'number' => ['type' => 'number', 'label' => 'Число', 'default' => 1000],
                'label'  => ['type' => 'text', 'label' => 'Подпись', 'default' => 'Клиентов'],
                'prefix' => ['type' => 'text', 'label' => 'Префикс', 'default' => ''],
                'suffix' => ['type' => 'text', 'label' => 'Суффикс', 'default' => '+'],
            ],
            'design'   => ['typography', 'spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $number = (int) ($d['number'] ?? 1000);
                $label = trim((string) ($d['label'] ?? ''));
                $prefix = (string) ($d['prefix'] ?? '');
                $suffix = (string) ($d['suffix'] ?? '');
                $html = '<div class="nf-counter-number">' . e($prefix) . number_format($number, 0, ',', ' ') . e($suffix) . '</div>';
                if ($label !== '') {
                    $html .= '<div class="nf-counter-label">' . e($label) . '</div>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-counter') . '>' . $html . '</div>';
            },
        ]);

        $this->register('timeline', [
            'label'    => 'Таймлайн',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M9 3v2H4v2h5v2H4v2h5v2H4v2h5v2H4v2h16V3H9zm11 16h-9V5h9v14z"/></svg>',
            'fields'   => [
                'items' => ['type' => 'textarea', 'label' => 'События: «Год || Заголовок || Описание» (по строке)', 'default' => "2020 || Основание || Начало пути\n2022 || Рост || Расширение команды\n2024 || Успех || Достижение целей"],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($d['items'] ?? ''));
                $lines = array_values(array_filter(array_map('trim', (array) $lines)));
                if (!$lines) {
                    return '';
                }
                $html = '';
                foreach ($lines as $line) {
                    $parts = explode('||', $line, 3);
                    $year = trim($parts[0] ?? '');
                    $title = trim($parts[1] ?? '');
                    $desc = trim($parts[2] ?? '');
                    $html .= '<div class="nf-timeline-item">'
                        . '<div class="nf-timeline-marker"></div>'
                        . '<div class="nf-timeline-content">'
                        . '<div class="nf-timeline-year">' . e($year) . '</div>'
                        . '<div class="nf-timeline-title">' . e($title) . '</div>'
                        . '<div class="nf-timeline-desc">' . e($desc) . '</div>'
                        . '</div></div>';
                }
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-timeline') . '>' . $html . '</div>';
            },
        ]);

        $this->register('map', [
            'label'    => 'Карта',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
            'fields'   => [
                'lat'    => ['type' => 'text', 'label' => 'Широта', 'default' => '55.7558'],
                'lng'    => ['type' => 'text', 'label' => 'Долгота', 'default' => '37.6173'],
                'zoom'   => ['type' => 'number', 'label' => 'Масштаб', 'default' => 12],
                'height' => ['type' => 'number', 'label' => 'Высота, px', 'default' => 400],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $lat = (string) ($d['lat'] ?? '55.7558');
                $lng = (string) ($d['lng'] ?? '37.6173');
                $zoom = max(1, (int) ($d['zoom'] ?? 12));
                $height = max(200, (int) ($d['height'] ?? 400));
                // OpenStreetMap embed
                $html = '<iframe width="100%" height="' . $height . '" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" '
                    . 'src="https://www.openstreetmap.org/export/embed.html?bbox='
                    . ((float)$lng - 0.05) . '%2C' . ((float)$lat - 0.05) . '%2C'
                    . ((float)$lng + 0.05) . '%2C' . ((float)$lat + 0.05)
                    . '&amp;layer=mapnik&amp;marker=' . e($lat) . '%2C' . e($lng) . '">'
                    . '</iframe>';
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-map') . '>' . $html . '</div>';
            },
        ]);

        $this->register('social', [
            'label'    => 'Социальные кнопки',
            'category' => 'Контент',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>',
            'fields'   => [
                'facebook'  => ['type' => 'url', 'label' => 'Facebook', 'default' => ''],
                'twitter'   => ['type' => 'url', 'label' => 'Twitter/X', 'default' => ''],
                'instagram' => ['type' => 'url', 'label' => 'Instagram', 'default' => ''],
                'linkedin'  => ['type' => 'url', 'label' => 'LinkedIn', 'default' => ''],
                'youtube'   => ['type' => 'url', 'label' => 'YouTube', 'default' => ''],
            ],
            'design'   => ['spacing', 'align'],
            'render'   => function (array $d, array $node): string {
                $networks = [
                    'facebook'  => ['Fa', 'Facebook'],
                    'twitter'   => ['𝕏', 'Twitter'],
                    'instagram' => ['In', 'Instagram'],
                    'linkedin'  => ['Li', 'LinkedIn'],
                    'youtube'   => ['Yt', 'YouTube'],
                ];
                $html = '';
                foreach ($networks as $key => $info) {
                    $url = trim((string) ($d[$key] ?? ''));
                    if ($url !== '') {
                        $html .= '<a class="nf-social-btn nf-social-' . $key . '" href="' . e($url) . '" target="_blank" rel="noopener" aria-label="' . $info[1] . '">'
                            . $info[0]
                            . '</a>';
                    }
                }
                return $html !== '' ? '<div ' . Renderer::attrs($node, 'nf-widget nf-social') . '>' . $html . '</div>' : '';
            },
        ]);

        $this->register('code', [
            'label'    => 'Код',
            'category' => 'Расширенное',
            'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>',
            'fields'   => [
                'code'     => ['type' => 'html', 'label' => 'Код', 'default' => 'function hello() {\n  console.log("Hello, World!");\n}'],
                'language' => ['type' => 'select', 'label' => 'Язык',
                               'options' => [
                                   'javascript' => 'JavaScript',
                                   'php' => 'PHP',
                                   'python' => 'Python',
                                   'html' => 'HTML',
                                   'css' => 'CSS',
                                   'bash' => 'Bash',
                               ],
                               'default' => 'javascript'],
            ],
            'design'   => ['spacing'],
            'render'   => function (array $d, array $node): string {
                $code = (string) ($d['code'] ?? '');
                $lang = (string) ($d['language'] ?? 'javascript');
                return '<div ' . Renderer::attrs($node, 'nf-widget nf-code') . '>'
                    . '<pre class="nf-code-pre language-' . e($lang) . '"><code>' . e($code) . '</code></pre>'
                    . '</div>';
            },
        ]);
    }
}
