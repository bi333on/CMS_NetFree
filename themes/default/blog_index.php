<?php
/** @var array $posts */
/** @var array $categories */
/** @var array|null $category */
$site_title = $category['name'] ?? 'Блог';
?>
<?php get_header(['site_title' => $site_title]); ?>

<section class="blog">
    <h1><?= e($category['name'] ?? 'Блог') ?></h1>

    <?php if (empty($posts)): ?>
        <p>Записей пока нет.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article class="blog-item" style="border-bottom:1px solid #e5e7eb;padding:16px 0;">
                <h2><a href="<?= e(url('blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                <?php if (!empty($post['category_name'])): ?>
                    <div style="color:#6b7280;font-size:0.9rem;">
                        Категория: <a href="<?= e(url('category/' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($post['excerpt'])): ?>
                    <p><?= e($post['excerpt']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php get_footer(); ?>
