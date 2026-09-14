<?php
/** @var array $posts */
/** @var array $categories */
/** @var array|null $category */
$site_title = $category['name'] ?? 'Блог';
?>
<?php get_header(['site_title' => $site_title]); ?>

<section class="blog">
    <h1 class="blog-title"><?= e($category['name'] ?? 'Блог') ?></h1>

    <?php if (empty($posts)): ?>
        <div class="empty">Записей пока нет.</div>
    <?php else: ?>
        <div class="blog-grid">
            <?php foreach ($posts as $post): ?>
                <article class="blog-card">
                    <?php if (!empty($post['featured_image'])): ?>
                        <a class="blog-card__thumb" href="<?= e(url('blog/' . $post['slug'])) ?>">
                            <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <div class="blog-card__body">
                        <div class="blog-card__meta">
                            <time datetime="<?= e($post['created_at']) ?>"><?= e(format_date($post['created_at'] ?? '', 'd M Y')) ?></time>
                            <?php if (!empty($post['category_name'])): ?>
                                <a class="blog-card__cat" href="<?= e(url('category/' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
                            <?php endif; ?>
                        </div>
                        <h2 class="blog-card__title"><a href="<?= e(url('blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                        <?php if (!empty($post['excerpt'])): ?>
                            <p class="blog-card__excerpt"><?= e($post['excerpt']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php get_footer(); ?>
