<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($site_title ?? 'NetFree') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <?php do_action('netfree.head'); ?>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="site-logo" href="<?= e(url('/')) ?>"><?= e(config('site.name', 'NetFree')) ?></a>
        <nav class="site-nav">
            <a href="<?= e(url('/')) ?>">Главная</a>
            <a href="<?= e(url('about')) ?>">О нас</a>
        </nav>
    </div>
</header>
<main class="site-main">
    <div class="container">
