<?php
$needsEditor = true; // модал медиа + TinyMCE в подвале
$adminTitle = ($page ? 'Редактирование' : 'Новая страница') . ' — NetFree';
$title    = $page['title'] ?? '';
$slug     = $page['slug'] ?? '';
$content  = $page['content'] ?? '';
$meta     = $page['meta_desc'] ?? '';
$featured = $page['featured_image'] ?? '';
$published = isset($page) ? (bool) $page['is_published'] : true;
?>
<?php include __DIR__ . '/layout_header.php'; ?>

<h1 style="margin-bottom:18px;"><?= $page ? 'Редактирование страницы' : 'Новая страница' ?></h1>

<?php if (!empty($error)): ?>
    <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/pages/save')) ?>">
    <?= csrf_field() ?>
    <?php if ($page): ?>
        <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
    <?php endif; ?>

    <div class="nf-editor-layout">
        <!-- Левая колонка: основные поля -->
        <div class="nf-editor-main">
            <div class="card">
                <label>Заголовок *</label>
                <input type="text" name="title" value="<?= e($title) ?>" required>

                <label>Slug (URL)</label>
                <input type="text" name="slug" value="<?= e($slug) ?>" placeholder="генерируется из заголовка">

                <label>Контент</label>
                <textarea name="content" id="nfContent" class="nf-editor"><?= e($content) ?></textarea>
            </div>
        </div>

        <!-- Правая колонка: сайдбар -->
        <div class="nf-editor-side">
            <div class="nf-sidebox">
                <h3>Публикация</h3>
                <label>
                    <input type="checkbox" name="is_published" value="1" <?= $published ? 'checked' : '' ?> style="width:auto;margin:0 6px 0 0;"> Опубликована
                </label>
                <button type="submit" class="btn" style="margin-top:10px;">Сохранить</button>
                <a class="btn secondary" style="margin-top:8px;display:block;text-align:center;" href="<?= e(url('admin/pages')) ?>">Отмена</a>
            </div>

            <div class="nf-sidebox">
                <h3>SEO</h3>
                <label>Meta description</label>
                <input type="text" name="meta_desc" value="<?= e($meta) ?>">
            </div>

            <div class="nf-sidebox">
                <h3>Изображение страницы</h3>
                <div class="nf-featured-preview<?= $featured ? '' : ' empty' ?>" id="nfFeaturedPreview">
                    <?= $featured ? '<img src="' . e($featured) . '" alt="">' : 'Изображение не выбрано' ?>
                </div>
                <input type="hidden" name="featured_image" id="nfFeaturedImage" value="<?= e($featured) ?>">
                <div class="row">
                    <button type="button" class="btn" style="flex:1;" onclick="nfPickFeatured()">Выбрать</button>
                    <button type="button" class="btn danger" onclick="nfRemoveFeatured()">Убрать</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/layout_footer.php'; ?>
