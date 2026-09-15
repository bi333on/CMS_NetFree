<?php

declare(strict_types=1);

namespace NetFree\Builder;

use NetFree\Application;
use NetFree\Content\PageRepository;
use NetFree\Content\PostRepository;
use NetFree\Content\RevisionRepository;
use NetFree\Csrf;
use NetFree\Database;
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

        $existing = $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
        if (!$existing) {
            return (new Response())->json(['error' => 'Not found'], 404);
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
        if ($title === '') {
            $title = (string) ($existing['title'] ?? '');
        } else {
            $update['title'] = $title;
        }

        if ($type === 'page') {
            PageRepository::update($id, $update);
        } else {
            PostRepository::update($id, $update);
        }

        RevisionRepository::create($this->revisionData($type, $id, $title, $content, $blocks, $css, false));

        return (new Response())->json(['ok' => true]);
    }

    /**
     * Автосохранение: пишет ревизию is_autosave=1 (храним только последнюю).
     */
    public function autosave(Application $app): Response
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

        $existing = $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
        if (!$existing) {
            return (new Response())->json(['error' => 'Not found'], 404);
        }

        $raw = is_array($data['document'] ?? null) ? $data['document'] : [];
        $doc = Document::normalize($raw);

        $content = Renderer::render($doc);
        $css     = CssGenerator::generate($doc);
        $blocks  = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($existing['title'] ?? '');
        }

        RevisionRepository::deleteAutosaves($type, $id);
        $revisionId = RevisionRepository::create($this->revisionData($type, $id, $title, $content, $blocks, $css, true));

        return (new Response())->json(['ok' => true, 'revision_id' => $revisionId]);
    }

    /**
     * Список ревизий (без тяжёлых тел).
     */
    public function revisions(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        $type = (string) ($app->request->query['type'] ?? '');
        $id   = (int) ($app->request->query['id'] ?? 0);
        if (!in_array($type, ['page', 'post'], true) || $id <= 0) {
            return (new Response())->json(['error' => 'invalid type or id'], 422);
        }

        $list = array_map(function (array $r) {
            return [
                'id'          => (int) $r['id'],
                'is_autosave' => (bool) (int) $r['is_autosave'],
                'created_at'  => $r['created_at'],
                'title'       => $r['title'],
                'has_blocks'  => !empty($r['content_blocks']),
            ];
        }, RevisionRepository::byEntity($type, $id, 50));

        return (new Response())->json(['revisions' => $list]);
    }

    /**
     * Восстановление ревизии. Перед перезаписью делает снимок текущего состояния.
     */
    public function restore(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        $data = $app->request->json();
        if (!$this->csrfOk($app, $data)) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        $type   = (string) ($data['type'] ?? '');
        $id     = (int) ($data['id'] ?? 0);
        $revId  = (int) ($data['revision_id'] ?? 0);
        if (!in_array($type, ['page', 'post'], true) || $id <= 0 || $revId <= 0) {
            return (new Response())->json(['error' => 'invalid type, id or revision_id'], 422);
        }

        $rev = RevisionRepository::byId($revId);
        if (!$rev || (string) $rev['entity_type'] !== $type || (int) $rev['entity_id'] !== $id) {
            return (new Response())->json(['error' => 'Revision not found'], 404);
        }

        $existing = $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
        if (!$existing) {
            return (new Response())->json(['error' => 'Not found'], 404);
        }

        // Снимок текущего состояния — откат обратим.
        RevisionRepository::create($this->revisionData(
            $type,
            $id,
            (string) ($existing['title'] ?? ''),
            (string) ($existing['content'] ?? ''),
            (string) ($existing['content_blocks'] ?? ''),
            (string) ($existing['content_css'] ?? ''),
            false
        ));

        if (!empty($rev['content_blocks'])) {
            $update = [
                'content'        => (string) $rev['content'],
                'content_blocks' => (string) $rev['content_blocks'],
                'content_css'    => (string) ($rev['content_css'] ?? ''),
                'editor_mode'    => 'builder',
            ];
        } else {
            $update = [
                'content'        => (string) $rev['content'],
                'content_blocks' => null,
                'content_css'    => '',
                'editor_mode'    => 'classic',
            ];
        }
        if (!empty($rev['title'])) {
            $update['title'] = (string) $rev['title'];
        }

        if ($type === 'page') {
            PageRepository::update($id, $update);
        } else {
            PostRepository::update($id, $update);
        }

        return (new Response())->json(['ok' => true]);
    }

    /**
     * Одноразовый токен предпросмотра (TTL 1 час).
     */
    public function previewToken(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }
        $data = $app->request->json();
        if (!$this->csrfOk($app, $data)) {
            return (new Response())->json(['error' => 'CSRF token mismatch'], 403);
        }

        $type  = (string) ($data['type'] ?? '');
        $id    = (int) ($data['id'] ?? 0);
        $revId = (int) ($data['revision_id'] ?? 0);
        if (!in_array($type, ['page', 'post'], true) || $id <= 0) {
            return (new Response())->json(['error' => 'invalid type or id'], 422);
        }

        $exists = $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
        if (!$exists) {
            return (new Response())->json(['error' => 'Not found'], 404);
        }

        $token = bin2hex(random_bytes(16));
        Database::execute(
            'INSERT INTO preview_tokens (token, entity_type, entity_id, revision_id, expires_at) VALUES (?, ?, ?, ?, ?)',
            [$token, $type, $id, $revId > 0 ? $revId : null, date('Y-m-d H:i:s', time() + 3600)]
        );

        return (new Response())->json(['url' => '/preview/' . $type . '/' . $id . '?token=' . $token]);
    }

    protected function revisionData(string $type, int $id, string $title, string $content, string $blocks, string $css, bool $autosave): array
    {
        $user = current_user();
        return [
            'entity_type'    => $type,
            'entity_id'      => $id,
            'user_id'        => isset($user['id']) ? (int) $user['id'] : null,
            'title'          => $title,
            'content'        => $content,
            'content_blocks' => $blocks !== '' ? $blocks : null,
            'content_css'    => $css !== '' ? $css : null,
            'is_autosave'    => $autosave ? 1 : 0,
        ];
    }

    /**
     * Каталог готовых шаблонов секций.
     */
    public function templates(Application $app): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        return (new Response())->json([
            'templates' => json_decode(TemplateLibrary::catalogJson(), true),
        ]);
    }

    /**
     * Получить полный документ шаблона по ключу.
     */
    public function template(Application $app, string $key): Response
    {
        if (!is_logged_in()) {
            return (new Response())->json(['error' => 'Unauthorized'], 401);
        }

        $template = TemplateLibrary::get($key);
        if (!$template) {
            return (new Response())->json(['error' => 'Template not found'], 404);
        }

        return (new Response())->json([
            'template' => $template,
        ]);
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
