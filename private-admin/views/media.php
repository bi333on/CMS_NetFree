<?php $adminTitle = 'Медиа — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <h1>Медиабиблиотека</h1>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data" style="margin-bottom:20px;">
        <?= csrf_field() ?>
        <label>Загрузить файл (jpg, png, gif, webp, pdf; до 10 МБ)</label>
        <input type="file" name="file" required>
        <div style="margin-top:10px;">
            <button type="submit" class="btn">Загрузить</button>
        </div>
    </form>

    <table>
        <thead><tr><th>ID</th><th>Файл</th><th>Оригинал</th><th>Тип</th><th>Размер</th><th>Ссылка</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($media as $m): ?>
            <tr>
                <td><?= (int) $m['id'] ?></td>
                <td>
                    <?php if (str_starts_with((string) $m['mime'], 'image/')): ?>
                        <img src="<?= e('/uploads/' . $m['filename']) ?>" style="max-width:60px;max-height:40px;" alt="">
                    <?php else: ?>
                        <?= e($m['filename']) ?>
                    <?php endif; ?>
                </td>
                <td><?= e($m['original_name']) ?></td>
                <td><?= e($m['mime']) ?></td>
                <td><?= number_format((int) $m['size'] / 1024, 1) ?> КБ</td>
                <td><code><?= e('/uploads/' . $m['filename']) ?></code></td>
                <td>
                    <form method="post" action="<?= e(url('admin/media/delete/' . $m['id'])) ?>" onsubmit="return confirm('Удалить файл?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
