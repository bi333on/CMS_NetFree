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
        :root { --nf-accent:#2563eb; --nf-accent-hover:#1d4ed8; --nf-bg:#f1f5f9; --nf-fg:#0f172a; --nf-muted:#64748b; --nf-border:#e2e8f0; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--nf-bg); color:var(--nf-fg); }
        .admin-header { background:#0f172a; color:#fff; padding:0 24px; display:flex; align-items:center; justify-content:space-between; height:56px; }
        .admin-header .brand { font-weight:700; font-size:1.05rem; }
        .admin-header a { color:#cbd5e1; text-decoration:none; margin-left:18px; font-size:.9rem; }
        .admin-header a:hover { color:#fff; }
        .admin-wrap { display:flex; min-height:calc(100vh - 56px); }
        .admin-nav { width:220px; background:#fff; padding:16px 12px; border-right:1px solid var(--nf-border); }
        .admin-nav a { display:block; color:#334155; padding:9px 14px; text-decoration:none; border-radius:8px; font-size:.92rem; margin-bottom:2px; }
        .admin-nav a:hover { background:#f1f5f9; }
        .admin-nav a.active { background:#eff6ff; color:var(--nf-accent); font-weight:600; }
        .admin-content { flex:1; padding:28px; }
        .card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(15,23,42,.06); margin-bottom:20px; }
        .flash { padding:11px 15px; border-radius:8px; margin-bottom:14px; font-size:.9rem; }
        .flash.success { background:#e7f6ec; color:#166534; }
        .flash.error { background:#fde8e8; color:#991b1b; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:11px 14px; border-bottom:1px solid var(--nf-border); font-size:.92rem; }
        th { color:var(--nf-muted); font-weight:600; font-size:.82rem; text-transform:uppercase; letter-spacing:.03em; }
        input[type=text], input[type=password], input[type=email], input[type=file], select, textarea {
            width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; margin:6px 0 16px; background:#fff;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--nf-accent); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        textarea { min-height:200px; font-family:ui-monospace, monospace; }
        label { display:block; font-weight:600; font-size:.9rem; color:#334155; }
        .btn { display:inline-block; background:var(--nf-accent); color:#fff; border:none; padding:9px 18px; border-radius:8px; cursor:pointer; text-decoration:none; font-size:.9rem; font-weight:600; }
        .btn:hover { background:var(--nf-accent-hover); }
        .btn.secondary { background:#64748b; }
        .btn.secondary:hover { background:#475569; }
        .btn.danger { background:#dc2626; }
        .btn.danger:hover { background:#b91c1c; }
        .row { display:flex; gap:8px; align-items:center; }
        h1 { margin-top:0; font-size:1.5rem; letter-spacing:-.02em; }
        /* Двухколоночный макет формы (как в WP) */
        .nf-editor-layout { display:flex; gap:24px; align-items:flex-start; }
        .nf-editor-main { flex:1; min-width:0; }
        .nf-editor-side { width:280px; flex-shrink:0; }
        .nf-sidebox { background:#fff; border:1px solid var(--nf-border); border-radius:10px; padding:16px; margin-bottom:16px; }
        .nf-sidebox h3 { margin:0 0 12px; font-size:.85rem; text-transform:uppercase; letter-spacing:.04em; color:var(--nf-muted); }
        .nf-sidebox select, .nf-sidebox input[type=text] { margin-bottom:0; }
        .nf-sidebox .btn { width:100%; text-align:center; margin-top:4px; }
        .nf-featured-preview { width:100%; border-radius:8px; border:1px dashed #cbd5e1; background:#f8fafc; min-height:120px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:10px; }
        .nf-featured-preview img { max-width:100%; max-height:160px; display:block; }
        .nf-featured-preview.empty { color:#94a3b8; font-size:.85rem; }
        @media (max-width: 860px) { .nf-editor-layout { flex-direction:column; } .nf-editor-side { width:100%; } }
        /* Модал */
        .nf-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:2147483000; }
        .nf-modal { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:12px; width:720px; max-width:95%; max-height:85%; overflow:auto; padding:24px; box-shadow:0 24px 64px rgba(15,23,42,.35); }
        .nf-modal-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:14px; }
        .nf-modal-grid .item { border:1px solid var(--nf-border); border-radius:8px; padding:6px; text-align:center; cursor:pointer; }
        .nf-modal-grid .item:hover { border-color:var(--nf-accent); background:#f8fafc; }
        .nf-modal-grid img { max-width:100%; height:70px; object-fit:cover; display:block; margin:0 auto; border-radius:4px; }
        .nf-modal-close { float:right; cursor:pointer; font-size:22px; color:var(--nf-muted); }
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
        <a href="<?= e(url('admin/posts')) ?>">Записи</a>
        <a href="<?= e(url('admin/categories')) ?>">Категории</a>
        <a href="<?= e(url('admin/media')) ?>">Медиа</a>
        <a href="<?= e(url('admin/settings')) ?>">Настройки</a>
        <a href="<?= e(url('admin/update')) ?>">Обновление</a>
    </nav>
    <main class="admin-content">
<?php endif; ?>
