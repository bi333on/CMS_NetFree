<?php

declare(strict_types=1);

namespace NetFree;

use Exception;
use Throwable;

/**
 * Главный класс приложения: регистрирует сервисы и обрабатывает запрос.
 */
class Application
{
    protected static ?Application $instance = null;

    public Config $config;
    public Hooks $hooks;
    public Router $router;
    public Request $request;
    public Auth $auth;
    public Theme $theme;
    public PluginManager $plugins;
    public string $basePath;

    protected bool $booted = false;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public static function getInstance(?string $basePath = null): Application
    {
        if (self::$instance === null) {
            self::$instance = new Application($basePath ?? dirname(__DIR__));
            self::$instance->boot();
        }
        return self::$instance;
    }

    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->config = new Config($this->basePath);
        $this->hooks  = new Hooks();
        $this->router = new Router();

        date_default_timezone_set((string) $this->config->get('app.timezone', 'UTC'));

        Session::start();
        $this->request = new Request();
        $this->auth    = new Auth();

        Database::connect($this->config->get('database', []));

        // Достраиваем недостающие таблицы (идемпотентно).
        try {
            Migrations::run();
        } catch (\Throwable $e) {
            // Не роняем сайт, если миграции ещё не прогнаны через установщик.
            if ((bool) $this->config->get('app.debug', false)) {
                trigger_error('Migrations: ' . $e->getMessage(), E_USER_WARNING);
            }
        }

        $this->theme   = new Theme($this->config->get('theme.active', 'default'));
        $this->plugins = new PluginManager($this->basePath);

