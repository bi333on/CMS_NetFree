<?php $adminTitle = 'Настройки — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <h1>Настройки</h1>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/settings/save')) ?>">
        <?= csrf_field() ?>

        <label>Название сайта</label>
        <input type="text" name="site_name" value="<?= e($options['site_name'] ?? '') ?>">

        <label>URL сайта</label>
        <input type="text" name="site_url" value="<?= e($options['site_url'] ?? '') ?>">

        <label>Репозиторий обновлений</label>
        <input type="text" name="update_repo" value="<?= e($options['update_repo'] ?? 'https://github.com/bi333on/CMS_NetFree') ?>">

        <div style="margin-top:16px;">
            <button type="submit" class="btn">Сохранить</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
