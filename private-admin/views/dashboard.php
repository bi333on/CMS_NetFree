<?php $adminTitle = 'Обзор — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <h1>Обзор</h1>
    <p>Добро пожаловать в админ-панель NetFree.</p>
    <p><a class="btn" href="<?= e(url('admin/pages')) ?>">Управление страницами</a></p>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
