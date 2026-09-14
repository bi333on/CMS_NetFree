<?php $adminTitle = 'Записи — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Записи</h1>
        <a class="btn" href="<?= e(url('admin/posts/new')) ?>">Новая запись</a>
    </div>
    <table>
        <thead>
            <tr><th>ID</th><th>Заголовок</th><th>Категория</th><th>Статус</th><th>Дата</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><?= (int) $p['id'] ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['category_name'] ?? '—') ?></td>
                <td><?= $p['status'] === 'published' ? 'Опубликована' : 'Черновик' ?></td>
                <td><?= e($p['created_at']) ?></td>
                <td class="row">
                    <a class="btn secondary" href="<?= e(url('admin/posts/edit/' . $p['id'])) ?>">Изменить</a>
                    <form method="post" action="<?= e(url('admin/posts/delete/' . $p['id'])) ?>" onsubmit="return confirm('Удалить запись?')">
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
