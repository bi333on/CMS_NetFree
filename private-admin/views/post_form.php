<?php
$adminTitle = ($post ? 'Редактирование записи' : 'Новая запись') . ' — NetFree';
$title     = $post['title'] ?? '';
$slug      = $post['slug'] ?? '';
$content   = $post['content'] ?? '';
$excerpt   = $post['excerpt'] ?? '';
$categoryId= $post['category_id'] ?? 0;
$status    = $post['status'] ?? 'published';
$featured  = $post['featured_image'] ?? '';
?>
<?php include __DIR__ . '/layout_header.php'; ?>

<h1 style="margin-bottom:18px;"><?= $post ? 'Редактирование записи' : 'Новая запись' ?></h1>

<?php if (!empty($error)): ?>
    <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/posts/save')) ?>">
    <?= csrf_field() ?>
    <?php if ($post): ?>
        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
    <?php endif; ?>

    <div class="nf-editor-layout">
        <!-- Левая колонка: основные поля -->
        <div class="nf-editor-main">
            <div class="card">
                <label>Заголовок *</label>
                <input type="text" name="title" value="<?= e($title) ?>" required>

                <label>Slug (URL)</label>
                <input type="text" name="slug" value="<?= e($slug) ?>" placeholder="генерируется из заголовка">

                <label>Анонс (excerpt)</label>
                <input type="text" name="excerpt" value="<?= e($excerpt) ?>">

                <label>Контент</label>
                <textarea name="content" id="nfContent" class="nf-editor"><?= e($content) ?></textarea>
            </div>
        </div>

        <!-- Правая колонка: сайдбар как в WP -->
        <div class="nf-editor-side">
            <div class="nf-sidebox">
                <h3>Публикация</h3>
                <label>Статус</label>
                <select name="status">
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Опубликована</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Черновик</option>
                </select>
                <button type="submit" class="btn">Сохранить</button>
                <a class="btn secondary" style="margin-top:8px;display:block;text-align:center;" href="<?= e(url('admin/posts')) ?>">Отмена</a>
            </div>

            <div class="nf-sidebox">
                <h3>Категория</h3>
                <select name="category_id">
                    <option value="0">Без категории</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nf-sidebox">
                <h3>Изображение записи</h3>
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
