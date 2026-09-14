<?php
$adminTitle = $adminTitle ?? 'NetFree — админ-панель';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?></title>
    <style>
        :root { --nf-accent:#2d6cdf; --nf-bg:#f5f6f8; --nf-fg:#1a1a2e; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--nf-bg); color:var(--nf-fg); }
        .admin-header { background:#111827; color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
        .admin-header .brand { font-weight:700; }
        .admin-header a { color:#fff; text-decoration:none; margin-left:16px; }
        .admin-wrap { display:flex; min-height:calc(100vh - 53px); }
        .admin-nav { width:220px; background:#1f2937; padding:16px 0; }
        .admin-nav a { display:block; color:#e5e7eb; padding:10px 24px; text-decoration:none; }
        .admin-nav a:hover, .admin-nav a.active { background:#2d6cdf; color:#fff; }
        .admin-content { flex:1; padding:24px; }
        .card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .flash { padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .flash.success { background:#e7f6ec; color:#166534; }
        .flash.error { background:#fde8e8; color:#991b1b; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #e5e7eb; }
        input[type=text], input[type=password], input[type=email], textarea {
            width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; margin:6px 0 14px;
        }
        textarea { min-height:200px; font-family:ui-monospace, monospace; }
        .btn { display:inline-block; background:var(--nf-accent); color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; text-decoration:none; }
        .btn.secondary { background:#6b7280; }
        .btn.danger { background:#dc2626; }
        .row { display:flex; gap:8px; }
    </style>
</head>
<body>
<?php if (is_logged_in()): ?>
<header class="admin-header">
    <div>
        <span class="brand">NetFree</span>
        <a href="<?= e(url('/')) ?>" target="_blank">Сайт</a>
    </div>
    <div>
        <span><?= e(current_user()['username'] ?? '') ?></span>
        <a href="<?= e(url('admin/logout')) ?>">Выйти</a>
    </div>
</header>
<div class="admin-wrap">
    <nav class="admin-nav">
        <a href="<?= e(url('admin')) ?>">Обзор</a>
        <a href="<?= e(url('admin/pages')) ?>">Страницы</a>
    </nav>
    <main class="admin-content">
<?php endif; ?>
