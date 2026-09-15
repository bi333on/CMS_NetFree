/**
 * NetFree Builder — UX улучшения
 * Клавиатурные сочетания, drag&drop виджетов, контекстное меню
 */
(function () {
    'use strict';

    // Ждём инициализации основного builder.js
    function waitForBuilder(callback) {
        if (window.NFBuilder && window.NFBuilder.ready) {
            callback();
        } else {
            setTimeout(function() { waitForBuilder(callback); }, 100);
        }
    }

    waitForBuilder(function() {
        var builder = window.NFBuilder;

        // ============================================================
        // Клавиатурные сочетания
        // ============================================================
        var clipboard = null;

        document.addEventListener('keydown', function(e) {
            // Игнорируем, если фокус в input/textarea
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea') {
                return;
            }

            var ctrl = e.ctrlKey || e.metaKey;
            var key = e.key.toLowerCase();

            // Ctrl+C — копировать выбранный элемент
            if (ctrl && key === 'c' && builder.getSelected()) {
                e.preventDefault();
                clipboard = JSON.parse(JSON.stringify(builder.getSelected()));
                showToast('Скопировано');
            }

            // Ctrl+V — вставить
            if (ctrl && key === 'v' && clipboard) {
                e.preventDefault();
                var copy = JSON.parse(JSON.stringify(clipboard));
                copy.id = generateId();
                builder.insertWidget(copy);
                showToast('Вставлено');
            }

            // Ctrl+D — дублировать
            if (ctrl && key === 'd' && builder.getSelected()) {
                e.preventDefault();
                var node = builder.getSelected();
                var copy = JSON.parse(JSON.stringify(node));
                copy.id = generateId();
                builder.duplicateNode(copy);
                showToast('Дублировано');
            }

            // Ctrl+Z — отменить
            if (ctrl && key === 'z' && !e.shiftKey) {
                e.preventDefault();
                builder.undo();
            }

            // Ctrl+Shift+Z или Ctrl+Y — повторить
            if ((ctrl && e.shiftKey && key === 'z') || (ctrl && key === 'y')) {
                e.preventDefault();
                builder.redo();
            }

            // Delete — удалить выбранный элемент
            if (key === 'delete' && builder.getSelected()) {
                e.preventDefault();
                if (confirm('Удалить выбранный элемент?')) {
                    builder.deleteSelected();
                }
            }

            // Escape — снять выделение
            if (key === 'escape') {
                builder.deselect();
            }

            // Ctrl+S — сохранить
            if (ctrl && key === 's') {
                e.preventDefault();
                builder.save();
            }
        });

        // ============================================================
        // Drag & Drop виджетов из палитры
        // ============================================================
        function makePaletteDraggable() {
            var items = document.querySelectorAll('.b-palette-item');
            items.forEach(function(item) {
                item.setAttribute('draggable', 'true');

                item.addEventListener('dragstart', function(e) {
                    var widgetType = item.dataset.type;
                    e.dataTransfer.setData('widget-type', widgetType);
                    e.dataTransfer.effectAllowed = 'copy';
                    item.classList.add('dragging');
                });

                item.addEventListener('dragend', function() {
                    item.classList.remove('dragging');
                });
            });
        }

        // Drop-зоны в iframe
        function setupDropZones() {
            var iframe = document.getElementById('bCanvas');
            if (!iframe || !iframe.contentDocument) return;

            var iframeDoc = iframe.contentDocument;
            var sections = iframeDoc.querySelectorAll('.nf-section');

            sections.forEach(function(section) {
                section.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'copy';
                    section.classList.add('drop-target');
                });

                section.addEventListener('dragleave', function() {
                    section.classList.remove('drop-target');
                });

                section.addEventListener('drop', function(e) {
                    e.preventDefault();
                    section.classList.remove('drop-target');

                    var widgetType = e.dataTransfer.getData('widget-type');
                    if (widgetType) {
                        builder.insertWidgetAt(widgetType, section.id);
                        showToast('Виджет добавлен');
                    }
                });
            });
        }

        // ============================================================
        // Контекстное меню (правая кнопка мыши)
        // ============================================================
        function createContextMenu() {
            var menu = document.createElement('div');
            menu.id = 'nf-context-menu';
            menu.className = 'nf-context-menu';
            menu.style.cssText = 'position:fixed;display:none;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;min-width:180px;';
            document.body.appendChild(menu);
            return menu;
        }

        var contextMenu = createContextMenu();

        function showContextMenu(x, y, nodeId) {
            var items = [
                { label: 'Копировать', icon: '📋', action: function() {
                    clipboard = JSON.parse(JSON.stringify(builder.getNode(nodeId)));
                    showToast('Скопировано');
                }},
                { label: 'Дублировать', icon: '📑', action: function() {
                    var node = builder.getNode(nodeId);
                    var copy = JSON.parse(JSON.stringify(node));
                    copy.id = generateId();
                    builder.duplicateNode(copy);
                    showToast('Дублировано');
                }},
                { label: 'Вставить', icon: '📌', action: function() {
                    if (clipboard) {
                        var copy = JSON.parse(JSON.stringify(clipboard));
                        copy.id = generateId();
                        builder.insertWidget(copy);
                        showToast('Вставлено');
                    }
                }, disabled: !clipboard },
                { separator: true },
                { label: 'Переместить вверх', icon: '↑', action: function() { builder.moveUp(nodeId); }},
                { label: 'Переместить вниз', icon: '↓', action: function() { builder.moveDown(nodeId); }},
                { separator: true },
                { label: 'Удалить', icon: '🗑', action: function() {
                    if (confirm('Удалить элемент?')) {
                        builder.deleteNode(nodeId);
                    }
                }, danger: true }
            ];

            contextMenu.innerHTML = '';
            items.forEach(function(item) {
                if (item.separator) {
                    var sep = document.createElement('div');
                    sep.style.cssText = 'height:1px;background:#e2e8f0;margin:4px 0;';
                    contextMenu.appendChild(sep);
                } else {
                    var btn = document.createElement('button');
                    btn.className = 'nf-context-item';
                    btn.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:none;cursor:pointer;font-size:14px;';
                    if (item.danger) btn.style.color = '#dc2626';
                    if (item.disabled) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                    btn.innerHTML = (item.icon || '') + ' ' + item.label;
                    btn.addEventListener('click', function() {
                        if (!item.disabled) item.action();
                        contextMenu.style.display = 'none';
                    });
                    btn.addEventListener('mouseenter', function() {
                        if (!item.disabled) btn.style.background = '#f1f5f9';
                    });
                    btn.addEventListener('mouseleave', function() {
                        btn.style.background = 'none';
                    });
                    contextMenu.appendChild(btn);
                }
            });

            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            contextMenu.style.display = 'block';
        }

        // Закрыть контекстное меню при клике вне его
        document.addEventListener('click', function() {
            contextMenu.style.display = 'none';
        });

        // Показывать контекстное меню на overlay-элементах
        document.addEventListener('contextmenu', function(e) {
            var target = e.target;
            if (target.classList.contains('b-overlay-item')) {
                e.preventDefault();
                var nodeId = target.dataset.id;
                if (nodeId) {
                    showContextMenu(e.clientX, e.clientY, nodeId);
                }
            }
        });

        // ============================================================
        // Поиск по виджетам в палитре
        // ============================================================
        function addPaletteSearch() {
            var palette = document.getElementById('bPalette');
            if (!palette) return;

            var searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = '🔍 Поиск виджетов...';
            searchInput.className = 'b-palette-search';
            searchInput.style.cssText = 'width:100%;padding:8px 12px;margin-bottom:12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;';

            palette.insertBefore(searchInput, palette.firstChild);

            searchInput.addEventListener('input', function() {
                var query = searchInput.value.toLowerCase();
                var items = palette.querySelectorAll('.b-palette-item');

                items.forEach(function(item) {
                    var label = (item.textContent || '').toLowerCase();
                    var type = (item.dataset.type || '').toLowerCase();
                    var matches = label.indexOf(query) !== -1 || type.indexOf(query) !== -1;
                    item.style.display = matches ? '' : 'none';
                });

                // Скрыть категории без видимых элементов
                var categories = palette.querySelectorAll('.b-palette-cat');
                categories.forEach(function(cat) {
                    var hasVisible = false;
                    var items = cat.querySelectorAll('.b-palette-item');
                    items.forEach(function(item) {
                        if (item.style.display !== 'none') hasVisible = true;
                    });
                    cat.style.display = hasVisible ? '' : 'none';
                });
            });
        }

        // ============================================================
        // Вспомогательные функции
        // ============================================================
        function generateId() {
            return 'n' + Math.random().toString(16).slice(2, 8);
        }

        function showToast(message) {
            var toast = document.getElementById('bToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'bToast';
                toast.className = 'b-toast';
                toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:8px;z-index:10000;';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 2000);
        }

        // ============================================================
        // Инициализация при готовности палитры
        // ============================================================
        var checkPalette = setInterval(function() {
            var palette = document.getElementById('bPalette');
            if (palette && palette.children.length > 0) {
                clearInterval(checkPalette);
                makePaletteDraggable();
                addPaletteSearch();
                setTimeout(setupDropZones, 500);
            }
        }, 500);

        console.log('NetFree Builder Enhancements loaded');
    });
})();
