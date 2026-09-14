<?php
/** @var string $type @var int $id @var string $title @var string $document @var string $canvasUrl @var string $backUrl */
$adminTitle = 'Конструктор — NetFree';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?: 'Конструктор') ?> — NetFree</title>
    <link rel="stylesheet" href="<?= e(url('nf-assets/builder.css?v=' . NF_VERSION)) ?>">
</head>
<body class="nf-builder-body">
<div id="nf-builder">
    <header class="b-topbar">
        <div class="b-brand">NetFree <strong>Builder</strong></div>
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
                <button type="button" data-ptab="history">История</button>
            </div>
            <div id="bPalette" class="b-panel"></div>
            <div id="bStructure" class="b-panel" hidden></div>
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
window.NF_BUILDER_CONFIG = {
    type: <?= json_encode($type) ?>,
    id: <?= (int) $id ?>,
    document: <?= $document ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    blocksUrl: <?= json_encode(url('admin/ajax/builder/blocks')) ?>,
    renderUrl: <?= json_encode(url('admin/ajax/builder/render')) ?>,
    saveUrl: <?= json_encode(url('admin/ajax/builder/save')) ?>,
    autosaveUrl: <?= json_encode(url('admin/ajax/builder/autosave')) ?>,
    revisionsUrl: <?= json_encode(url('admin/ajax/builder/revisions')) ?>,
    restoreUrl: <?= json_encode(url('admin/ajax/builder/revisions/restore')) ?>,
    previewTokenUrl: <?= json_encode(url('admin/ajax/builder/preview-token')) ?>,
    autosave: <?= $autosave ?>
};
</script>
<script src="<?= e(url('nf-assets/builder.js?v=' . NF_VERSION)) ?>"></script>
</body>
</html>
