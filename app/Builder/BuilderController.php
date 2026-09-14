<?php

declare(strict_types=1);

namespace NetFree\Builder;

use NetFree\Application;
use NetFree\Content\PageRepository;
use NetFree\Content\PostRepository;
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
            $doc = HtmlConverter::convert((string) ($entity['content'] ?? ''));
        } else {
            $doc = Document::parse($raw);
        }

        // Экранируем "</" как "<\/", чтобы JSON нельзя было разорвать через "</script>".
        $documentJson = str_replace('</', '<\\/', json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $data = [
            'type'      => $type,
            'id'        => $id,
            'title'     => (string) ($entity['title'] ?? ''),
            'document'  => $documentJson,
            'canvasUrl' => '/admin/builder/canvas/' . $type . '/' . $id,
            'backUrl'   => $type === 'page' ? '/admin/pages' : '/admin/posts',
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

    protected function entity(string $type, int $id): ?array
    {
        return $type === 'page' ? PageRepository::byId($id) : PostRepository::byId($id);
    }
}
