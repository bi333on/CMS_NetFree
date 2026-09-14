<?php
/** @var array $post */
$site_title = $post['title'] ?? 'Запись';
?>
<?php get_header(['site_title' => $site_title]); ?>

<article class="blog-post">
    <h1><?= e($post['title'] ?? '') ?></h1>
    <div class="post-meta">
        <time datetime="<?= e($post['created_at']) ?>"><?= e(format_date($post['created_at'] ?? '', 'd M Y')) ?></time>
        <?php if (!empty($post['category_name'])): ?>
            <a class="blog-card__cat" href="<?= e(url('category/' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
        <?php endif; ?>
    </div>
    <?php if (!empty($post['featured_image'])): ?>
        <img class="post-cover" src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>">
    <?php endif; ?>
    <div class="page-content">
        <?= apply_filters('netfree.post_content', $post['content'] ?? '', $post) ?>
    </div>
    <a class="back-link" href="<?= e(url('blog')) ?>">&larr; Назад к блогу</a>
</article>

<?php get_footer(); ?>
