<?php if (is_logged_in()): ?>
    </main>
</div>
<?php endif; ?>

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

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
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
                .replace(/<p>\s*<\/p>/gi, '')
                .replace(/<p>\s*<\/p>/gi, '');
        }

        tinymce.init({
            selector: '#' + textareaId,
            height: 480,
            menubar: false,
            branding: false,
            promotion: false,
            plugins: 'lists link image code autoresize paste',
            toolbar: 'undo redo | formatselect | bold italic underline strikethrough | bullist numlist | link image | alignleft aligncenter alignright | blockquote | removeformat code',
            block_formats: 'Параграф=p; Заголовок 2=h2; Заголовок 3=h3; Заголовок 4=h4; Цитата=blockquote',
            content_style: 'body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.6; } p { margin: 0 0 12px; } img { max-width: 100%; height: auto; }',
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
                editor.on('init', function () {
                    var initial = editor.getContent();
                    editor.setContent(splitBrToParagraphs(initial));
                });
                editor.on('change', function () {
                    editor.save();
                });
            }
        });
    };

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
</body>
</html>
