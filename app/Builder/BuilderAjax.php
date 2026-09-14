<?php

declare(strict_types=1);

namespace NetFree\Builder;

use NetFree\Application;
use NetFree\Content\PageRepository;
use NetFree\Content\PostRepository;
use NetFree\Csrf;
use NetFree\Response;

/**
 * JSON-эндпоинты конструктора.
 */
class BuilderAjax
{
    /**
     * Схема реестра виджетов + декларативная таблица design→CSS для live-редактора.
     */
    public function blocks(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        return (new Response())->json([
            'blocks'      => json_decode(BlockRegistry::getInstance()->schemaJson(), true),
            'propMap'     => CssGenerator::propMap(),
            'px'          => CssGenerator::pxProps(),
            'breakpoints' => CssGenerator::breakpoints(),
        ]);
    }

    /**
     * Изменённая секция → {html, css}. Рендер — только на PHP.
     */
    public function render(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        $data = $app->request->json();
        if (!$this->csrfOk($app, $data)) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        $section = $data['section'] ?? null;
        if (!is_array($section)) {
            return (new Response())->json(['error' => 'section required'], 422);
        }

        $doc = Document::normalize(['version' => 1, 'sections' => [$section]]);
        $section = $doc['sections'][0] ?? null;
        if (!$section) {
            return (new Response())->json(['error' => 'invalid section'], 422);
        }

        return (new Response())->json([
            'html' => Renderer::renderSection($section),
            'css'  => CssGenerator::generate($doc),
        ]);
    }

    /**
     * Сохранение документа: рендерит content, генерирует CSS, пишет в БД.
     */
    public function save(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        $data = $app->request->json();
        if (!$this->csrfOk($app, $data)) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        $type = (string) ($data['type'] ?? '');
        $id   = (int) ($data['id'] ?? 0);
        if (!in_array($type, ['page', 'post'], true) || $id <= 0) {
            return (new Response())->json(['error' => 'invalid type or id'], 422);
        }

        $raw = is_array($data['document'] ?? null) ? $data['document'] : [];
        $doc = Document::normalize($raw);

        $content = Renderer::render($doc);
        $css     = CssGenerator::generate($doc);
        $blocks  = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $update = [
            'content'        => $content,
            'content_blocks' => $blocks,
            'content_css'    => $css,
            'editor_mode'    => 'builder',
        ];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            $update['title'] = $title;
        }

        if ($type === 'page') {
            if (!PageRepository::byId($id)) {
                return (new Response())->json(['error' => 'Not found'], 404);
            }
            PageRepository::update($id, $update);
        } else {
            if (!PostRepository::byId($id)) {
                return (new Response())->json(['error' => 'Not found'], 404);
            }
            PostRepository::update($id, $update);
        }

        return (new Response())->json(['ok' => true]);
    }

    protected function csrfOk(Application $app, array $data): bool
    {
        $token = (string) ($app->request->header('X-CSRF-Token') ?? '');
        if ($token === '') {
            $token = (string) ($data['_csrf'] ?? '');
        }
        return Csrf::verify($token);
    }
}
