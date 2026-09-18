<?php

declare(strict_types=1);

namespace NetFree\Builder;

use NetFree\Application;
use NetFree\Content\PageRepository;
use NetFree\Content\PostRepository;
use NetFree\Content\RevisionRepository;
use NetFree\Database;
use NetFree\Response;

/**
 * Страницы конструктора: оболочка редактора и iframe-холст.
 */
class BuilderController
{
    public function shell(Application $app, string $type, int $id): Response
    {
        if (!in_array($type, ['page', 'post'], true)) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }
        $entity = $this->entity($type, $id);
        if (!$entity) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }

        // Текущий документ: content_blocks, либо автоконверсия из классического content.
        $raw = (string) ($entity['content_blocks'] ?? '');
        if ($raw === '' || $raw === 'null') {
            // Перед первой конвертацией пишем снимок классического контента (обратимость).
            if (($entity['editor_mode'] ?? 'classic') !== 'builder'
                && (string) ($entity['content'] ?? '') !== ''
                && !RevisionRepository::byEntity($type, $id, 1)
            ) {
                $user = current_user();
                RevisionRepository::create([
                    'entity_type'    => $type,
                    'entity_id'      => $id,
                    'user_id'        => isset($user['id']) ? (int) $user['id'] : null,
                    'title'          => (string) ($entity['title'] ?? ''),
                    'content'        => (string) ($entity['content'] ?? ''),
                    'content_blocks' => null,
                    'content_css'    => null,
                    'is_autosave'    => 0,
                ]);
            }
            $doc = HtmlConverter::convert((string) ($entity['content'] ?? ''));
        } else {
            $doc = Document::parse($raw);
        }

        // Экранирование происходит во вью через JSON.parse — сюда передаём сырые данные.
        $autosave = RevisionRepository::latestAutosave($type, $id);
        $autosaveInfo = $autosave
            ? ['id' => (int) $autosave['id'], 'created_at' => $autosave['created_at']]
            : null;

        $data = [
            'type'      => $type,
            'id'        => $id,
            'title'     => (string) ($entity['title'] ?? ''),
            'document'  => $doc,
            'canvasUrl' => '/admin/builder/canvas/' . $type . '/' . $id,
            'backUrl'   => $type === 'page' ? '/admin/pages' : '/admin/posts',
            'autosave'  => $autosaveInfo,
        ];

        $view = __DIR__ . '/../../private-admin/views/builder.php';
        extract($data, EXTR_SKIP);
        ob_start();
        include $view;
        return (new Response())->setBody((string) ob_get_clean());
    }

    /**
     * Холст: реальный шаблон темы в режиме правки (rendered content + CSS в <head>).
     */
    public function canvas(Application $app, string $type, int $id): Response
    {
        if (!in_array($type, ['page', 'post'], true)) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }
        $entity = $this->entity($type, $id);
        if (!$entity) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }

        $html = $type === 'page'
            ? $app->theme->render('page', ['page' => $entity])
            : $app->theme->render('blog_post', ['post' => $entity]);

        return (new Response())->setBody($html);
    }

    /**
     * Публичный предпросмотр черновика по одноразовому токену с TTL.
     * Рендерит конкретную ревизию (или последний автосейв) без авторизации.
     */
    public function preview(Application $app, string $type, int $id): Response
    {
        if (!in_array($type, ['page', 'post'], true)) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }

        $token = (string) ($app->request->query['token'] ?? '');
        $row = $token !== ''
            ? Database::first('SELECT * FROM preview_tokens WHERE token = ?', [$token])
            : null;

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }
        if ((string) $row['entity_type'] !== $type || (int) $row['entity_id'] !== $id) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }

        // Токен одноразовый: удаляем сразу после валидации, чтобы повторный
        // запрос (в т.ч. с переданным через Referer/логи) был бесполезен.
        Database::execute('DELETE FROM preview_tokens WHERE token = ?', [$token]);

        // Исходная запись без хуков (чтобы Builder::prepareEntity не перерисовывал).
        $entity = $type === 'page'
            ? Database::first('SELECT * FROM pages WHERE id = ?', [$id])
            : Database::first('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!$entity) {
            return (new Response())->setStatus(404)->setBody('Not found');
        }

        $revision = null;
        if (!empty($row['revision_id'])) {
            $revision = RevisionRepository::byId((int) $row['revision_id']);
        } else {
            $revision = RevisionRepository::latestAutosave($type, $id);
        }

        if ($revision) {
            $entity['title']          = $revision['title'] !== '' ? (string) $revision['title'] : (string) ($entity['title'] ?? '');
            $entity['content']        = (string) $revision['content'];
            $entity['content_blocks'] = $revision['content_blocks'];
            $entity['content_css']    = (string) ($revision['content_css'] ?? '');
            Builder::enqueueRawCss((string) ($revision['content_css'] ?? ''));
        } else {
            Builder::enqueueRawCss((string) ($entity['content_css'] ?? ''));
        }

        $html = $type === 'page'
            ? $app->theme->render('page', ['page' => $entity])
            : $app->theme->render('blog_post', ['post' => $entity]);

        return (new Response())->setBody($html);
    }

    protected function entity(string $type, int $id): ?array
    {
        return $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
    }
}
