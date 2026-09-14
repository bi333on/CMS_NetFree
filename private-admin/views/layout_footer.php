<?php if (is_logged_in()): ?>
    </main>
</div>
<?php endif; ?>

<?php if (is_logged_in() && ($needsEditor ?? false)): ?>
<!-- Модал медиа-библиотеки -->
<div class="nf-modal-overlay" id="nfMediaModal">
    <div class="nf-modal">
        <span class="nf-modal-close" onclick="nfCloseMedia()">&times;</span>
        <h3>Медиабиблиотека</h3>
        <form id="nfMediaUploadForm">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="row" style="align-items:center;">
                <input type="file" name="file" id="nfMediaFile" style="flex:1;">
                <button type="submit" class="btn">Загрузить</button>
            </div>
        </form>
        <div class="nf-modal-grid" id="nfMediaGrid"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js" integrity="sha384-lo8/CN/iRaTSWve/rcVNU06/qOA1Qn47bB4ENNcUQ7tLVBqPca8yRbxhx5ic7UZM" crossorigin="anonymous"></script>
<script>
(function () {
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // --- Редактор TinyMCE ---
    window.nfInitEditor = function (textareaId) {
        if (typeof tinymce === 'undefined') {
            // Если CDN недоступен — оставляем обычную textarea.
            return;
        }

        // Разбивает переносы <br> внутри абзацев на отдельные абзацы,
        // чтобы форматирование блока (например H2) применялось только к
        // выделенной строке, а не ко всему абзацу с соседними строками.
        function splitBrToParagraphs(html) {
            return html
                .replace(/(?:<br\s*\/?>\s*)+/gi, '</p><p>')
                .replace(/<p>\s*<\/p>/gi, '');
        }

        tinymce.init({
            selector: '#' + textareaId,
            height: 480,
            menubar: false,
            branding: false,
            promotion: false,
            plugins: 'lists link image code autoresize',
            toolbar: 'undo redo | h1 h2 h3 h4 h5 h6 | bold italic underline strikethrough | bullist numlist | link image | alignleft aligncenter alignright | blockquote | removeformat code',
            block_formats: 'Параграф=p; Заголовок 1=h1; Заголовок 2=h2; Заголовок 3=h3; Заголовок 4=h4; Заголовок 5=h5; Заголовок 6=h6; Цитата=blockquote',
            content_style: 'body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.65; color: #334155; } p { margin: 0 0 12px; } h1 { font-size: 2em; margin: 1.2em 0 .5em; } h2 { font-size: 1.6em; margin: 1.3em 0 .5em; padding-bottom: .3em; border-bottom: 1px solid #e2e8f0; } h3 { font-size: 1.35em; margin: 1.2em 0 .4em; } h4 { font-size: 1.15em; margin: 1.1em 0 .4em; } h5 { font-size: 1em; margin: 1em 0 .4em; } h6 { font-size: .9em; margin: 1em 0 .4em; color: #64748b; text-transform: uppercase; letter-spacing: .03em; } ul, ol { padding-left: 1.6em; } li { margin-bottom: .3em; } blockquote { margin: 1.2em 0; padding: 10px 16px; border-left: 4px solid #2563eb; background: #eff6ff; border-radius: 0 8px 8px 0; } img { max-width: 100%; height: auto; border-radius: 10px; } code { background: #f1f5f9; color: #be185d; padding: 2px 6px; border-radius: 4px; }',
            forced_root_block: 'p',
            paste_as_text: false,
            file_picker_types: 'image',
            file_picker_callback: function (cb, value, meta) {
                nfOpenMedia(function (url) {
                    cb(url, { alt: '' });
                });
            },
            paste_preprocess: function (plugin, args) {
                args.content = splitBrToParagraphs(args.content);
            },
            setup: function (editor) {
                // Отдельные кнопки заголовков H1-H6 на панели.
                function addHeadingButton(name, tag) {
                    editor.ui.registry.addButton(name, {
                        text: tag.toUpperCase(),
                        tooltip: 'Заголовок ' + tag.toUpperCase(),
                        onAction: function () {
                            editor.execCommand('mceToggleFormat', false, tag);
                        }
                    });
                }
                ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach(function (tag) {
                    addHeadingButton(tag, tag);
                });

                editor.on('init', function () {
                    editor.setContent(splitBrToParagraphs(editor.getContent()));
                });
                editor.on('change', function () {
                    editor.save();
                });
            }
        });
    };

    // Автозапуск редактора для всех textarea с классом .nf-editor.
    function initAllEditors() {
        var areas = document.querySelectorAll('textarea.nf-editor');
        for (var i = 0; i < areas.length; i++) {
            if (!areas[i].id) {
                areas[i].id = 'nfEditor' + i;
            }
            nfInitEditor(areas[i].id);
        }
    }

    document.addEventListener('DOMContentLoaded', initAllEditors);

    // --- Модал ---
    var currentCallback = null;

    window.nfOpenMedia = function (cb) {
        currentCallback = cb;
        document.getElementById('nfMediaModal').style.display = 'block';
        nfLoadMedia();
    };

    window.nfCloseMedia = function () {
        document.getElementById('nfMediaModal').style.display = 'none';
    };

    // Выбор главного изображения записи (в сайдбаре).
    window.nfPickFeatured = function () {
        nfOpenMedia(function (url) {
            var input = document.getElementById('nfFeaturedImage');
            var preview = document.getElementById('nfFeaturedPreview');
            if (input) input.value = url;
            if (preview) {
                preview.classList.remove('empty');
                preview.innerHTML = '<img src="' + esc(url) + '" alt="">';
            }
        });
    };

    window.nfRemoveFeatured = function () {
        var input = document.getElementById('nfFeaturedImage');
        var preview = document.getElementById('nfFeaturedPreview');
        if (input) input.value = '';
        if (preview) {
            preview.classList.add('empty');
            preview.innerHTML = 'Изображение не выбрано';
        }
    };

    function nfLoadMedia() {
        fetch('<?= e(url('admin/ajax/media')) ?>')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var grid = document.getElementById('nfMediaGrid');
                grid.innerHTML = '';
                (data.items || []).forEach(function (item) {
                    var el = document.createElement('div');
                    el.className = 'item';
                    el.innerHTML = item.mime.indexOf('image/') === 0
                        ? '<img src="' + esc(item.url) + '" alt="">'
                        : '<div style="height:70px;display:flex;align-items:center;justify-content:center;">📄</div>';
                    el.innerHTML += '<div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(item.name) + '</div>';
                    el.onclick = function () {
                        if (currentCallback) currentCallback(item.url);
                        nfCloseMedia();
                    };
                    grid.appendChild(el);
                });
            });
    }

    var uploadForm = document.getElementById('nfMediaUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(uploadForm);
            fetch('<?= e(url('admin/ajax/media/upload')) ?>', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) { alert(data.error); return; }
                    nfLoadMedia();
                })
                .catch(function () { alert('Ошибка загрузки'); });
        });
    }

    // Закрытие модала по клику на подложку.
    document.getElementById('nfMediaModal').addEventListener('click', function (e) {
        if (e.target === this) nfCloseMedia();
    });
})();
</script>
<?php endif; ?>
</body>
</html>
