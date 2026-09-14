<?php
$adminTitle = ($page ? 'Редактирование' : 'Новая страница') . ' — NetFree';
$title    = $page['title'] ?? '';
$slug     = $page['slug'] ?? '';
$content  = $page['content'] ?? '';
$meta     = $page['meta_desc'] ?? '';
$published = isset($page) ? (bool) $page['is_published'] : true;
?>
<?php include __DIR__ . '/layout_header.php'; ?>

<div class="card">
    <h1><?= $page ? 'Редактирование страницы' : 'Новая страница' ?></h1>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/pages/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($page): ?>
            <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
        <?php endif; ?>

        <label>Заголовок *</label>
        <input type="text" name="title" value="<?= e($title) ?>" required>

        <label>Slug (URL)</label>
        <input type="text" name="slug" value="<?= e($slug) ?>" placeholder="генерируется из заголовка">

        <label>Meta description</label>
        <input type="text" name="meta_desc" value="<?= e($meta) ?>">

        <label>Контент</label>
        <textarea name="content" id="nfContent"><?= e($content) ?></textarea>

        <label>
            <input type="checkbox" name="is_published" value="1" <?= $published ? 'checked' : '' ?>> Опубликована
        </label>

        <div style="margin-top:16px;" class="row">
            <button type="submit" class="btn">Сохранить</button>
            <a class="btn secondary" href="<?= e(url('admin/pages')) ?>">Отмена</a>
        </div>
    </form>
</div>

<script>nfInitEditor('nfContent');</script>

<?php include __DIR__ . '/layout_footer.php'; ?>
