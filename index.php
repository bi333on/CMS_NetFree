<?php
/**
 * NetFree — корневой мост для общего хостинга.
 *
 * Если веб-сервер отдаёт сайт из корня проекта (а не из public/),
 * этот файл перенаправляет выполнение на фронт-контроллер public/index.php.
 */

declare(strict_types=1);

require __DIR__ . '/public/index.php';
