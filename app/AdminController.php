<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Content\PageRepository;

/**
 * Админ-панель: вход, список страниц, создание/редактирование.
 */
class AdminController
{
    public function login(Application $app): Response
    {
        $resp = new Response();
        $error = '';

        if ($app->request->method === 'POST') {
            if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
                return $resp->setStatus(403)->setBody('CSRF token mismatch');
            }
            $username = (string) $app->request->input('username', '');
            $password = (string) $app->request->input('password', '');
            if ($app->auth->attempt($username, $password)) {
                return $resp->redirect('/admin');
            }
            $error = 'Неверный логин или пароль.';
        }

        $html = $this->render('login', ['error' => $error, 'site' => $app->config->get('site.name')]);
        return $resp->setBody($html);
    }

    public function logout(Application $app): Response
    {
        $app->auth->logout();
        return (new Response())->redirect('/admin/login');
    }

    public function dashboard(Application $app): Response
    {
        return (new Response())->setBody($this->render('dashboard'));
    }

    public function pages(Application $app): Response
    {
        return (new Response())->setBody($this->render('pages', ['pages' => PageRepository::all()]));
    }

    public function pageNew(Application $app): Response
    {
        return (new Response())->setBody($this->render('page_form', ['page' => null, 'error' => '']));
    }

    public function pageEdit(Application $app, int $id): Response
    {
        $page = PageRepository::byId($id);
        if (!$page) {
            return (new Response())->setStatus(404)->setBody('Page not found');
        }
        return (new Response())->setBody($this->render('page_form', ['page' => $page, 'error' => '']));
    }

    public function pageSave(Application $app): Response
    {
        $resp = new Response();
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return $resp->setStatus(403)->setBody('CSRF token mismatch');
        }

        $id    = (int) $app->request->input('id', '0');
        $title = trim((string) $app->request->input('title', ''));
        $slug  = slugify((string) $app->request->input('slug', $title));
        $content = (string) $app->request->input('content', '');
        $meta  = trim((string) $app->request->input('meta_desc', ''));
        $published = (int) (bool) $app->request->input('is_published', '1');

        if ($title === '' || $slug === '') {
            $page = $id ? PageRepository::byId($id) : null;
            return $resp->setBody($this->render('page_form', [
                'page'  => $page,
                'error' => 'Заголовок обязателен.',
            ]));
        }

        if (PageRepository::slugExists($slug, $id ?: null)) {
            $page = $id ? PageRepository::byId($id) : null;
            return $resp->setBody($this->render('page_form', [
                'page'  => $page,
                'error' => 'Такой URL (slug) уже занят.',
            ]));
        }

        $data = [
            'title'        => $title,
            'slug'         => $slug,
            'content'      => $content,
            'meta_desc'    => $meta,
            'is_published' => $published,
        ];

        if ($id) {
            PageRepository::update($id, $data);
        } else {
            $id = PageRepository::create($data);
        }

        flash_set('success', 'Страница сохранена.');
        return $resp->redirect('/admin/pages');
    }

    public function pageDelete(Application $app, int $id): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        PageRepository::delete($id);
        flash_set('success', 'Страница удалена.');
        return (new Response())->redirect('/admin/pages');
    }

    protected function render(string $view, array $data = []): string
    {
        $adminViews = __DIR__ . '/../private-admin/views';
        extract($data, EXTR_SKIP);
        ob_start();
        include $adminViews . '/' . $view . '.php';
        return (string) ob_get_clean();
    }
}
