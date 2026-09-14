<?php $adminTitle = 'Обзор — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <h1>Обзор</h1>
    <p>Добро пожаловать в админ-панель NetFree.</p>
    <p>
        <a class="btn" href="<?= e(url('admin/pages')) ?>">Страницы</a>
        <a class="btn" href="<?= e(url('admin/posts')) ?>">Записи</a>
        <a class="btn" href="<?= e(url('admin/categories')) ?>">Категории</a>
        <a class="btn" href="<?= e(url('admin/media')) ?>">Медиа</a>
    </p>
    <table style="max-width:420px;">
        <tr><td>Страницы</td><td><strong><?= (int) ($pageCount ?? 0) ?></strong></td></tr>
        <tr><td>Записи</td><td><strong><?= (int) ($postCount ?? 0) ?></strong></td></tr>
        <tr><td>Категории</td><td><strong><?= (int) ($catCount ?? 0) ?></strong></td></tr>
        <tr><td>Медиафайлы</td><td><strong><?= (int) ($mediaCount ?? 0) ?></strong></td></tr>
    </table>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
