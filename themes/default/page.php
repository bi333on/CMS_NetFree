<?php
/** @var array $page */
$site_title = $page['title'] ?? 'NetFree';
?>
<?php get_header(['site_title' => $site_title]); ?>

<article class="page">
    <h1 class="page-title"><?= e($page['title'] ?? '') ?></h1>
    <?php if (!empty($page['meta_desc'])): ?>
        <p class="page-subtitle"><?= e($page['meta_desc']) ?></p>
    <?php endif; ?>
    <?php if (!empty($page['featured_image'])): ?>
        <img class="post-cover" src="<?= e($page['featured_image']) ?>" alt="<?= e($page['title'] ?? '') ?>">
    <?php endif; ?>
    <div class="page-content">
        <?= apply_filters('netfree.page_content', $page['content'] ?? '', $page) ?>
    </div>
</article>

<?php get_footer(); ?>
