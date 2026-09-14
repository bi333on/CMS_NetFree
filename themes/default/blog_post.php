<?php
/** @var array $post */
$site_title = $post['title'] ?? 'Запись';
?>
<?php get_header(['site_title' => $site_title]); ?>

<article class="blog-post">
    <h1><?= e($post['title'] ?? '') ?></h1>
    <?php if (!empty($post['category_name'])): ?>
        <div style="color:#6b7280;margin-bottom:12px;">
            Категория: <a href="<?= e(url('category/' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
        </div>
    <?php endif; ?>
    <div class="page-content">
        <?= apply_filters('netfree.post_content', $post['content'] ?? '', $post) ?>
    </div>
    <p><a href="<?= e(url('blog')) ?>">&larr; Назад к блогу</a></p>
</article>

<?php get_footer(); ?>
