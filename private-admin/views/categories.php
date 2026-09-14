<?php $adminTitle = 'Категории — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php foreach (flash_get() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <h1>Категории</h1>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <table>
        <thead><tr><th>ID</th><th>Название</th><th>Slug</th><th>Описание</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= (int) $c['id'] ?></td>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['slug']) ?></td>
                <td><?= e($c['description']) ?></td>
                <td>
                    <form method="post" action="<?= e(url('admin/categories/delete/' . $c['id'])) ?>" onsubmit="return confirm('Удалить категорию?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:20px;">
    <h2>Добавить категорию</h2>
    <form method="post" action="<?= e(url('admin/categories/save')) ?>">
        <?= csrf_field() ?>
        <label>Название *</label>
        <input type="text" name="name" required>
        <label>Slug (URL)</label>
        <input type="text" name="slug" placeholder="генерируется из названия">
        <label>Описание</label>
        <input type="text" name="description">
        <div style="margin-top:14px;">
            <button type="submit" class="btn">Добавить</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
