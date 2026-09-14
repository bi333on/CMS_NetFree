<?php $site_title = '404 — страница не найдена'; ?>
<?php get_header(['site_title' => $site_title]); ?>

<section class="not-found">
    <h1>404</h1>
    <p>Страница не найдена.</p>
    <p><a href="<?= e(url('/')) ?>">На главную</a></p>
</section>

<?php get_footer(); ?>
