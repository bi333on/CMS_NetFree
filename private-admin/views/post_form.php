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

<div class="card">
    <h1><?= $post ? 'Редактирование записи' : 'Новая запись' ?></h1>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/posts/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($post): ?>
            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
        <?php endif; ?>

        <label>Заголовок *</label>
        <input type="text" name="title" value="<?= e($title) ?>" required>

        <label>Slug (URL)</label>
        <input type="text" name="slug" value="<?= e($slug) ?>" placeholder="генерируется из заголовка">

        <label>Категория</label>
        <select name="category_id" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;margin:6px 0 14px;">
            <option value="0">Без категории</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Статус</label>
        <select name="status" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;margin:6px 0 14px;">
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Опубликована</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Черновик</option>
        </select>

        <label>Анонс (excerpt)</label>
        <input type="text" name="excerpt" value="<?= e($excerpt) ?>">

        <label>URL главного изображения</label>
        <input type="text" name="featured_image" value="<?= e($featured) ?>">

        <label>Контент</label>
        <textarea name="content" id="nfContent"><?= e($content) ?></textarea>

        <div style="margin-top:16px;" class="row">
            <button type="submit" class="btn">Сохранить</button>
            <a class="btn secondary" href="<?= e(url('admin/posts')) ?>">Отмена</a>
        </div>
    </form>
</div>

<script>nfInitEditor('nfContent');</script>

<?php include __DIR__ . '/layout_footer.php'; ?>
