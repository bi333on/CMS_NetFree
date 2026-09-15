<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * Библиотека готовых шаблонов секций (templates)
 * для быстрого создания популярных блоков сайта.
 */
class TemplateLibrary
{
    protected static array $templates = [];
    protected static bool $loaded = false;

    /**
     * Загружает встроенные шаблоны при первом обращении.
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::registerCore();
        do_action('netfree.register_templates', self::class);
        self::$loaded = true;
    }

    public static function register(string $key, array $template): void
    {
        self::$templates[$key] = $template;
    }

    public static function get(string $key): ?array
    {
        self::load();
        return self::$templates[$key] ?? null;
    }

    public static function all(): array
    {
        self::load();
        return self::$templates;
    }

    /**
     * JSON для фронтенда (без тяжёлых document-данных).
     */
    public static function catalogJson(): string
    {
        self::load();
        $catalog = [];
        foreach (self::$templates as $key => $tpl) {
            $catalog[] = [
                'key'         => $key,
                'title'       => $tpl['title'] ?? $key,
                'description' => $tpl['description'] ?? '',
                'category'    => $tpl['category'] ?? 'Прочее',
                'thumbnail'   => $tpl['thumbnail'] ?? '',
            ];
        }
        return json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Регистрирует встроенные шаблоны.
     */
    protected static function registerCore(): void
    {
        // Hero секция с заголовком и кнопкой
        self::register('hero-simple', [
            'title'       => 'Hero — простой',
            'description' => 'Заголовок, подзаголовок и кнопка призыва к действию',
            'category'    => 'Hero',
            'thumbnail'   => '',
            'document'    => [
                'id'       => 'hero1',
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [
                    'desktop' => [
                        'paddingTop'    => 80,
                        'paddingBottom' => 80,
                        'align'         => 'center',
                    ],
                ],
                'advanced' => [],
                'columns'  => [
                    [
                        'id'       => 'col1',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 100]],
                        'design'   => [],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'w1',
                                'type'     => 'heading',
                                'data'     => [
                                    'text'  => 'Создайте что-то удивительное',
                                    'level' => 'h1',
                                ],
                                'design'   => [
                                    'desktop' => [
                                        'fontSize'   => 48,
                                        'fontWeight' => 700,
                                        'marginBottom' => 16,
                                    ],
                                ],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'w2',
                                'type'     => 'text',
                                'data'     => [
                                    'text' => '<p>Мощный инструмент для воплощения ваших идей в жизнь. Начните прямо сейчас.</p>',
                                ],
                                'design'   => [
                                    'desktop' => [
                                        'fontSize'     => 18,
                                        'color'        => '#64748b',
                                        'marginBottom' => 32,
                                    ],
                                ],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'w3',
                                'type'     => 'button',
                                'data'     => [
                                    'text'   => 'Начать работу',
                                    'href'   => '#',
                                    'target' => '_self',
                                ],
                                'design'   => [],
                                'advanced' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Features — три колонки с иконками
        self::register('features-3col', [
            'title'       => 'Преимущества — 3 колонки',
            'description' => 'Три карточки с иконками, заголовками и описанием',
            'category'    => 'Features',
            'thumbnail'   => '',
            'document'    => [
                'id'       => 'feat1',
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [
                    'desktop' => [
                        'paddingTop'    => 60,
                        'paddingBottom' => 60,
                    ],
                ],
                'advanced' => [],
                'columns'  => [
                    [
                        'id'       => 'col1',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => ['desktop' => ['align' => 'center']],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'ic1',
                                'type'     => 'icon',
                                'data'     => ['icon' => 'check', 'size' => 48, 'color' => '#3b82f6'],
                                'design'   => ['desktop' => ['marginBottom' => 16]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'h1',
                                'type'     => 'heading',
                                'data'     => ['text' => 'Быстро', 'level' => 'h3'],
                                'design'   => ['desktop' => ['marginBottom' => 8]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 't1',
                                'type'     => 'text',
                                'data'     => ['text' => '<p>Молниеносная скорость работы и отклика системы.</p>'],
                                'design'   => ['desktop' => ['color' => '#64748b']],
                                'advanced' => [],
                            ],
                        ],
                    ],
                    [
                        'id'       => 'col2',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => ['desktop' => ['align' => 'center']],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'ic2',
                                'type'     => 'icon',
                                'data'     => ['icon' => 'star', 'size' => 48, 'color' => '#3b82f6'],
                                'design'   => ['desktop' => ['marginBottom' => 16]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'h2',
                                'type'     => 'heading',
                                'data'     => ['text' => 'Надёжно', 'level' => 'h3'],
                                'design'   => ['desktop' => ['marginBottom' => 8]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 't2',
                                'type'     => 'text',
                                'data'     => ['text' => '<p>Проверенное решение, которому доверяют тысячи.</p>'],
                                'design'   => ['desktop' => ['color' => '#64748b']],
                                'advanced' => [],
                            ],
                        ],
                    ],
                    [
                        'id'       => 'col3',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => ['desktop' => ['align' => 'center']],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'ic3',
                                'type'     => 'icon',
                                'data'     => ['icon' => 'heart', 'size' => 48, 'color' => '#3b82f6'],
                                'design'   => ['desktop' => ['marginBottom' => 16]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'h3',
                                'type'     => 'heading',
                                'data'     => ['text' => 'Удобно', 'level' => 'h3'],
                                'design'   => ['desktop' => ['marginBottom' => 8]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 't3',
                                'type'     => 'text',
                                'data'     => ['text' => '<p>Интуитивный интерфейс, который не требует обучения.</p>'],
                                'design'   => ['desktop' => ['color' => '#64748b']],
                                'advanced' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Pricing — тарифные планы
        self::register('pricing-3col', [
            'title'       => 'Тарифы — 3 плана',
            'description' => 'Три тарифных плана с ценами и характеристиками',
            'category'    => 'Pricing',
            'thumbnail'   => '',
            'document'    => [
                'id'       => 'price1',
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [
                    'desktop' => [
                        'paddingTop'    => 60,
                        'paddingBottom' => 60,
                        'background'    => ['type' => 'color', 'value' => '#f8fafc'],
                    ],
                ],
                'advanced' => [],
                'columns'  => [
                    [
                        'id'       => 'col1',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => [],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'card1',
                                'type'     => 'card',
                                'data'     => [
                                    'title'   => 'Стартовый',
                                    'text'    => '<p><strong style="font-size:2rem">990₽</strong>/мес</p><p>• 10 ГБ хранилища<br>• 100 запросов/день<br>• Email поддержка</p>',
                                    'btnText' => 'Выбрать',
                                    'btnUrl'  => '#',
                                ],
                                'design'   => [],
                                'advanced' => [],
                            ],
                        ],
                    ],
                    [
                        'id'       => 'col2',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => [],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'card2',
                                'type'     => 'card',
                                'data'     => [
                                    'title'   => 'Профессиональный',
                                    'text'    => '<p><strong style="font-size:2rem">2990₽</strong>/мес</p><p>• 100 ГБ хранилища<br>• Неограниченные запросы<br>• Приоритетная поддержка</p>',
                                    'btnText' => 'Выбрать',
                                    'btnUrl'  => '#',
                                ],
                                'design'   => [],
                                'advanced' => [],
                            ],
                        ],
                    ],
                    [
                        'id'       => 'col3',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 33, 'tablet' => 100, 'mobile' => 100]],
                        'design'   => [],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'card3',
                                'type'     => 'card',
                                'data'     => [
                                    'title'   => 'Корпоративный',
                                    'text'    => '<p><strong style="font-size:2rem">9990₽</strong>/мес</p><p>• Безлимитное хранилище<br>• API доступ<br>• Персональный менеджер</p>',
                                    'btnText' => 'Связаться',
                                    'btnUrl'  => '#',
                                ],
                                'design'   => [],
                                'advanced' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // CTA секция
        self::register('cta-centered', [
            'title'       => 'Призыв к действию',
            'description' => 'Центрированный блок с призывом к действию',
            'category'    => 'CTA',
            'thumbnail'   => '',
            'document'    => [
                'id'       => 'cta1',
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [
                    'desktop' => [
                        'paddingTop'    => 60,
                        'paddingBottom' => 60,
                    ],
                ],
                'advanced' => [],
                'columns'  => [
                    [
                        'id'       => 'col1',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 100]],
                        'design'   => ['desktop' => ['align' => 'center']],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'cta1',
                                'type'     => 'cta',
                                'data'     => [
                                    'title'      => 'Готовы начать?',
                                    'text'       => '<p>Присоединяйтесь к тысячам довольных клиентов уже сегодня.</p>',
                                    'buttonText' => 'Начать бесплатно',
                                    'buttonUrl'  => '#',
                                ],
                                'design'   => [],
                                'advanced' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Footer с контактами
        self::register('footer-simple', [
            'title'       => 'Footer — простой',
            'description' => 'Подвал с копирайтом и социальными ссылками',
            'category'    => 'Footer',
            'thumbnail'   => '',
            'document'    => [
                'id'       => 'footer1',
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [
                    'desktop' => [
                        'paddingTop'    => 40,
                        'paddingBottom' => 40,
                        'background'    => ['type' => 'color', 'value' => '#1e293b'],
                        'color'         => '#ffffff',
                        'align'         => 'center',
                    ],
                ],
                'advanced' => [],
                'columns'  => [
                    [
                        'id'       => 'col1',
                        'type'     => 'column',
                        'settings' => ['width' => ['desktop' => 100]],
                        'design'   => [],
                        'advanced' => [],
                        'widgets'  => [
                            [
                                'id'       => 'soc1',
                                'type'     => 'social',
                                'data'     => [
                                    'facebook'  => 'https://facebook.com',
                                    'twitter'   => 'https://twitter.com',
                                    'instagram' => 'https://instagram.com',
                                ],
                                'design'   => ['desktop' => ['marginBottom' => 16]],
                                'advanced' => [],
                            ],
                            [
                                'id'       => 'txt1',
                                'type'     => 'text',
                                'data'     => [
                                    'text' => '<p>© 2024 Ваша компания. Все права защищены.</p>',
                                ],
                                'design'   => ['desktop' => ['fontSize' => 14, 'color' => '#94a3b8']],
                                'advanced' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
