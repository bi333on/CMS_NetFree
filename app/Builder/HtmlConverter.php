<?php

declare(strict_types=1);

namespace NetFree\Builder;

use DOMDocument;
use DOMElement;
use DOMNode;
use NetFree\Content\HtmlSanitizer;

/**
 * Legacy HTML → документ конструктора (одна полноширинная секция, одна колонка).
 * Необратимая операция: перед конвертацией пишется снимок в revisions.
 */
class HtmlConverter
{
    public static function convert(string $html): array
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div id="nf-convert-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $widgets = [];
        if ($loaded) {
            $xpath = new \DOMXPath($doc);
            $root = $xpath->query("//*[@id='nf-convert-root']")->item(0);
            if ($root) {
                foreach ($root->childNodes as $child) {
                    $widget = self::nodeToWidget($doc, $child);
                    if ($widget !== null) {
                        $widgets[] = $widget;
                    }
                }
            }
        }

        return [
            'version'  => 1,
            'sections' => [[
                'id'       => self::randomId(),
                'type'     => 'section',
                'settings' => ['width' => 'boxed', 'gap' => 24],
                'design'   => [],
                'advanced' => [],
                'columns'  => [[
                    'id'       => self::randomId(),
                    'type'     => 'column',
                    'settings' => ['width' => ['desktop' => 100, 'tablet' => 100, 'mobile' => 100]],
                    'design'   => [],
                    'advanced' => [],
                    'widgets'  => $widgets,
                ]],
            ]],
        ];
    }

    protected static function nodeToWidget(DOMDocument $doc, DOMNode $node): ?array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->nodeValue);
            return $text !== '' ? self::widget('text', ['text' => $text]) : null;
        }
        if ($node->nodeType === XML_CDATA_SECTION_NODE) {
            $text = trim($node->nodeValue);
            return $text !== '' ? self::widget('text', ['text' => $text]) : null;
        }
        if (!$node instanceof DOMElement) {
            return null;
        }

        $tag = strtolower($node->nodeName);

        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                return self::widget('heading', [
                    'text'  => trim($node->textContent),
                    'level' => $tag,
                ]);

            case 'p':
                $image = self::imageOnly($node);
                if ($image !== null) {
                    return $image;
                }
                return self::widget('text', [
                    'text' => HtmlSanitizer::clean(self::innerHtml($doc, $node), 'rich'),
                ]);

            case 'img':
                return self::imageWidget($node);

            case 'figure':
                return self::figureWidget($doc, $node);

            case 'ul':
            case 'ol':
                return self::widget('list', [
                    'html' => HtmlSanitizer::clean(self::innerHtml($doc, $node), 'html'),
                ]);

            case 'blockquote':
                return self::widget('quote', [
                    'text' => HtmlSanitizer::clean(self::innerHtml($doc, $node), 'rich'),
                ]);

            case 'hr':
                return self::widget('divider', []);

            default:
                return self::widget('html', [
                    'html' => HtmlSanitizer::clean(self::innerHtml($doc, $node), 'html'),
                ]);
        }
    }

    /**
     * <p>, внутри которого только картинки (без текста и иных элементов) → виджет изображения.
     */
    protected static function imageOnly(DOMElement $p): ?array
    {
        $images = [];
        $other = false;

        foreach ($p->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                if (trim($child->nodeValue) !== '') {
                    $other = true;
                }
                continue;
            }
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'img') {
                $images[] = $child;
            } else {
                $other = true;
            }
        }

        if ($other || !$images) {
            return null;
        }
        return self::imageWidget($images[0]);
    }

    protected static function imageWidget(DOMElement $img): array
    {
        return self::widget('image', [
            'src'     => (string) $img->getAttribute('src'),
            'alt'     => (string) $img->getAttribute('alt'),
            'caption' => (string) $img->getAttribute('title'),
        ]);
    }

    protected static function figureWidget(DOMDocument $doc, DOMElement $figure): array
    {
        $src = '';
        $alt = '';
        foreach ($figure->getElementsByTagName('img') as $img) {
            $src = (string) $img->getAttribute('src');
            $alt = (string) $img->getAttribute('alt');
            break;
        }
        $caption = '';
        foreach ($figure->getElementsByTagName('figcaption') as $fc) {
            $caption = trim($fc->textContent);
            break;
        }

        return self::widget('image', [
            'src'     => $src,
            'alt'     => $alt,
            'caption' => $caption,
        ]);
    }

    protected static function innerHtml(DOMDocument $doc, DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    protected static function widget(string $type, array $data): array
    {
        return [
            'id'       => self::randomId(),
            'type'     => $type,
            'data'     => $data,
            'design'   => [],
            'advanced' => [],
        ];
    }

    protected static function randomId(): string
    {
        return 'n' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