        $this->registerRoutes();
        $this->booted = true;
    }

    protected function registerRoutes(): void
    {
        $this->router->get('/', function () {
            return $this->renderFrontPage();
        });

        // Ассеты активной темы.
        $this->router->get('/assets/{file}', function (string $file) {
            $path = $this->basePath . '/themes/' . $this->theme->name() . '/assets/' . basename($file);
            if (!is_file($path)) {
                return (new Response())->setStatus(404)->setBody('Not found');
            }
            $mime = str_ends_with($file, '.css') ? 'text/css' : (str_ends_with($file, '.js') ? 'application/javascript' : 'application/octet-stream');
            return (new Response())->setHeader('Content-Type', $mime)->setBody((string) file_get_contents($path));
        });

        // Отдача загруженных медиафайлов из public/uploads/.
        $this->router->get('/uploads/{file}', function (string $file) {
            $file = basename($file);
            $path = $this->basePath . '/public/uploads/' . $file;
            if (!is_file($path)) {
                return (new Response())->setStatus(404)->setBody('Not found');
            }
            return $this->serveFile($path);
        });

        $this->registerAdminRoutes();

        // Публичный блог: список записей и рубрика.
        $this->router->get('/blog', function () {
            return $this->renderBlogIndex();
        });

        $this->router->get('/blog/{slug}', function (string $slug) {
            return $this->renderBlogPost($slug);
        });

        $this->router->get('/category/{slug}', function (string $slug) {
            return $this->renderCategory($slug);
        });

        $this->router->get('/{slug}', function (string $slug) {
            return $this->renderPage($slug);
        });
    }

    protected function registerAdminRoutes(): void
    {
        $controller = new AdminController();

        $this->router->any('/admin/login', function () use ($controller) {
            return $controller->login($this);
        });

        $this->router->any('/admin/logout', function () use ($controller) {
            return $controller->logout($this);
        });

        $this->router->get('/admin', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->dashboard($this);
        });

        $this->router->get('/admin/pages', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->pages($this);
        });

        $this->router->get('/admin/pages/new', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->pageNew($this);
        });

        $this->router->post('/admin/pages/save', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->pageSave($this);
        });

        $this->router->get('/admin/pages/edit/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->pageEdit($this, (int) $id);
        });

        $this->router->post('/admin/pages/delete/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->pageDelete($this, (int) $id);
        });

        // Записи (посты)
        $this->router->get('/admin/posts', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->posts($this);
        });

        $this->router->get('/admin/posts/new', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->postNew($this);
        });

        $this->router->post('/admin/posts/save', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->postSave($this);
        });

        $this->router->get('/admin/posts/edit/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->postEdit($this, (int) $id);
        });

        $this->router->post('/admin/posts/delete/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->postDelete($this, (int) $id);
        });

        // Категории
        $this->router->get('/admin/categories', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->categories($this);
        });

        $this->router->post('/admin/categories/save', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->categorySave($this);
        });

        $this->router->post('/admin/categories/delete/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->categoryDelete($this, (int) $id);
        });

        // Медиа
        $this->router->get('/admin/media', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->media($this);
        });

        $this->router->post('/admin/media/upload', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->mediaUpload($this);
        });

        $this->router->post('/admin/media/delete/{id}', function (string $id) use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->mediaDelete($this, (int) $id);
        });

        // Настройки
        $this->router->get('/admin/settings', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->settings($this);
        });

        $this->router->post('/admin/settings/save', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->settingsSave($this);
        });

        // Обновление ядра
        $this->router->get('/admin/update', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->update($this);
        });

        $this->router->post('/admin/update/run', function () use ($controller) {
            if (!is_logged_in()) {
                return (new Response())->redirect('/admin/login');
            }
            return $controller->updateRun($this);
        });

        // AJAX для медиа-модала редактора.
        $mediaAjax = new MediaAjax();

        $this->router->get('/admin/ajax/media', function () use ($mediaAjax) {
            return $mediaAjax->list($this);
        });

        $this->router->post('/admin/ajax/media/upload', function () use ($mediaAjax) {
            return $mediaAjax->upload($this);
        });
    }

    public function run(): void
    {
        try {
            $this->plugins->load();

            // API-роутер обрабатывается раньше публичных маршрутов.
            if (str_starts_with($this->request->path, '/api/')) {
                (new ApiRouter($this))->run();
                return;
            }

            $response = $this->dispatchWeb();
            $response->send();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function dispatchWeb(): Response
    {
        $route = $this->router->dispatch($this->request->method, $this->request->path);
        $response = new Response();

        if ($route === null) {
            $response->setStatus(404)->setBody($this->theme->render('404'));
            return $response;
        }

        $handler = $route['handler'];
        $params  = $route['params'];

        do_action('netfree.before_route', $this->request->method, $this->request->path);

        $result = call_user_func_array($handler, array_values($params));

        if ($result instanceof Response) {
            return $result;
        }
        return $response->setBody((string) $result);
    }

    protected function renderFrontPage(): string
    {
        $pages = \NetFree\Content\PageRepository::frontPage();
        if (!$pages) {
            return $this->theme->render('404');
        }
        return $this->theme->render('page', ['page' => $pages]);
    }

    protected function renderPage(string $slug): string
    {
        $page = \NetFree\Content\PageRepository::bySlug($slug);
        if (!$page) {
            return $this->theme->render('404');
        }
        return $this->theme->render('page', ['page' => $page]);
    }

    protected function renderBlogIndex(): string
    {
        $posts = \NetFree\Content\PostRepository::published();
        $categories = \NetFree\Content\CategoryRepository::all();
        return $this->theme->render('blog_index', ['posts' => $posts, 'categories' => $categories]);
    }

    protected function renderBlogPost(string $slug): string
    {
        $post = \NetFree\Content\PostRepository::bySlug($slug);
        if (!$post) {
            return $this->theme->render('404');
        }
        return $this->theme->render('blog_post', ['post' => $post]);
    }

    protected function renderCategory(string $slug): string
    {
        $category = \NetFree\Content\CategoryRepository::bySlug($slug);
        if (!$category) {
            return $this->theme->render('404');
        }
        $posts = \NetFree\Content\PostRepository::byCategory($slug);
        return $this->theme->render('blog_index', [
            'posts'      => $posts,
            'categories' => \NetFree\Content\CategoryRepository::all(),
            'category'   => $category,
        ]);
    }

    /**
     * Отдаёт файл с правильным Content-Type.
     */
    protected function serveFile(string $path): Response
    {
        $mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'pdf'  => 'application/pdf',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        return (new Response())
            ->setHeader('Content-Type', $mime)
            ->setBody((string) file_get_contents($path));
    }

    protected function handleException(Throwable $e): void
    {
        $debug = (bool) $this->config->get('app.debug', false);
        if ($debug) {
            http_response_code(500);
            echo '<h1>NetFree error</h1><pre>' . e($e->getMessage())
               . "\n\n" . e($e->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo 'Internal Server Error';
        }
    }
}
