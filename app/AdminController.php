<?php

declare(strict_types=1);

namespace NetFree;

use NetFree\Api\RateLimiter;
use NetFree\Content\CategoryRepository;
use NetFree\Content\MediaRepository;
use NetFree\Content\PageRepository;
use NetFree\Content\PostRepository;

/**
 * Админ-панель: вход, страницы, записи, категории, медиа, настройки, обновление.
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

            // Rate-limit попыток входа в БД (по IP + логин). Сессия не используется —
            // иначе атакующий обходил бы блокировку, не отправляя cookie.
            $username = (string) $app->request->input('username', '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $bucket = 'login:' . $ip . ':' . md5($username);
            $limit = 5;
            $window = 300; // окно 5 минут

            if (RateLimiter::tooMany($bucket, $limit, $window)) {
                $secs = RateLimiter::blockedSeconds($bucket, $window);
                $error = 'Слишком много попыток. Подождите ' . $secs . ' сек.';
                return $resp->setBody($this->render('login', ['error' => $error, 'site' => $app->config->get('site.name')]));
            }

            $password = (string) $app->request->input('password', '');
            if ($app->auth->attempt($username, $password)) {
                RateLimiter::clear($bucket);
                Session::regenerate();
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
        return (new Response())->setBody($this->render('dashboard', [
            'pageCount' => count(PageRepository::all()),
            'postCount' => PostRepository::count(),
            'catCount'  => count(CategoryRepository::all()),
            'mediaCount'=> count(MediaRepository::all()),
        ]));
    }

    // ------------------------------------------------------------------
    // Страницы
    // ------------------------------------------------------------------
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
        $slugInput = trim((string) $app->request->input('slug', ''));
        $slug  = slugify($slugInput !== '' ? $slugInput : $title);
        $content = (string) $app->request->input('content', '');
        $meta  = trim((string) $app->request->input('meta_desc', ''));
        $featured = trim((string) $app->request->input('featured_image', ''));
        $published = $app->request->has('is_published') ? 1 : 0;

        if ($title === '' || $slug === '') {
            return $resp->setBody($this->render('page_form', [
                'page'  => $this->submittedPage($id, $title, $slug, $content, $meta, $featured, $published),
                'error' => 'Заголовок обязателен.',
            ]));
        }

        if (PageRepository::slugExists($slug, $id ?: null)) {
            return $resp->setBody($this->render('page_form', [
                'page'  => $this->submittedPage($id, $title, $slug, $content, $meta, $featured, $published),
                'error' => 'Такой URL (slug) уже занят.',
            ]));
        }

        $data = [
            'title'          => $title,
            'slug'           => $slug,
            'content'        => $content,
            'meta_desc'      => $meta,
            'featured_image' => $featured,
            'is_published'   => $published,
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

    // ------------------------------------------------------------------
    // Записи (посты)
    // ------------------------------------------------------------------
    public function posts(Application $app): Response
    {
        return (new Response())->setBody($this->render('posts', [
            'posts'      => PostRepository::all(),
            'categories' => CategoryRepository::all(),
        ]));
    }

    public function postNew(Application $app): Response
    {
        return (new Response())->setBody($this->render('post_form', [
            'post'       => null,
            'categories' => CategoryRepository::all(),
            'error'      => '',
        ]));
    }

    public function postEdit(Application $app, int $id): Response
    {
        $post = PostRepository::byId($id);
        if (!$post) {
            return (new Response())->setStatus(404)->setBody('Post not found');
        }
        return (new Response())->setBody($this->render('post_form', [
            'post'       => $post,
            'categories' => CategoryRepository::all(),
            'error'      => '',
        ]));
    }

    public function postSave(Application $app): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }

        $id    = (int) $app->request->input('id', '0');
        $title = trim((string) $app->request->input('title', ''));
        $slugInput = trim((string) $app->request->input('slug', ''));
        $slug  = slugify($slugInput !== '' ? $slugInput : $title);
        $content = (string) $app->request->input('content', '');
        $excerpt = trim((string) $app->request->input('excerpt', ''));
        $categoryId = (int) $app->request->input('category_id', '0');
        $status = in_array((string) $app->request->input('status', 'published'), ['published', 'draft'], true)
            ? (string) $app->request->input('status', 'published') : 'published';
        $featured = trim((string) $app->request->input('featured_image', ''));

        if ($title === '' || $slug === '') {
            return (new Response())->setBody($this->render('post_form', [
                'post'       => $this->submittedPost($id, $title, $slug, $content, $excerpt, $categoryId, $status, $featured),
                'categories' => CategoryRepository::all(),
                'error'      => 'Заголовок обязателен.',
            ]));
        }
        if (PostRepository::slugExists($slug, $id ?: null)) {
            return (new Response())->setBody($this->render('post_form', [
                'post'       => $this->submittedPost($id, $title, $slug, $content, $excerpt, $categoryId, $status, $featured),
                'categories' => CategoryRepository::all(),
                'error'      => 'Такой URL (slug) уже занят.',
            ]));
        }

        $data = [
            'title'          => $title,
            'slug'           => $slug,
            'content'        => $content,
            'excerpt'        => $excerpt,
            'category_id'    => $categoryId ?: null,
            'status'         => $status,
            'featured_image' => $featured,
        ];

        if ($id) {
            PostRepository::update($id, $data);
        } else {
            $id = PostRepository::create($data);
        }

        flash_set('success', 'Запись сохранена.');
        return (new Response())->redirect('/admin/posts');
    }

    public function postDelete(Application $app, int $id): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        PostRepository::delete($id);
        flash_set('success', 'Запись удалена.');
        return (new Response())->redirect('/admin/posts');
    }

    // ------------------------------------------------------------------
    // Категории
    // ------------------------------------------------------------------
    public function categories(Application $app): Response
    {
        return (new Response())->setBody($this->render('categories', [
            'categories' => CategoryRepository::all(),
            'error'      => '',
        ]));
    }

    public function categorySave(Application $app): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }

        $id   = (int) $app->request->input('id', '0');
        $name = trim((string) $app->request->input('name', ''));
        $slugInput = trim((string) $app->request->input('slug', ''));
        $slug = slugify($slugInput !== '' ? $slugInput : $name);
        $desc = trim((string) $app->request->input('description', ''));

        if ($name === '' || $slug === '') {
            return (new Response())->setBody($this->render('categories', [
                'categories' => CategoryRepository::all(),
                'error'      => 'Название категории обязательно.',
            ]));
        }
        if (CategoryRepository::slugExists($slug, $id ?: null)) {
            return (new Response())->setBody($this->render('categories', [
                'categories' => CategoryRepository::all(),
                'error'      => 'Такой slug уже занят.',
            ]));
        }

        $data = ['name' => $name, 'slug' => $slug, 'description' => $desc];
        if ($id) {
            CategoryRepository::update($id, $data);
        } else {
            CategoryRepository::create($data);
        }

        flash_set('success', 'Категория сохранена.');
        return (new Response())->redirect('/admin/categories');
    }

    public function categoryDelete(Application $app, int $id): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        CategoryRepository::delete($id);
        flash_set('success', 'Категория удалена.');
        return (new Response())->redirect('/admin/categories');
    }

    // ------------------------------------------------------------------
    // Медиа
    // ------------------------------------------------------------------
    public function media(Application $app): Response
    {
        return (new Response())->setBody($this->render('media', [
            'media' => MediaRepository::all(),
            'error' => '',
        ]));
    }

    public function mediaUpload(Application $app): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        try {
            $file = $app->request->files['file'] ?? null;
            if (!$file) {
                throw new \RuntimeException('Файл не выбран.');
            }
            MediaUploader::upload($file);
            flash_set('success', 'Файл загружен.');
        } catch (\Throwable $e) {
            flash_set('error', $e->getMessage());
        }
        return (new Response())->redirect('/admin/media');
    }

    public function mediaDelete(Application $app, int $id): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        $item = MediaRepository::byId($id);
        if ($item) {
            $file = Application::getInstance()->basePath . '/public/uploads/' . basename((string) $item['filename']);
            if (is_file($file)) {
                @unlink($file);
            }
            MediaRepository::delete($id);
        }
        flash_set('success', 'Файл удалён.');
        return (new Response())->redirect('/admin/media');
    }

    // ------------------------------------------------------------------
    // Настройки
    // ------------------------------------------------------------------
    public function settings(Application $app): Response
    {
        $current = SettingsRepository::all();
        return (new Response())->setBody($this->render('settings', [
            'options' => $current,
            'error'   => '',
        ]));
    }

    public function settingsSave(Application $app): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }

        $fields = ['site_name', 'site_url', 'update_repo'];
        foreach ($fields as $field) {
            SettingsRepository::set($field, trim((string) $app->request->input($field, '')));
        }

        flash_set('success', 'Настройки сохранены.');
        return (new Response())->redirect('/admin/settings');
    }

    // ------------------------------------------------------------------
    // Обновление ядра
    // ------------------------------------------------------------------
    public function update(Application $app): Response
    {
        return (new Response())->setBody($this->render('update', [
            'repo'      => SettingsRepository::get('update_repo', 'https://github.com/bi333on/CMS_NetFree'),
            'lastUpdate'=> SettingsRepository::get('last_update', ''),
            'result'    => '',
            'error'     => '',
        ]));
    }

    public function updateRun(Application $app): Response
    {
        if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
            return (new Response())->setStatus(403)->setBody('CSRF token mismatch');
        }
        $token = trim((string) $app->request->input('github_token', ''));
        try {
            $copied = (new Updater($app))->update($token);
            return (new Response())->setBody($this->render('update', [
                'repo'       => SettingsRepository::get('update_repo', ''),
                'lastUpdate' => SettingsRepository::get('last_update', ''),
                'result'     => 'Обновление выполнено успешно. Обновлено файлов: ' . $copied . '.',
                'error'      => '',
            ]));
        } catch (\Throwable $e) {
            return (new Response())->setBody($this->render('update', [
                'repo'       => SettingsRepository::get('update_repo', ''),
                'lastUpdate' => SettingsRepository::get('last_update', ''),
                'result'     => '',
                'error'      => $e->getMessage(),
            ]));
        }
    }

    /**
     * Собирает массив страницы для перерисовки формы: присланные данные поверх записи из БД,
     * чтобы несохранённые правки не терялись при ошибке валидации.
     */
    protected function submittedPage(int $id, string $title, string $slug, string $content, string $meta, string $featured, int $published): array
    {
        $submitted = [
            'id'             => $id,
            'title'          => $title,
            'slug'           => $slug,
            'content'        => $content,
            'meta_desc'      => $meta,
            'featured_image' => $featured,
            'is_published'   => $published,
        ];
        if ($id) {
            $existing = PageRepository::byId($id);
            if ($existing) {
                $submitted = array_merge($existing, $submitted);
            }
        }
        return $submitted;
    }

    /**
     * То же для записей (постов).
     */
    protected function submittedPost(int $id, string $title, string $slug, string $content, string $excerpt, int $categoryId, string $status, string $featured): array
    {
        $submitted = [
            'id'             => $id,
            'title'          => $title,
            'slug'           => $slug,
            'content'        => $content,
            'excerpt'        => $excerpt,
            'category_id'    => $categoryId ?: null,
            'status'         => $status,
            'featured_image' => $featured,
        ];
        if ($id) {
            $existing = PostRepository::byId($id);
            if ($existing) {
                $submitted = array_merge($existing, $submitted);
            }
        }
        return $submitted;
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
