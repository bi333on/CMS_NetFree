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

<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // --- Редактор ---
    window.nfInitEditor = function (textareaId) {
        var ta = document.getElementById(textareaId);
        if (!ta) return;

        var wrap = document.createElement('div');
        wrap.className = 'nf-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'nf-editor-toolbar';
        toolbar.innerHTML =
            '<button type="button" data-cmd="bold"><b>B</b></button>' +
            '<button type="button" data-cmd="italic"><i>I</i></button>' +
            '<button type="button" data-cmd="underline"><u>U</u></button>' +
            '<button type="button" data-cmd="insertUnorderedList">• Список</button>' +
            '<button type="button" data-cmd="formatBlock" data-val="h2">H2</button>' +
            '<button type="button" data-cmd="formatBlock" data-val="h3">H3</button>' +
            '<button type="button" data-cmd="createLink">Ссылка</button>' +
            '<button type="button" data-cmd="nfMedia">🖼 Медиа</button>';

        var area = document.createElement('div');
        area.className = 'nf-editor-area';
        area.contentEditable = 'true';
        area.innerHTML = ta.value;

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var cmd = btn.getAttribute('data-cmd');
            if (cmd === 'nfMedia') {
                nfOpenMedia(function (url) {
                    area.focus();
                    document.execCommand('insertImage', false, url);
                });
                return;
            }
            var val = btn.getAttribute('data-val') || null;
            document.execCommand(cmd, false, val);
            area.focus();
        });

        area.addEventListener('input', function () {
            ta.value = area.innerHTML;
        });

        ta.style.display = 'none';
        ta.parentNode.insertBefore(wrap, ta);
        wrap.appendChild(toolbar);
        wrap.appendChild(area);
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
