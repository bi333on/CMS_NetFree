<?php

declare(strict_types=1);

namespace NetFree\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Санитайзер HTML по белому списку, без сторонних библиотек.
 * Запускается на сохранении (не на рендере), чтобы не платить на каждом запросе.
 *
 * Профили:
 *  - rich  — текстовый виджет (инлайн-разметка, списки, ссылки);
 *  - html  — виджет «сырой HTML» (широкая разметка, но без скриптов/обработчиков);
 *  - plain — только текст без разметки.
 */
class HtmlSanitizer
{
    protected const TAGS = [
        'rich' => ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'code', 'span', 'img'],
        'html' => [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'code', 'pre', 'span',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'img', 'figure', 'figcaption', 'blockquote', 'hr',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
            'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main',
            'video', 'audio', 'source',
        ],
    ];

    /** Теги, которые вырезаются целиком (вместе с содержимым). */
    protected const REMOVE = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'select', 'textarea', 'svg', 'math', 'link', 'meta', 'base', 'title', 'head', 'noscript',
    ];

    /** Атрибуты, допустимые по тегам (+ class — глобально). */
    protected const ATTRS = [
        'a'      => ['href', 'title', 'target', 'rel'],
        'img'    => ['src', 'alt', 'title', 'width', 'height'],
        'td'     => ['colspan', 'rowspan'],
        'th'     => ['colspan', 'rowspan'],
        'video'  => ['src', 'poster', 'controls', 'width', 'height'],
        'audio'  => ['src', 'controls'],
        'source' => ['src', 'type'],
    ];

    protected const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(string $html, string $profile = 'rich'): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if ($profile === 'plain') {
            $doc = self::load($html);
            $root = $doc ? self::root($doc) : null;
            return $root ? trim($root->textContent) : '';
        }

        $tags = self::TAGS[$profile] ?? self::TAGS['rich'];
        $doc = self::load($html);
        if (!$doc) {
            return '';
        }
        $root = self::root($doc);
        if (!$root) {
            return '';
        }

        self::sanitizeChildren($root, $tags);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    protected static function load(string $html): ?DOMDocument
    {
        if (!class_exists(DOMDocument::class)) {
            return null;
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // Обёртка гарантирует валидный фрагмент; PI задаёт UTF-8.
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div id="nf-sanitize-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $loaded ? $doc : null;
    }

    protected static function root(DOMDocument $doc): ?DOMElement
    {
        $xpath = new \DOMXPath($doc);
        $node = $xpath->query("//*[@id='nf-sanitize-root']")->item(0);
        return $node instanceof DOMElement ? $node : null;
    }

    protected static function sanitizeChildren(DOMNode $parent, array $tags): void
    {
        // Снимок, т.к. дерево меняется при вырезании/разворачивании.
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::sanitizeNode($child, $tags);
        }
    }

    protected static function sanitizeNode(DOMNode $node, array $tags): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::REMOVE, true)) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
                return;
            }

            if (!in_array($tag, $tags, true)) {
                self::unwrap($node, $tags);
                return;
            }

            self::sanitizeAttributes($node);
            self::sanitizeChildren($node, $tags);
            return;
        }

        // Комментарии убираем, текст оставляем.
        if ($node->nodeType === XML_COMMENT_NODE && $node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Разворачивает тег: заменяет его детьми (например <font> → текст), затем
     * прогоняет детей через те же правила.
     */
    protected static function unwrap(DOMNode $node, array $tags): void
    {
        $parent = $node->parentNode;
        $doc = $node->ownerDocument;
        if (!$parent || !$doc) {
            return;
        }

        $fragment = $doc->createDocumentFragment();
        while ($node->firstChild) {
            $fragment->appendChild($node->firstChild);
        }
        $parent->replaceChild($fragment, $node);
        self::sanitizeChildren($fragment, $tags);
    }

    protected static function sanitizeAttributes(DOMElement $el): void
    {
        $tag = strtolower($el->nodeName);
        $keep = array_merge(['class'], self::ATTRS[$tag] ?? []);

        $remove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);

            if (str_starts_with($name, 'on')) {
                $remove[] = $attr;
                continue;
            }
            if (!in_array($name, $keep, true)) {
                $remove[] = $attr;
                continue;
            }
            if (($name === 'href' || $name === 'src') && !self::safeUrl((string) $attr->value)) {
                $remove[] = $attr;
                continue;
            }
            if ($name === 'target' && !in_array(strtolower((string) $attr->value), ['_blank', '_self', '_parent', '_top'], true)) {
                $remove[] = $attr;
            }
        }

        foreach ($remove as $attr) {
            $el->removeAttributeNode($attr);
        }
    }

    public static function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m)) {
            return in_array(strtolower($m[1]), self::SAFE_SCHEMES, true);
        }
        // Относительные и protocol-relative ссылки допустимы.
        return true;
    }
}
