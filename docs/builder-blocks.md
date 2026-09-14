# NetFree Builder — регистрация виджетов из плагинов

Конструктор хранит контент структурно и рендерит его на PHP (`app/Builder/Renderer.php`).
Виджет — это чистый PHP-массив с описанием полей и колбэком рендера. Инспектор в
браузере строится автоматически по схеме из `BlockRegistry::schemaJson()`, поэтому
плагин добавляет виджет **без единой строчки JS**.

## Точка расширения

Плагин регистрирует виджеты в экшене `netfree.register_blocks`:

```php
add_action('netfree.register_blocks', function (\NetFree\Builder\BlockRegistry $registry) {
    $registry->register('alert', [
        'label'    => 'Предупреждение',
        'category' => 'Контент',
        'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
        'fields'   => [
            'text' => ['type' => 'richtext', 'label' => 'Текст', 'default' => ''],
            'type' => ['type' => 'select', 'label' => 'Тип',
                       'options' => ['info' => 'Инфо', 'warning' => 'Внимание', 'error' => 'Ошибка'],
                       'default' => 'info'],
        ],
        'design'   => ['spacing'],
        'render'   => function (array $d, array $node): string {
            return '<div ' . \NetFree\Builder\Renderer::attrs($node, 'nf-widget nf-alert nf-alert--' . e((string)($d['type'] ?? 'info')) . ')>'
                . ($d['text'] ?? '') . '</div>';
        },
    ]);
});
```

## Структура определения

| Ключ       | Назначение                                                              |
|------------|-------------------------------------------------------------------------|
| `label`    | Название в палитре                                                       |
| `category` | Группа в палитре («Контент», «Раскладка», «Расширенное», …)              |
| `icon`     | SVG-строка (18×18)                                                       |
| `fields`   | Описание полей инспектора (вкладка «Содержимое»)                         |
| `design`   | Группы оформления: `typography`, `spacing`, `align`                     |
| `render`   | `callable(array $d, array $node): string` — колбэк рендера               |

## Типы полей (`fields`)

| Тип        | Инспектор      | Поведение при сохранении                                |
|------------|----------------|---------------------------------------------------------|
| `text`     | строка         | как есть                                                 |
| `textarea` | многострочный  | как есть                                                 |
| `select`   | выпадающий     | как есть (`options` = `{значение: подпись}`)            |
| `url`      | строка URL     | проверяется `HtmlSanitizer::safeUrl()`; unsafe → пусто  |
| `number`   | число          | приводится к `(int)`                                    |
| `richtext` | textarea       | санитайзится профилем `rich`                            |
| `html`     | textarea       | санитайзится профилем `html`                            |

## Колбэк рендера

- `$d` — данные виджета (по ключам `fields`, значения уже нормализованы/санитайзированы).
- `$node` — узел целиком: `id`, `design`, `advanced` (`anchor`, `cssClass`, `hideOn`).

Рекомендации:
- Всегда выдавайте атрибуты через `Renderer::attrs($node, 'nf-widget …')` — это даёт
  стабильный `id="nf-{id}"`, по которому генерируется CSS (`#nf-{id}`) и работают оверлеи.
- Экранируйте пользовательские значения: `e($value)`.
- Для сложной вёрстки используйте сырые HTML-виджеты или встраивайте разметку прямо в колбэк.
- CSS оформления виджета можно положить в тему или подключить плагином через `netfree.head`.

## Пример: регистрация в `plugin.php`

```php
<?php
declare(strict_types=1);

add_action('netfree.register_blocks', function (\NetFree\Builder\BlockRegistry $registry) {
    $registry->register('my-card', [
        'label'    => 'Карточка',
        'category' => 'Контент',
        'icon'     => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16v16H4V4z"/></svg>',
        'fields'   => [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'default' => ''],
            'body'  => ['type' => 'richtext', 'label' => 'Текст', 'default' => ''],
        ],
        'design'   => ['spacing'],
        'render'   => function (array $d, array $node): string {
            return '<div ' . \NetFree\Builder\Renderer::attrs($node, 'nf-widget nf-my-card') . '>'
                . '<h3>' . e((string)($d['title'] ?? '')) . '</h3>'
                . '<div>' . ($d['body'] ?? '') . '</div>'
                . '</div>';
        },
    ]);
});
```

После активации плагина виджет появится в палитре конструктора, а инспектор
соберётся автоматически из `fields`.
