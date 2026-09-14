<?php $adminTitle = 'Страницы — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Страницы</h1>
        <a class="btn" href="<?= e(url('admin/pages/new')) ?>">Новая страница</a>
    </div>
    <table>
        <thead>
            <tr><th>ID</th><th>Заголовок</th><th>Slug</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <tr>
                <td><?= (int) $p['id'] ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['slug']) ?></td>
                <td><?= $p['is_published'] ? 'Опубликована' : 'Черновик' ?></td>
                <td class="row">
                    <a class="btn" href="<?= e(url('admin/builder/page/' . $p['id'])) ?>">Конструктор</a>
                    <a class="btn secondary" href="<?= e(url('admin/pages/edit/' . $p['id'])) ?>">Изменить</a>
                    <form method="post" action="<?= e(url('admin/pages/delete/' . $p['id'])) ?>" onsubmit="return confirm('Удалить страницу?')">
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
