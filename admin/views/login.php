<?php $adminTitle = 'Вход — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<div style="max-width:400px;margin:80px auto;">
    <div class="card">
        <h2>Вход в админ-панель</h2>
        <?php if (!empty($error)): ?>
            <div class="flash error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label>Логин</label>
            <input type="text" name="username" required autofocus>
            <label>Пароль</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn">Войти</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
