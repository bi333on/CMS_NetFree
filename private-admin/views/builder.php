<?php
/** @var string $type @var int $id @var string $title @var array $document @var string $canvasUrl @var string $backUrl @var ?array $autosave */
$adminTitle = 'Конструктор — NetFree';

// Ассеты инлайним: на некоторых хостингах статика через /nf-assets не проксируется в PHP.
$builderCss = '';
$builderJs  = '';
$coreAssets = dirname(__DIR__, 2) . '/core-assets';
if (is_file($coreAssets . '/builder.css')) {
    $builderCss = (string) file_get_contents($coreAssets . '/builder.css');
}
if (is_file($coreAssets . '/builder.js')) {
    $builderJs = (string) file_get_contents($coreAssets . '/builder.js');
}

// Конфиг для JS встраиваем через JSON.parse: экранируем <, >, &, чтобы ни один символ
// контента не смог разорвать тег <script>.
$config = [
    'type'            => $type,
    'id'              => $id,
    'document'        => $document,
    'csrf'            => csrf_token(),
    'blocksUrl'       => url('admin/ajax/builder/blocks'),
    'renderUrl'       => url('admin/ajax/builder/render'),
    'saveUrl'         => url('admin/ajax/builder/save'),
    'autosaveUrl'     => url('admin/ajax/builder/autosave'),
    'revisionsUrl'    => url('admin/ajax/builder/revisions'),
    'restoreUrl'      => url('admin/ajax/builder/revisions/restore'),
    'previewTokenUrl' => url('admin/ajax/builder/preview-token'),
    'mediaUrl'        => url('admin/ajax/media'),
    'mediaUploadUrl'  => url('admin/ajax/media/upload'),
    'autosave'        => $autosave,
];
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$configJson = str_replace(['<', '>', '&'], ['\u003c', '\u003e', '\u0026'], (string) $configJson);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?: 'Конструктор') ?> — NetFree</title>
    <style><?= $builderCss ?></style>
</head>
<body class="nf-builder-body">
<!-- NF_BUILDER_SHELL v<?= e(NF_VERSION) ?> -->
<div id="nf-builder">
    <?php if ($builderCss === '' || $builderJs === ''): ?>
    <div style="background:#dc2626;color:#fff;padding:10px 16px;font-size:13px;">Не найдены ассеты конструктора (core-assets/builder.css|js) — каталог core-assets не загружен на сервер.</div>
    <?php endif; ?>
    <header class="b-topbar">
        <div class="b-brand">NetFree <strong>Builder</strong> <span style="opacity:.55;font-size:11px;">v<?= e(NF_VERSION) ?></span></div>
        <input id="bTitle" class="b-title" type="text" value="<?= e($title) ?>" placeholder="Заголовок">
        <div class="b-devices" role="group" aria-label="Устройство">
            <button type="button" data-device="desktop" class="active">Десктоп</button>
            <button type="button" data-device="tablet">Планшет</button>
            <button type="button" data-device="mobile">Телефон</button>
        </div>
        <div class="b-actions">
            <button type="button" id="bAddSection" class="b-btn">+ Секция</button>
            <button type="button" id="bUndo" class="b-btn" title="Отменить">↶</button>
            <button type="button" id="bRedo" class="b-btn" title="Повторить">↷</button>
            <a class="b-btn b-btn-ghost" href="<?= e(url($backUrl)) ?>">Назад</a>
            <button type="button" id="bPreview" class="b-btn b-btn-ghost">Просмотр</button>
            <button type="button" id="bSave" class="b-btn b-btn-primary">Сохранить</button>
        </div>
    </header>

    <div class="b-body">
        <aside class="b-side b-side-left">
            <div class="b-tabs">
                <button type="button" data-ptab="palette" class="active">Виджеты</button>
                <button type="button" data-ptab="structure">Структура</button>
                <button type="button" data-ptab="media">Медиа</button>
                <button type="button" data-ptab="history">История</button>
            </div>
            <div id="bPalette" class="b-panel"></div>
            <div id="bStructure" class="b-panel" hidden></div>
            <div id="bMedia" class="b-panel" hidden></div>
            <div id="bHistory" class="b-panel" hidden></div>
        </aside>

        <div class="b-canvas-wrap" id="bCanvasWrap">
            <iframe id="bCanvas" src="<?= e(url($canvasUrl)) ?>" title="Холст"></iframe>
            <div id="bOverlay" class="b-overlay"></div>
        </div>

        <aside class="b-side b-side-right">
            <div class="b-tabs">
                <button type="button" data-itab="content" class="active">Содержимое</button>
                <button type="button" data-itab="style">Стиль</button>
                <button type="button" data-itab="advanced">Дополнительно</button>
            </div>
            <div id="bInspector" class="b-panel"></div>
        </aside>
    </div>
</div>

<script>
window.NF_BUILDER_CONFIG = JSON.parse(<?= json_encode($configJson) ?>);
</script>
<script><?= $builderJs ?></script>
</body>
</html>
