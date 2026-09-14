<?php $adminTitle = 'Обновление — NetFree'; ?>
<?php include __DIR__ . '/layout_header.php'; ?>

<div class="card">
    <h1>Обновление ядра</h1>

    <?php if (!empty($result)): ?>
        <div class="flash success"><?= e($result) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <p>Текущий репозиторий: <code><?= e($repo) ?></code></p>
    <?php if ($lastUpdate !== ''): ?>
        <p>Последнее обновление: <strong><?= e($lastUpdate) ?></strong></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/update/run')) ?>">
        <?= csrf_field() ?>
        <label>GitHub-токен (для приватного репозитория)</label>
        <input type="password" name="github_token" placeholder="ghp_... или github_pat_..." autocomplete="off">
        <p style="color:#6b7280;font-size:13px;">Токен нигде не сохраняется. Нужен только для приватного репозитория.</p>
        <div style="margin-top:10px;">
            <button type="submit" class="btn" onclick="return confirm('Запустить обновление ядра?')">Проверить и обновить</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
