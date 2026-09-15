(function () {
    'use strict';

    var CFG = window.NF_BUILDER_CONFIG;
    if (!CFG) { return; }

    // ---------------------------------------------------------------- state
    var state = {
        type: CFG.type,
        id: CFG.id,
        device: 'desktop',
        selectedId: null,
        itab: 'content',
        ptab: 'palette',
        document: CFG.document || { version: 1, sections: [] },
        blocks: {},
        propMap: {},
        px: [],
        breakpoints: { tablet: '1024px', mobile: '767px' },
        history: [],
        historyIndex: -1,
        dirty: false,
        media: { q: '', page: 1, perPage: 24, total: 0 },
        pickTarget: null,
        iframe: null,
        overlayEl: null
    };

    // ---------------------------------------------------------------- utils
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s === null || s === undefined) ? '' : String(s);
        return d.innerHTML;
    }
    function uid() { return 'n' + Math.random().toString(16).slice(2, 8); }
    function doc() { return state.iframe && state.iframe.contentDocument; }
    function toast(msg, isError) {
        var t = $('#bToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'bToast';
            t.className = 'b-toast';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.className = 'b-toast show' + (isError ? ' error' : '');
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.className = 'b-toast'; }, 2200);
    }
    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    // ---------------------------------------------------------------- node helpers
    function findSection(id) {
        for (var i = 0; i < state.document.sections.length; i++) {
            if (state.document.sections[i].id === id) return state.document.sections[i];
        }
        return null;
    }
    function findNode(id) {
        var sections = state.document.sections || [];
        for (var si = 0; si < sections.length; si++) {
            var s = sections[si];
            if (s.id === id) return { node: s, section: s, parent: null, index: si, kind: 'section' };
            if (s.type === 'free') {
                // Свободная секция: виджеты лежат прямо в section.widgets.
                var fws = s.widgets || [];
                for (var fwi = 0; fwi < fws.length; fwi++) {
                    if (fws[fwi].id === id) return { node: fws[fwi], section: s, free: true, index: fwi, kind: 'widget' };
                }
                continue;
            }
            var cols = s.columns || [];
            for (var ci = 0; ci < cols.length; ci++) {
                var c = cols[ci];
                if (c.id === id) return { node: c, section: s, parent: s, index: ci, kind: 'column' };
                var ws = c.widgets || [];
                for (var wi = 0; wi < ws.length; wi++) {
                    if (ws[wi].id === id) return { node: ws[wi], section: s, column: c, index: wi, kind: 'widget' };
                }
            }
        }
        return null;
    }
    function newNode(type, free) {
        var b = state.blocks[type] || {};
        var data = {};
        Object.keys(b.fields || {}).forEach(function (k) {
            var f = b.fields[k];
            data[k] = (f && f.default !== undefined) ? f.default : '';
        });
        var w = { id: uid(), type: type, data: data, design: {}, advanced: {} };
        if (free) {
            w.settings = { pos: { x: 10, y: 10, w: 50 }, z: 0 };
        }
        return w;
    }
    function newColumn() { return { id: uid(), type: 'column', settings: { width: { desktop: 100 } }, design: {}, advanced: {}, widgets: [] }; }
    function newSection() { return { id: uid(), type: 'section', settings: { width: 'boxed', gap: 24 }, design: {}, advanced: {}, columns: [newColumn()] }; }
    function newFreeSection() { return { id: uid(), type: 'free', settings: { width: 'boxed', minHeight: 400 }, design: {}, advanced: {}, widgets: [] }; }

    // ---------------------------------------------------------------- server
    function api(url, body) {
        return fetch(url, {
            method: body === undefined ? 'GET' : 'POST',
            headers: body === undefined ? {} : { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
            body: body === undefined ? undefined : JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function renderSectionServer(section) {
        return api(CFG.renderUrl, { section: section }).then(function (j) {
            if (j && j.error) { throw new Error(j.error); }
            return j.html;
        });
    }

    // ---------------------------------------------------------------- live CSS (по propMap из PHP)
    function media(bp) { return '@media (max-width:' + (state.breakpoints[bp] || '1024px') + '){'; }
    function unit(v) {
        if (typeof v === 'number') return v + 'px';
        if (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v.trim())) return v.trim() + 'px';
        return v;
    }
    function decl(cssProp, key, value) {
        if (value === null || value === undefined || value === '') return '';
        var v = value;
        if (state.px.indexOf(key) !== -1) v = unit(v);
        if (typeof v === 'number') v = String(v);
        if (typeof v !== 'string') return '';
        return cssProp + ':' + v + ';';
    }
    function backgroundDecls(v) {
        if (!v || typeof v !== 'object') return '';
        var type = v.type || 'color';
        var val = v.value || '';
        if (type === 'gradient') return val ? 'background-image:' + val + ';' : '';
        if (type === 'image') return val ? "background-image:url('" + val.replace(/'/g, "\\'") + "');background-size:cover;background-position:center;" : '';
        return val ? 'background-color:' + val + ';' : '';
    }
    function block(sel, props) {
        var out = '';
        Object.keys(props || {}).forEach(function (k) {
            var v = props[k];
            if (k === 'background') { out += backgroundDecls(v); return; }
            var cssProp = state.propMap[k];
            if (!cssProp) return;
            out += decl(cssProp, k, v);
        });
        return out ? sel + '{' + out + '}' : '';
    }
    function designRules(sel, design) {
        var css = '';
        if (design && design.desktop) css += block(sel, design.desktop);
        Object.keys(state.breakpoints).forEach(function (bp) {
            if (design && design[bp]) css += media(bp) + block(sel, design[bp]) + '}';
        });
        return css;
    }
    function nodeCss(node) {
        var sel = '#nf-' + node.id;
        var css = designRules(sel, node.design);
        var hideOn = (node.advanced && node.advanced.hideOn) || [];
        hideOn.forEach(function (bp) { css += media(bp) + sel + '{display:none!important}' + '}'; });
        var showOnly = (node.advanced && node.advanced.showOnly) || [];
        if (showOnly.length) {
            Object.keys(state.breakpoints).forEach(function (bp) {
                if (showOnly.indexOf(bp) === -1) css += media(bp) + sel + '{display:none!important}' + '}';
            });
        }
        return css;
    }
    function freeWidgetCss(w) {
        var sel = '#nf-' + w.id;
        var pos = (w.settings && w.settings.pos) || {};
        var css = sel + '{position:absolute;';
        css += 'left:' + (pos.x || 0) + '%;';
        css += 'top:' + (pos.y || 0) + '%;';
        css += 'width:' + (pos.w || 50) + '%;';
        if (pos.h) css += 'height:' + pos.h + 'px;';
        css += 'z-index:' + ((w.settings && w.settings.z) || 0) + ';';
        css += '}' + nodeCss(w);
        return css;
    }
    function freeSectionCss(s) {
        var sel = '#nf-' + s.id;
        var css = '';
        var h = (s.settings && s.settings.height) || 0;
        var mh = (s.settings && s.settings.minHeight) || 0;
        if (h > 0) css += sel + '{height:' + h + 'px;}';
        else if (mh > 0) css += sel + '{min-height:' + mh + 'px;}';
        else css += sel + '{min-height:300px;}';
        css += nodeCss(s);
        (s.widgets || []).forEach(function (w) { css += freeWidgetCss(w); });
        return css;
    }
    function columnCss(col) {
        var sel = '#nf-' + col.id;
        var w = (col.settings && col.settings.width) || {};
        var css = '';
        if (w.desktop !== null && w.desktop !== undefined) css += sel + '{flex:0 0 ' + w.desktop + '%;max-width:' + w.desktop + '%;}';
        Object.keys(state.breakpoints).forEach(function (bp) {
            if (w[bp] !== null && w[bp] !== undefined) css += media(bp) + sel + '{flex:0 0 ' + w[bp] + '%;max-width:' + w[bp] + '%;}}';
        });
        return css + nodeCss(col);
    }
    function generateCss() {
        var css = '';
        (state.document.sections || []).forEach(function (s) {
            if (s.type === 'free') {
                css += freeSectionCss(s);
                return;
            }
            css += nodeCss(s);
            (s.columns || []).forEach(function (c) {
                css += columnCss(c);
                (c.widgets || []).forEach(function (w) { css += nodeCss(w); });
            });
        });
        return css;
    }
    function refreshLiveCss() {
        var d = doc();
        if (!d) return;
        var style = d.getElementById('nf-live');
        if (!style) {
            style = d.createElement('style');
            style.id = 'nf-live';
            d.head.appendChild(style);
        }
        style.textContent = generateCss();
    }

    // ---------------------------------------------------------------- DOM apply
    function contentContainer() {
        var d = doc();
        if (!d) return null;
        return d.querySelector('.page-content') || d.querySelector('main') || d.body;
    }
    function autoHeight() {
        var d = doc();
        if (!d || !d.body) return;
        state.iframe.style.height = Math.max(d.body.scrollHeight, 600) + 'px';
    }
    function insertSection(s) {
        return renderSectionServer(s).then(function (html) {
            var d = doc();
            var holder = d.createElement('div');
            holder.innerHTML = html.trim();
            contentContainer().appendChild(holder.firstElementChild);
            refreshLiveCss(); refreshOverlays(); autoHeight(); syncEditable();
        });
    }
    function rerenderSection(section) {
        return renderSectionServer(section).then(function (html) {
            var d = doc();
            var old = d.getElementById('nf-' + section.id);
            if (old) { old.outerHTML = html.trim(); }
            else { insertSection(section); return; }
            refreshLiveCss(); refreshOverlays(); autoHeight(); syncEditable();
        });
    }

    // ---------------------------------------------------------------- history
    function pushHistory() {
        var snap = JSON.stringify(state.document);
        if (state.history[state.historyIndex] === snap) return;
        state.history = state.history.slice(0, state.historyIndex + 1);
        state.history.push(snap);
        if (state.history.length > 60) state.history.shift();
        state.historyIndex = state.history.length - 1;
        state.dirty = true;
        updateUndoButtons();
    }
    function updateUndoButtons() {
        var u = $('#bUndo'), r = $('#bRedo');
        if (u) u.disabled = state.historyIndex <= 0;
        if (r) r.disabled = state.historyIndex >= state.history.length - 1;
    }
    function restore(snap) {
        state.document = JSON.parse(snap);
        var d = doc();
        var container = contentContainer();
        $$('.nf-section', container).forEach(function (el) { el.remove(); });
        var chain = Promise.resolve();
        (state.document.sections || []).forEach(function (s) {
            chain = chain.then(function () { return insertSection(s); });
        });
        chain.then(function () {
            state.selectedId = null;
            refreshLiveCss(); refreshOverlays(); renderInspector(); renderStructure();
        });
        updateUndoButtons();
    }

    // ---------------------------------------------------------------- overlays
    function refreshOverlays() {
        state.overlayEl.innerHTML = '';
        var d = doc();
        if (!d) return;
        var frameRect = state.iframe.getBoundingClientRect();
        var wrap = $('#bCanvasWrap');
        var wrapRect = wrap.getBoundingClientRect();
        var els = d.querySelectorAll('.nf-section, .nf-col, .nf-widget');
        els.forEach(function (el) {
            var id = (el.id || '').replace(/^nf-/, '');
            var box = el.getBoundingClientRect();
            var div = document.createElement('div');
            div.className = 'b-hover' + (id === state.selectedId ? ' selected' : '');
            div.style.left = (frameRect.left + box.left - wrapRect.left + wrap.scrollLeft) + 'px';
            div.style.top = (frameRect.top + box.top - wrapRect.top + wrap.scrollTop) + 'px';
            div.style.width = box.width + 'px';
            div.style.height = box.height + 'px';
            div.dataset.id = id;
            // Редактируемый текстовый виджет: клики по телу уходят в iframe (contenteditable).
            var found = findNode(id);

            // Подпись блока (название виджета/секции/колонки).
            if (found) {
                var label = document.createElement('div');
                label.className = 'b-label';
                if (found.kind === 'widget') {
                    label.textContent = (state.blocks[found.node.type] && state.blocks[found.node.type].label) || found.node.type;
                } else if (found.kind === 'column') {
                    label.textContent = 'Колонка';
                } else if (found.node.type === 'free') {
                    label.textContent = 'Свободная секция';
                } else {
                    label.textContent = 'Секция';
                }
                div.appendChild(label);
            }

            var editable = found && found.kind === 'widget' && (found.node.type === 'heading' || found.node.type === 'text');

            if (found && found.free && found.kind === 'widget') {
                // Свободные виджеты: перетаскивание за ручку в тулбаре (✥).
                if (editable) {
                    // Текстовый виджет: клики по телу уходят в iframe (contenteditable).
                    if (id !== state.selectedId) {
                        div.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); select(id); });
                    } else {
                        div.style.pointerEvents = 'none';
                    }
                } else {
                    makeFreeDraggable(div, found);
                }
            } else if (id === state.selectedId && editable) {
                div.style.pointerEvents = 'none';
            } else {
                div.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); select(id); });
            }
            state.overlayEl.appendChild(div);

            // Ручка растягивания свободной секции (внизу).
            if (found && found.kind === 'section' && found.node.type === 'free') {
                var resize = document.createElement('div');
                resize.className = 'b-resize-handle';
                resize.title = 'Растянуть секцию';
                resize.style.left = div.style.left;
                resize.style.width = div.style.width;
                resize.style.top = (parseFloat(div.style.top) + parseFloat(div.style.height) - 4) + 'px';
                state.overlayEl.appendChild(resize);
                makeFreeSectionResizable(resize, found);
            }
        });
        renderToolbar();
    }

    // Растягивание свободной секции за нижнюю ручку (в px).
    function makeFreeSectionResizable(handle, found) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            select(found.node.id);

            var startY = e.clientY;
            var sectionEl = doc() && doc().getElementById('nf-' + found.node.id);
            if (!sectionEl) return;
            var origH = sectionEl.getBoundingClientRect().height;

            state.iframe.style.pointerEvents = 'none';
            document.body.classList.add('b-resizing');

            function onMove(ev) {
                var nh = Math.max(40, Math.round(origH + (ev.clientY - startY)));
                found.node.settings.height = nh;
                delete found.node.settings.minHeight;
                refreshLiveCss();
            }
            function onUp() {
                state.iframe.style.pointerEvents = '';
                document.body.classList.remove('b-resizing');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                pushHistory();
                refreshOverlays();
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    // Перетаскивание свободного виджета мышью (в % от секции).
    function makeFreeDraggable(handle, found) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            select(found.node.id);

            var startX = e.clientX;
            var startY = e.clientY;
            var pos = found.node.settings.pos = found.node.settings.pos || { x: 0, y: 0, w: 50 };
            var origX = pos.x || 0;
            var origY = pos.y || 0;

            // Отключаем iframe, чтобы родитель получал mousemove/mouseup (курсор над iframe).
            state.iframe.style.pointerEvents = 'none';
            document.body.classList.add('b-dragging');

            function onMove(ev) {
                var sectionEl = doc() && doc().getElementById('nf-' + found.section.id);
                if (!sectionEl) return;
                var rect = sectionEl.getBoundingClientRect();
                var dx = (ev.clientX - startX) / rect.width * 100;
                var dy = (ev.clientY - startY) / rect.height * 100;
                pos.x = Math.max(0, Math.min(100, Math.round((origX + dx) * 10) / 10));
                pos.y = Math.max(0, Math.min(100, Math.round((origY + dy) * 10) / 10));
                refreshLiveCss();
            }

            function onUp() {
                state.iframe.style.pointerEvents = '';
                document.body.classList.remove('b-dragging');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                pushHistory();
                refreshOverlays();
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }
    function renderToolbar() {
        var old = $('.b-toolbar', state.overlayEl);
        if (old) old.remove();
        var sel = state.selectedId ? findNode(state.selectedId) : null;
        if (!sel) return;
        var hover = $$('.b-hover.selected', state.overlayEl)[0];
        if (!hover) return;

        var bar = document.createElement('div');
        bar.className = 'b-toolbar';
        bar.style.left = hover.style.left;
        bar.style.top = (parseFloat(hover.style.top) - 40) + 'px';

        function btn(text, title, fn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = text;
            b.title = title;
            b.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
            b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
            bar.appendChild(b);
        }

        // Название блока на чёрной панели.
        var label = document.createElement('span');
        label.className = 'b-toolbar-label';
        if (sel.kind === 'widget') {
            label.textContent = (state.blocks[sel.node.type] && state.blocks[sel.node.type].label) || sel.node.type;
        } else if (sel.kind === 'column') {
            label.textContent = 'Колонка';
        } else if (sel.node.type === 'free') {
            label.textContent = 'Свободная секция';
        } else {
            label.textContent = 'Секция';
        }
        bar.appendChild(label);

        // Крестик-ручка перетаскивания (drag handle).
        var grip = document.createElement('button');
        grip.type = 'button';
        grip.className = 'b-toolbar-grip';
        grip.textContent = '✥';
        grip.title = 'Перетащить';
        bar.appendChild(grip);
        if (sel.free && sel.kind === 'widget') {
            makeFreeDraggable(grip, sel);
        } else if (sel.kind === 'section') {
            grip.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
        }

        btn('↑', 'Выше', function () { moveNode(state.selectedId, -1); });
        btn('↓', 'Ниже', function () { moveNode(state.selectedId, 1); });
        if (sel.free && sel.kind === 'widget') {
            btn('▲', 'Слой выше', function () { layerNode(state.selectedId, 1); });
            btn('▼', 'Слой ниже', function () { layerNode(state.selectedId, -1); });
        }
        if (sel.kind === 'section') btn('+К', 'Добавить колонку', function () { addColumn(sel.node); });
        if (sel.kind === 'section' || sel.kind === 'column' || sel.free) btn('+В', 'Добавить виджет', function () { openPaletteAdd(); });
        btn('⧉', 'Дублировать', function () { duplicateNode(state.selectedId); });
        btn('✕', 'Удалить', function () { deleteNode(state.selectedId); });

        state.overlayEl.appendChild(bar);
    }

    // Изменение порядка слоёв (z-index) свободных виджетов.
    function layerNode(id, delta) {
        var found = findNode(id);
        if (!found || !found.free) return;
        var ws = found.section.widgets || [];
        // Пересчёт z-index всех слоёв по порядку.
        ws.forEach(function (w, i) { w.settings = w.settings || {}; w.settings.z = i; });
        var idx = ws.indexOf(found.node);
        var ni = idx + delta;
        if (ni < 0 || ni >= ws.length) return;
        ws.splice(idx, 1);
        ws.splice(ni, 0, found.node);
        ws.forEach(function (w, i) { w.settings.z = i; });
        pushHistory();
        refreshLiveCss();
        rerenderSection(found.section).then(function () { refreshOverlays(); renderStructure(); });
    }
    function openPaletteAdd() {
        state.ptab = 'palette';
        $$('#nf-builder [data-ptab]').forEach(function (b) { b.classList.toggle('active', b.dataset.ptab === 'palette'); });
        $('#bPalette').hidden = false;
        $('#bStructure').hidden = true;
    }

    // ---------------------------------------------------------------- selection
    function select(id) {
        state.selectedId = id;
        refreshOverlays();
        renderInspector();
        renderStructure();
        syncEditable();
    }
    function syncEditable() {
        var d = doc();
        if (!d) return;
        $$('[contenteditable]', d).forEach(function (el) { el.removeAttribute('contenteditable'); });
        var sel = state.selectedId ? findNode(state.selectedId) : null;
        if (sel && sel.kind === 'widget' && (sel.node.type === 'heading' || sel.node.type === 'text')) {
            var el = d.getElementById('nf-' + sel.node.id);
            if (el) el.setAttribute('contenteditable', 'true');
        }
    }

    // ---------------------------------------------------------------- actions
    function moveNode(id, dir) {
        var sel = findNode(id);
        if (!sel) return;
        if (sel.kind === 'section') {
            var arr = state.document.sections;
            var ni = sel.index + dir;
            if (ni < 0 || ni >= arr.length) return;
            arr.splice(sel.index, 1); arr.splice(ni, 0, sel.node);
            var d = doc(); var container = contentContainer();
            arr.forEach(function (s) { var el = d.getElementById('nf-' + s.id); if (el) container.appendChild(el); });
            pushHistory(); refreshOverlays(); renderStructure();
        } else {
            var siblings;
            if (sel.free) siblings = sel.section.widgets;
            else siblings = sel.kind === 'column' ? sel.section.columns : sel.column.widgets;
            var nidx = sel.index + dir;
            if (nidx < 0 || nidx >= siblings.length) return;
            siblings.splice(sel.index, 1); siblings.splice(nidx, 0, sel.node);
            if (sel.free) siblings.forEach(function (w, i) { w.settings.z = i; });
            pushHistory();
            rerenderSection(sel.section);
        }
    }
    function duplicateNode(id) {
        var sel = findNode(id);
        if (!sel) return;
        var copy = JSON.parse(JSON.stringify(sel.node));
        regenIds(copy);
        if (sel.kind === 'section') {
            state.document.sections.splice(sel.index + 1, 0, copy);
            pushHistory();
            insertSection(copy).then(function () { select(copy.id); renderStructure(); });
        } else {
            var siblings;
            if (sel.free) siblings = sel.section.widgets;
            else siblings = sel.kind === 'column' ? sel.section.columns : sel.column.widgets;
            siblings.splice(sel.index + 1, 0, copy);
            pushHistory();
            rerenderSection(sel.section).then(function () { select(copy.id); });
        }
    }
    function regenIds(node) {
        node.id = uid();
        (node.columns || []).forEach(function (c) {
            c.id = uid();
            (c.widgets || []).forEach(function (w) { w.id = uid(); });
        });
        (node.widgets || []).forEach(function (w) { w.id = uid(); });
    }
    function deleteNode(id) {
        var sel = findNode(id);
        if (!sel) return;
        if (sel.kind === 'section') {
            state.document.sections.splice(sel.index, 1);
            var d = doc(); var el = d.getElementById('nf-' + id); if (el) el.remove();
            state.selectedId = null;
            pushHistory(); refreshLiveCss(); refreshOverlays(); renderInspector(); renderStructure();
        } else {
            var siblings;
            if (sel.free) siblings = sel.section.widgets;
            else siblings = sel.kind === 'column' ? sel.section.columns : sel.column.widgets;
            siblings.splice(sel.index, 1);
            state.selectedId = sel.section.id;
            pushHistory();
            rerenderSection(sel.section);
        }
    }
    function addSection() {
        var s = newSection();
        state.document.sections.push(s);
        pushHistory();
        insertSection(s).then(function () { select(s.id); renderStructure(); });
    }
    function addFreeSection() {
        var s = newFreeSection();
        state.document.sections.push(s);
        pushHistory();
        insertSection(s).then(function () { select(s.id); renderStructure(); });
    }
    function addColumn(section) {
        var c = newColumn();
        section.columns = section.columns || [];
        section.columns.push(c);
        pushHistory();
        rerenderSection(section).then(function () { select(c.id); });
    }
    function addWidget(type) {
        var target = null;
        var isFree = false;
        if (state.selectedId) {
            var sel = findNode(state.selectedId);
            if (sel) {
                if (sel.free) { target = sel.section; isFree = true; }
                else if (sel.kind === 'column') target = sel.node;
                else if (sel.kind === 'widget') target = sel.column;
                else if (sel.kind === 'section') {
                    if (sel.node.type === 'free') { target = sel.node; isFree = true; }
                    else target = (sel.node.columns || [])[0] || null;
                }
            }
        }
        var p;
        if (!target) {
            var s = newSection();
            state.document.sections.push(s);
            target = s.columns[0];
            p = insertSection(s);
        } else {
            p = Promise.resolve();
        }
        p.then(function () {
            var w = newNode(type, isFree);
            if (isFree) {
                // Каскадное смещение новых слоёв, чтобы они не слипались в одной точке.
                var count = (target.widgets || []).length;
                w.settings.pos = { x: 10 + (count % 5) * 8, y: 10 + (count % 5) * 8, w: 40 };
                w.settings.z = count;
            }
            target.widgets = target.widgets || [];
            target.widgets.push(w);
            if (isFree) {
                // Пересчитываем z-index слоёв.
                target.widgets.forEach(function (ww, i) { ww.settings = ww.settings || {}; ww.settings.z = i; });
            }
            pushHistory();
            rerenderSection(findNode(target.id).section).then(function () { select(w.id); });
        });
    }

    // ---------------------------------------------------------------- palette + structure
    function renderPalette() {
        var root = $('#bPalette');
        root.innerHTML = '';
        var cats = {};
        Object.keys(state.blocks).forEach(function (t) {
            var b = state.blocks[t];
            var cat = b.category || 'Прочее';
            (cats[cat] = cats[cat] || []).push({ type: t, block: b });
        });
        Object.keys(cats).sort().forEach(function (cat) {
            var h = document.createElement('div');
            h.className = 'b-cat';
            h.textContent = cat;
            root.appendChild(h);
            cats[cat].forEach(function (it) {
                var div = document.createElement('div');
                div.className = 'b-widget-item';
                div.innerHTML = (it.block.icon || '') + '<span>' + esc(it.block.label || it.type) + '</span>';
                div.addEventListener('click', function () { addWidget(it.type); });
                root.appendChild(div);
            });
        });
    }
    function renderStructure() {
        var root = $('#bStructure');
        root.innerHTML = '';
        var ul = document.createElement('ul');
        ul.className = 'b-tree';
        (state.document.sections || []).forEach(function (s) {
            ul.appendChild(structureNode(s, s.type === 'free' ? 'Свободная секция' : 'Секция', function () { select(s.id); }, [
                { t: '↑', fn: function () { moveNode(s.id, -1); } },
                { t: '↓', fn: function () { moveNode(s.id, 1); } },
                { t: '⧉', fn: function () { duplicateNode(s.id); } },
                { t: '✕', fn: function () { deleteNode(s.id); } }
            ]));
            if (s.type === 'free') {
                var fwUl = document.createElement('ul');
                (s.widgets || []).forEach(function (w) {
                    var label = (state.blocks[w.type] && state.blocks[w.type].label) || w.type;
                    fwUl.appendChild(structureNode(w, label, function () { select(w.id); }, [
                        { t: '⧉', fn: function () { duplicateNode(w.id); } },
                        { t: '✕', fn: function () { deleteNode(w.id); } }
                    ]));
                });
                ul.appendChild(fwUl);
                return;
            }
            var colUl = document.createElement('ul');
            (s.columns || []).forEach(function (c) {
                colUl.appendChild(structureNode(c, 'Колонка', function () { select(c.id); }, [
                    { t: '⧉', fn: function () { duplicateNode(c.id); } },
                    { t: '✕', fn: function () { deleteNode(c.id); } }
                ]));
                var wUl = document.createElement('ul');
                (c.widgets || []).forEach(function (w) {
                    var label = (state.blocks[w.type] && state.blocks[w.type].label) || w.type;
                    wUl.appendChild(structureNode(w, label, function () { select(w.id); }, [
                        { t: '⧉', fn: function () { duplicateNode(w.id); } },
                        { t: '✕', fn: function () { deleteNode(w.id); } }
                    ]));
                });
                colUl.appendChild(wUl);
            });
            ul.appendChild(colUl);
        });
        root.appendChild(ul);
    }
    function structureNode(node, label, onSelect, actions) {
        var li = document.createElement('li');
        var div = document.createElement('div');
        div.className = 'b-node' + (node.id === state.selectedId ? ' selected' : '');
        div.addEventListener('click', onSelect);
        var span = document.createElement('span');
        span.textContent = label;
        div.appendChild(span);
        var acts = document.createElement('span');
        acts.className = 'b-node-actions';
        actions.forEach(function (a) {
            var b = document.createElement('button');
            b.type = 'button'; b.textContent = a.t; b.title = a.t;
            b.addEventListener('click', function (e) { e.stopPropagation(); a.fn(); });
            acts.appendChild(b);
        });
        div.appendChild(acts);
        li.appendChild(div);
        return li;
    }

    // ---------------------------------------------------------------- inspector: design helpers
    function designValue(node, key) {
        var d = node.design || {};
        if (state.device !== 'desktop') {
            var bp = d[state.device] || {};
            if (Object.prototype.hasOwnProperty.call(bp, key)) return bp[key];
        }
        var base = d.desktop || {};
        return Object.prototype.hasOwnProperty.call(base, key) ? base[key] : '';
    }
    function hasOverride(node, key) {
        if (state.device === 'desktop') return false;
        var bp = (node.design || {})[state.device] || {};
        return Object.prototype.hasOwnProperty.call(bp, key);
    }
    function setDesign(node, key, value) {
        node.design = node.design || {};
        if (state.device === 'desktop') {
            node.design.desktop = node.design.desktop || {};
            if (value === '' || value === null || value === undefined) delete node.design.desktop[key];
            else node.design.desktop[key] = value;
        } else {
            var bp = node.design[state.device] = node.design[state.device] || {};
            var baseVal = (node.design.desktop || {})[key];
            if (value === baseVal || value === '' && baseVal === undefined) delete bp[key];
            else bp[key] = value;
        }
        refreshLiveCss();
    }
    function field(labelText, control, resetBtn) {
        var wrap = document.createElement('div');
        wrap.className = 'b-field';
        var label = document.createElement('label');
        label.textContent = labelText;
        wrap.appendChild(label);
        if (control) wrap.appendChild(control);
        if (resetBtn) wrap.appendChild(resetBtn);
        return wrap;
    }
    function resetBtnFor(node, key) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '↺';
        btn.title = 'Сбросить к унаследованному';
        btn.className = 'b-btn';
        btn.style.cssText = 'padding:2px 8px;font-size:11px;margin-left:6px;';
        btn.disabled = !hasOverride(node, key);
        btn.addEventListener('click', function () {
            var bp = (node.design || {})[state.device] || {};
            delete bp[key];
            refreshLiveCss();
            renderInspector();
        });
        return btn;
    }
    function numberField(labelText, node, key) {
        var input = document.createElement('input');
        input.type = 'number';
        var v = designValue(node, key);
        input.value = (v === '' || v === null || v === undefined) ? '' : v;
        input.addEventListener('change', function () {
            setDesign(node, key, input.value === '' ? '' : Number(input.value));
            renderInspector();
        });
        return field(labelText, input, state.device !== 'desktop' ? resetBtnFor(node, key) : null);
    }
    function textField(labelText, node, key) {
        var input = document.createElement('input');
        input.type = 'text';
        var v = designValue(node, key);
        input.value = (v === '' || v === null || v === undefined) ? '' : v;
        input.addEventListener('change', function () {
            setDesign(node, key, input.value);
            renderInspector();
        });
        return field(labelText, input, state.device !== 'desktop' ? resetBtnFor(node, key) : null);
    }
    function selectField(labelText, node, key, options) {
        var sel = document.createElement('select');
        var current = designValue(node, key);
        var empty = document.createElement('option');
        empty.value = ''; empty.textContent = '—';
        sel.appendChild(empty);
        Object.keys(options).forEach(function (val) {
            var o = document.createElement('option');
            o.value = val; o.textContent = options[val];
            if (String(current) === String(val)) o.selected = true;
            sel.appendChild(o);
        });
        sel.addEventListener('change', function () {
            setDesign(node, key, sel.value);
            renderInspector();
        });
        return field(labelText, sel, state.device !== 'desktop' ? resetBtnFor(node, key) : null);
    }
    function colorField(labelText, node, key) {
        var v = designValue(node, key) || '';
        var row = document.createElement('div');
        row.className = 'b-color-row';
        var color = document.createElement('input');
        color.type = 'color';
        color.value = /^#[0-9a-fA-F]{6}$/.test(v) ? v : '#2563eb';
        var txt = document.createElement('input');
        txt.type = 'text';
        txt.value = v;
        txt.placeholder = '#2563eb';
        txt.style.cssText = 'flex:1;';
        function commit(val) {
            setDesign(node, key, val);
            txt.value = val;
            if (/^#[0-9a-fA-F]{6}$/.test(val)) color.value = val;
        }
        color.addEventListener('input', function () { commit(color.value); });
        txt.addEventListener('change', function () { commit(txt.value); renderInspector(); });
        row.appendChild(color);
        row.appendChild(txt);
        return field(labelText, row, state.device !== 'desktop' ? resetBtnFor(node, key) : null);
    }
    function spacingGroup(node) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Отступы (px)';
        g.appendChild(t);
        var grid = document.createElement('div'); grid.className = 'b-grid2';
        grid.appendChild(numberField('Padding ↑', node, 'paddingTop'));
        grid.appendChild(numberField('Margin ↑', node, 'marginTop'));
        grid.appendChild(numberField('Padding →', node, 'paddingRight'));
        grid.appendChild(numberField('Margin →', node, 'marginRight'));
        grid.appendChild(numberField('Padding ↓', node, 'paddingBottom'));
        grid.appendChild(numberField('Margin ↓', node, 'marginBottom'));
        grid.appendChild(numberField('Padding ←', node, 'paddingLeft'));
        grid.appendChild(numberField('Margin ←', node, 'marginLeft'));
        g.appendChild(grid);
        return g;
    }
    function typographyGroup(node) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Типографика';
        g.appendChild(t);
        g.appendChild(numberField('Размер шрифта', node, 'fontSize'));
        g.appendChild(numberField('Насыщенность (400–900)', node, 'fontWeight'));
        g.appendChild(numberField('Межстрочный', node, 'lineHeight'));
        g.appendChild(numberField('Межбуквенный', node, 'letterSpacing'));
        g.appendChild(selectField('Регистр', node, 'textTransform', { none: '—', uppercase: 'ВЕРХНИЙ', lowercase: 'нижний', capitalize: 'С Заглавных' }));
        g.appendChild(colorField('Цвет текста', node, 'color'));
        return g;
    }
    function alignGroup(node) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Выравнивание';
        g.appendChild(t);
        g.appendChild(selectField('Выравнивание', node, 'align', { left: 'Слева', center: 'Центр', right: 'Справа', justify: 'По ширине' }));
        return g;
    }
    function backgroundGroup(node) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Фон';
        g.appendChild(t);
        // Фон: цвет
        var v = designValue(node, 'background');
        var current = (v && v.value) || '';
        var row = document.createElement('div');
        row.className = 'b-color-row';
        var color = document.createElement('input');
        color.type = 'color';
        color.value = /^#[0-9a-fA-F]{6}$/.test(current) ? current : '#ffffff';
        var txt = document.createElement('input');
        txt.type = 'text';
        txt.value = current;
        txt.placeholder = '#ffffff';
        txt.style.cssText = 'flex:1;';
        function commitBg(val) {
            node.design = node.design || {};
            if (state.device === 'desktop') {
                node.design.desktop = node.design.desktop || {};
                if (val === '') delete node.design.desktop.background;
                else node.design.desktop.background = { type: 'color', value: val };
            } else {
                var bp = node.design[state.device] = node.design[state.device] || {};
                if (val === '') delete bp.background;
                else bp.background = { type: 'color', value: val };
            }
            refreshLiveCss();
        }
        color.addEventListener('input', function () { commitBg(color.value); txt.value = color.value; });
        txt.addEventListener('change', function () { commitBg(txt.value); renderInspector(); });
        row.appendChild(color);
        row.appendChild(txt);
        var ff = document.createElement('div'); ff.className = 'b-field';
        var lbl = document.createElement('label'); lbl.textContent = 'Цвет фона';
        ff.appendChild(lbl); ff.appendChild(row);
        g.appendChild(ff);
        g.appendChild(numberField('Скругление', node, 'borderRadius'));
        g.appendChild(numberField('Мин. высота', node, 'minHeight'));
        return g;
    }
    function columnWidthGroup(col) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Ширина колонки (%)';
        g.appendChild(t);
        var w = col.settings.width = col.settings.width || {};
        [['desktop', 'Десктоп'], ['tablet', 'Планшет'], ['mobile', 'Телефон']].forEach(function (pair) {
            var input = document.createElement('input');
            input.type = 'number'; input.min = '0'; input.max = '100';
            input.value = (w[pair[0]] === null || w[pair[0]] === undefined) ? 100 : w[pair[0]];
            input.addEventListener('change', function () {
                var v = Math.max(0, Math.min(100, Number(input.value) || 0));
                if (pair[0] === 'desktop') w.desktop = v; else w[pair[0]] = v;
                refreshLiveCss(); renderInspector();
            });
            var f = field(pair[1], input, null);
            g.appendChild(f);
        });
        return g;
    }

    // ---------------------------------------------------------------- inspector
    function renderInspector() {
        var root = $('#bInspector');
        root.innerHTML = '';
        var sel = state.selectedId ? findNode(state.selectedId) : null;
        if (!sel) {
            root.innerHTML = '<div class="b-hint">Выберите элемент на холсте или в структуре</div>';
            return;
        }
        if (state.itab === 'content') renderContentTab(root, sel);
        else if (state.itab === 'style') renderStyleTab(root, sel);
        else renderAdvancedTab(root, sel);
    }
    function renderContentTab(root, sel) {
        var node = sel.node;
        if (sel.kind === 'widget') {
            var def = state.blocks[node.type] || {};
            var entries = Object.keys(def.fields || {});
            if (!entries.length) {
                root.innerHTML = '<div class="b-hint">У этого виджета нет полей содержимого</div>';
                return;
            }
            var g = document.createElement('div');
            g.className = 'b-group';
            var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = (def.label || node.type) + ' — содержимое';
            g.appendChild(t);
            entries.forEach(function (key) {
                var f = def.fields[key];
                var ftype = f.type || 'text';
                var control;
                if (ftype === 'select') {
                    control = document.createElement('select');
                    Object.keys(f.options || {}).forEach(function (val) {
                        var o = document.createElement('option');
                        o.value = val; o.textContent = f.options[val];
                        if (String(node.data[key]) === String(val)) o.selected = true;
                        control.appendChild(o);
                    });
                    control.addEventListener('change', function () {
                        node.data[key] = control.value;
                        commitContentEdit(sel);
                    });
                } else if (ftype === 'textarea' || ftype === 'richtext' || ftype === 'html') {
                    control = document.createElement('textarea');
                    control.value = node.data[key] || '';
                    control.addEventListener('change', function () {
                        node.data[key] = control.value;
                        commitContentEdit(sel);
                    });
                } else if (ftype === 'number') {
                    control = document.createElement('input');
                    control.type = 'number';
                    control.value = node.data[key] || 0;
                    control.addEventListener('change', function () {
                        node.data[key] = Number(control.value) || 0;
                        commitContentEdit(sel);
                    });
                } else {
                    control = document.createElement('input');
                    control.type = ftype === 'url' ? 'url' : 'text';
                    control.value = node.data[key] || '';
                    control.addEventListener('change', function () {
                        node.data[key] = control.value;
                        commitContentEdit(sel);
                    });
                }
                root.appendChild(field(f.label || key, control, null));
            });
            if (node.type === 'image') {
                var pickBtn = document.createElement('button');
                pickBtn.type = 'button';
                pickBtn.className = 'b-btn';
                pickBtn.textContent = 'Выбрать из библиотеки';
                pickBtn.style.cssText = 'width:100%;margin-top:4px;';
                pickBtn.addEventListener('click', function () {
                    state.pickTarget = node.id;
                    switchPanel('media');
                });
                root.appendChild(pickBtn);
            }
        } else {
            root.innerHTML = '<div class="b-hint">' + (sel.kind === 'section' ? 'Секция' : 'Колонка') + '. Настройки — во вкладке «Стиль».</div>';
        }
    }
    function commitContentEdit(sel) {
        pushHistory();
        rerenderSection(sel.section).then(function () { select(state.selectedId); });
    }
    function renderStyleTab(root, sel) {
        var node = sel.node;
        if (state.device !== 'desktop') {
            var note = document.createElement('div');
            note.className = 'b-device-note';
            note.textContent = 'Устройство: ' + state.device + ' — значения наследуются от десктопа (поле пустое = наследуется).';
            root.appendChild(note);
        }
        if (sel.kind === 'section') {
            var g = document.createElement('div'); g.className = 'b-group';
            var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Секция';
            g.appendChild(t);
            var wSel = document.createElement('select');
            ['boxed', 'full'].forEach(function (val) {
                var o = document.createElement('option');
                o.value = val; o.textContent = val === 'boxed' ? 'В контейнере (boxed)' : 'Во всю ширину (full)';
                if (node.settings.width === val) o.selected = true;
                wSel.appendChild(o);
            });
            wSel.addEventListener('change', function () { node.settings.width = wSel.value; rerenderSection(sel.section); });
            g.appendChild(field('Ширина', wSel, null));
            var gapInput = document.createElement('input');
            gapInput.type = 'number'; gapInput.value = node.settings.gap || 0;
            gapInput.addEventListener('change', function () {
                node.settings.gap = Math.max(0, Number(gapInput.value) || 0);
                rerenderSection(sel.section);
            });
            g.appendChild(field('Зазор между колонками (px)', gapInput, null));
            root.appendChild(g);
            root.appendChild(spacingGroup(node));
            root.appendChild(backgroundGroup(node));
        } else if (sel.kind === 'column') {
            root.appendChild(columnWidthGroup(node));
            root.appendChild(spacingGroup(node));
            root.appendChild(backgroundGroup(node));
        } else {
            var def = state.blocks[node.type] || {};
            var design = def.design || [];
            if (sel.free) {
                root.appendChild(freePositionGroup(node));
            }
            if (design.indexOf('typography') !== -1) root.appendChild(typographyGroup(node));
            if (design.indexOf('spacing') !== -1) root.appendChild(spacingGroup(node));
            if (design.indexOf('align') !== -1) root.appendChild(alignGroup(node));
            root.appendChild(backgroundGroup(node));
        }
    }

    // Поля позиции/размера свободного виджета (X/Y/W в %, H в px).
    function freePositionGroup(node) {
        var g = document.createElement('div');
        g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Позиция и размер';
        g.appendChild(t);
        var pos = node.settings.pos = node.settings.pos || { x: 0, y: 0, w: 50 };

        function num(label, key, suffix) {
            var input = document.createElement('input');
            input.type = 'number';
            input.value = pos[key];
            input.addEventListener('change', function () {
                pos[key] = Number(input.value) || 0;
                refreshLiveCss();
                refreshOverlays();
            });
            return field(label + (suffix || ''), input, null);
        }

        g.appendChild(num('X', 'x', ' (%)'));
        g.appendChild(num('Y', 'y', ' (%)'));
        g.appendChild(num('Ширина', 'w', ' (%)'));
        g.appendChild(num('Высота', 'h', ' (px, 0 = авто)'));

        var z = document.createElement('input');
        z.type = 'number';
        z.value = node.settings.z || 0;
        z.addEventListener('change', function () {
            node.settings.z = Number(z.value) || 0;
            refreshLiveCss();
        });
        g.appendChild(field('Слой (z-index)', z, null));

        return g;
    }
    function renderAdvancedTab(root, sel) {
        var node = sel.node;
        var g = document.createElement('div'); g.className = 'b-group';
        var t = document.createElement('div'); t.className = 'b-group-title'; t.textContent = 'Дополнительно';
        g.appendChild(t);

        if (sel.kind === 'section') {
            var anchor = document.createElement('input');
            anchor.type = 'text';
            anchor.value = (node.advanced && node.advanced.anchor) || '';
            anchor.placeholder = 'about';
            anchor.addEventListener('change', function () {
                node.advanced = node.advanced || {};
                node.advanced.anchor = anchor.value;
                rerenderSection(sel.section);
            });
            g.appendChild(field('Якорь (id для навигации)', anchor, null));
        }

        var cssClass = document.createElement('input');
        cssClass.type = 'text';
        cssClass.value = (node.advanced && node.advanced.cssClass) || '';
        cssClass.addEventListener('change', function () {
            node.advanced = node.advanced || {};
            node.advanced.cssClass = cssClass.value;
            rerenderSection(sel.section);
        });
        g.appendChild(field('CSS-класс', cssClass, null));

        var hideOn = (node.advanced && node.advanced.hideOn) || [];
        ['tablet', 'mobile'].forEach(function (bp) {
            var label = document.createElement('label');
            label.className = 'b-check';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = hideOn.indexOf(bp) !== -1;
            cb.addEventListener('change', function () {
                node.advanced = node.advanced || {};
                node.advanced.hideOn = node.advanced.hideOn || [];
                var idx = node.advanced.hideOn.indexOf(bp);
                if (cb.checked && idx === -1) node.advanced.hideOn.push(bp);
                if (!cb.checked && idx !== -1) node.advanced.hideOn.splice(idx, 1);
                refreshLiveCss(); renderInspector();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode('Скрыть на ' + (bp === 'tablet' ? 'планшете' : 'телефоне')));
            g.appendChild(label);
        });

        // Показывать ТОЛЬКО на конкретном устройстве.
        var showOnly = (node.advanced && node.advanced.showOnly) || [];
        ['tablet', 'mobile'].forEach(function (bp) {
            var label = document.createElement('label');
            label.className = 'b-check';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = showOnly.indexOf(bp) !== -1;
            cb.addEventListener('change', function () {
                node.advanced = node.advanced || {};
                node.advanced.showOnly = node.advanced.showOnly || [];
                var idx = node.advanced.showOnly.indexOf(bp);
                if (cb.checked && idx === -1) node.advanced.showOnly.push(bp);
                if (!cb.checked && idx !== -1) node.advanced.showOnly.splice(idx, 1);
                refreshLiveCss(); renderInspector();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode('Показывать только на ' + (bp === 'tablet' ? 'планшете' : 'телефоне')));
            g.appendChild(label);
        });

        root.appendChild(g);
    }

    // ---------------------------------------------------------------- save
    function save() {
        var btn = $('#bSave');
        btn.disabled = true;
        var title = $('#bTitle').value.trim();
        api(CFG.saveUrl, {
            type: state.type,
            id: state.id,
            title: title,
            document: state.document
        }).then(function (j) {
            btn.disabled = false;
            if (j && j.error) { toast(j.error, true); return; }
            state.dirty = false;
            toast('Сохранено');
        }).catch(function (e) {
            btn.disabled = false;
            toast('Ошибка: ' + e.message, true);
        });
    }

    // ---------------------------------------------------------------- history + preview
    function renderHistory() {
        var root = $('#bHistory');
        root.innerHTML = '<div class="b-hint">Загрузка…</div>';
        api(CFG.revisionsUrl + '?type=' + encodeURIComponent(state.type) + '&id=' + state.id).then(function (j) {
            var revs = (j && j.revisions) || [];
            root.innerHTML = '';
            if (!revs.length) {
                root.innerHTML = '<div class="b-hint">Ревизий пока нет</div>';
                return;
            }
            var ul = document.createElement('ul');
            ul.className = 'b-tree';
            revs.forEach(function (r) {
                var li = document.createElement('li');
                var div = document.createElement('div');
                div.className = 'b-node';
                var span = document.createElement('span');
                span.textContent = (r.is_autosave ? 'Автосохранение' : 'Сохранение') + ' · ' + (r.created_at || '') + (r.has_blocks ? '' : ' · классика');
                div.appendChild(span);
                var acts = document.createElement('span');
                acts.className = 'b-node-actions';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '↩';
                btn.title = 'Восстановить';
                btn.addEventListener('click', function () { restoreRevision(r.id); });
                acts.appendChild(btn);
                div.appendChild(acts);
                li.appendChild(div);
                ul.appendChild(li);
            });
            root.appendChild(ul);
        });
    }
    function restoreRevision(revId) {
        if (!confirm('Восстановить эту ревизию? Текущее состояние будет сохранено как новая ревизия.')) return;
        api(CFG.restoreUrl, { type: state.type, id: state.id, revision_id: revId }).then(function (j) {
            if (j && j.error) { toast(j.error, true); return; }
            location.reload();
        }).catch(function (e) { toast('Ошибка: ' + e.message, true); });
    }
    function preview() {
        // Сначала автосохраняем текущее состояние, чтобы предпросмотр показал последние правки.
        var pre = state.dirty
            ? api(CFG.autosaveUrl, {
                type: state.type,
                id: state.id,
                title: $('#bTitle').value.trim(),
                document: state.document
            }).then(function () { state.dirty = false; })
            : Promise.resolve();
        pre.then(function () {
            return api(CFG.previewTokenUrl, { type: state.type, id: state.id });
        }).then(function (j) {
            if (j && j.error) { toast(j.error, true); return; }
            if (j && j.url) window.open(j.url, '_blank');
        }).catch(function (e) { toast('Ошибка: ' + e.message, true); });
    }

    // ---------------------------------------------------------------- media
    function switchPanel(tab) {
        state.ptab = tab;
        $$('#nf-builder [data-ptab]').forEach(function (x) { x.classList.toggle('active', x.dataset.ptab === tab); });
        $('#bPalette').hidden = tab !== 'palette';
        $('#bStructure').hidden = tab !== 'structure';
        $('#bMedia').hidden = tab !== 'media';
        $('#bHistory').hidden = tab !== 'history';
        if (tab === 'structure') renderStructure();
        if (tab === 'media') renderMediaPanel();
        if (tab === 'history') renderHistory();
    }
    function renderMediaPanel() {
        var root = $('#bMedia');
        if (!root.dataset.ready) {
            root.dataset.ready = '1';
            root.innerHTML =
                '<input type="text" id="bMediaSearch" class="b-media-search" placeholder="Поиск...">' +
                '<div id="bMediaGrid" class="b-media-grid"></div>' +
                '<div class="b-media-pager">' +
                '<button type="button" id="bMediaPrev" class="b-btn">←</button>' +
                '<span id="bMediaInfo"></span>' +
                '<button type="button" id="bMediaNext" class="b-btn">→</button>' +
                '</div>';
            $('#bMediaSearch').addEventListener('input', debounce(function () {
                state.media.q = this.value;
                state.media.page = 1;
                loadMedia();
            }, 300));
            $('#bMediaPrev').addEventListener('click', function () { if (state.media.page > 1) { state.media.page--; loadMedia(); } });
            $('#bMediaNext').addEventListener('click', function () { state.media.page++; loadMedia(); });
        }
        loadMedia();
    }
    function loadMedia() {
        var url = CFG.mediaUrl + '?q=' + encodeURIComponent(state.media.q) + '&page=' + state.media.page + '&per_page=' + state.media.perPage;
        api(url).then(function (j) {
            var grid = $('#bMediaGrid');
            if (!grid) return;
            grid.innerHTML = '';
            (j.items || []).forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'b-media-item';
                if ((item.mime || '').indexOf('image/') === 0) {
                    div.innerHTML = '<img src="' + esc(item.url) + '" alt="' + esc(item.alt || '') + '">';
                } else {
                    div.innerHTML = '<div class="b-media-file">📄</div>';
                }
                if (item.width && item.height) {
                    div.innerHTML += '<div class="b-media-dims">' + item.width + '×' + item.height + '</div>';
                }
                div.addEventListener('click', function () { pickMedia(item); });
                grid.appendChild(div);
            });
            var totalPages = Math.max(1, Math.ceil((j.total || 0) / state.media.perPage));
            var info = $('#bMediaInfo'), prev = $('#bMediaPrev'), next = $('#bMediaNext');
            if (info) info.textContent = 'Стр. ' + j.page + ' / ' + totalPages;
            if (prev) prev.disabled = j.page <= 1;
            if (next) next.disabled = j.page >= totalPages;
        });
    }
    function pickMedia(item) {
        if (state.pickTarget) {
            var found = findNode(state.pickTarget);
            state.pickTarget = null;
            if (found && found.kind === 'widget' && found.node.type === 'image') {
                found.node.data.src = item.url;
                found.node.data.alt = item.alt || '';
                if (item.width) found.node.data.width = item.width;
                if (item.height) found.node.data.height = item.height;
                pushHistory();
                rerenderSection(found.section).then(function () { renderInspector(); });
            }
            return;
        }
        var w = newNode('image');
        w.data.src = item.url;
        w.data.alt = item.alt || '';
        if (item.width) w.data.width = item.width;
        if (item.height) w.data.height = item.height;
        insertWidgetIntoSelection(w);
    }
    function insertWidgetIntoSelection(w) {
        var target = null;
        var isFree = false;
        if (state.selectedId) {
            var sel = findNode(state.selectedId);
            if (sel) {
                if (sel.free) { target = sel.section; isFree = true; }
                else if (sel.kind === 'column') target = sel.node;
                else if (sel.kind === 'widget') target = sel.column;
                else if (sel.kind === 'section') {
                    if (sel.node.type === 'free') { target = sel.node; isFree = true; }
                    else target = (sel.node.columns || [])[0] || null;
                }
            }
        }
        if (isFree && !w.settings) {
            w.settings = { pos: { x: 10, y: 10, w: 50 }, z: 0 };
        }
        if (!target) {
            var s = newSection();
            state.document.sections.push(s);
            target = s.columns[0];
            insertSection(s).then(function () { appendWidget(target, w); });
            return;
        }
        appendWidget(target, w, isFree);
    }
    function appendWidget(target, w, isFree) {
        target.widgets = target.widgets || [];
        if (isFree && !w.settings) {
            var count = target.widgets.length;
            w.settings = { pos: { x: 10 + (count % 5) * 8, y: 10 + (count % 5) * 8, w: 40 }, z: count };
        }
        target.widgets.push(w);
        if (isFree) {
            target.widgets.forEach(function (ww, i) { ww.settings = ww.settings || {}; ww.settings.z = i; });
        }
        pushHistory();
        rerenderSection(findNode(target.id).section).then(function () { select(w.id); });
    }
    function uploadImage(file) {
        var fd = new FormData();
        fd.append('_csrf', CFG.csrf);
        fd.append('file', file);
        return fetch(CFG.mediaUploadUrl, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }
    function insertUploaded(item) {
        if (!item || !item.url) return;
        var w = newNode('image');
        w.data.src = item.url;
        w.data.alt = '';
        if (item.width) w.data.width = item.width;
        if (item.height) w.data.height = item.height;
        insertWidgetIntoSelection(w);
    }

    // ---------------------------------------------------------------- init
    function init() {
        state.iframe = $('#bCanvas');
        state.overlayEl = $('#bOverlay');

        // Табы палитры/структуры/медиа/истории.
        $$('#nf-builder [data-ptab]').forEach(function (b) {
            b.addEventListener('click', function () { switchPanel(b.dataset.ptab); });
        });
        // Табы инспектора.
        $$('#nf-builder [data-itab]').forEach(function (b) {
            b.addEventListener('click', function () {
                state.itab = b.dataset.itab;
                $$('#nf-builder [data-itab]').forEach(function (x) { x.classList.toggle('active', x === b); });
                renderInspector();
            });
        });
        // Устройства.
        $$('#nf-builder [data-device]').forEach(function (b) {
            b.addEventListener('click', function () {
                state.device = b.dataset.device;
                $$('#nf-builder [data-device]').forEach(function (x) { x.classList.toggle('active', x === b); });
                $('#bCanvasWrap').className = 'b-canvas-wrap' + (state.device !== 'desktop' ? ' device-' + state.device : '');
                setTimeout(function () { refreshLiveCss(); refreshOverlays(); autoHeight(); renderInspector(); }, 200);
            });
        });
        // Кнопки.
        $('#bAddSection').addEventListener('click', addSection);
        var bFree = $('#bAddFreeSection');
        if (bFree) bFree.addEventListener('click', addFreeSection);
        $('#bSave').addEventListener('click', save);
        $('#bPreview').addEventListener('click', preview);
        $('#bUndo').addEventListener('click', function () {
            if (state.historyIndex > 0) { state.historyIndex--; restore(state.history[state.historyIndex]); }
        });
        $('#bRedo').addEventListener('click', function () {
            if (state.historyIndex < state.history.length - 1) { state.historyIndex++; restore(state.history[state.historyIndex]); }
        });

        // Холст.
        state.iframe.addEventListener('load', function () {
            var d = doc();
            // Синхронизация contenteditable по blur.
            d.addEventListener('blur', function (e) {
                var el = e.target;
                if (el && el.isContentEditable) {
                    var id = (el.id || '').replace(/^nf-/, '');
                    var found = findNode(id);
                    if (found && found.kind === 'widget') {
                        var val = found.node.type === 'heading' ? el.textContent : el.innerHTML;
                        if (found.node.data.text !== val) {
                            found.node.data.text = val;
                            pushHistory();
                        }
                    }
                }
            }, true);
            refreshLiveCss();
            autoHeight();
            refreshOverlays();
            updateUndoButtons();
        });

        // Схема виджетов.
        api(CFG.blocksUrl).then(function (j) {
            state.blocks = (j && j.blocks) || {};
            state.propMap = (j && j.propMap) || {};
            state.px = (j && j.px) || [];
            state.breakpoints = (j && j.breakpoints) || state.breakpoints;
            renderPalette();
            renderStructure();
        });

        // Автосохранение раз в 30 секунд при наличии несохранённых изменений.
        setInterval(function () {
            if (state.dirty) {
                state.dirty = false;
                api(CFG.autosaveUrl, {
                    type: state.type,
                    id: state.id,
                    title: $('#bTitle').value.trim(),
                    document: state.document
                }).catch(function () { state.dirty = true; });
            }
        }, 30000);

        // Баннер «найдено автосохранение».
        if (CFG.autosave) {
            var banner = document.createElement('div');
            banner.className = 'b-autosave-banner';
            banner.textContent = 'Найдено автосохранение от ' + (CFG.autosave.created_at || '') + ' ';
            var rBtn = document.createElement('button');
            rBtn.type = 'button'; rBtn.className = 'b-btn b-btn-primary'; rBtn.textContent = 'Восстановить';
            rBtn.addEventListener('click', function () { restoreRevision(CFG.autosave.id); });
            var dBtn = document.createElement('button');
            dBtn.type = 'button'; dBtn.className = 'b-btn'; dBtn.textContent = 'Игнорировать';
            dBtn.addEventListener('click', function () { banner.remove(); });
            banner.appendChild(rBtn);
            banner.appendChild(dBtn);
            document.body.appendChild(banner);
        }

        // Вставка изображения из буфера обмена.
        window.addEventListener('paste', function (e) {
            var items = (e.clipboardData && e.clipboardData.items) || [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
                    var file = items[i].getAsFile();
                    uploadImage(file).then(function (j) {
                        if (j && j.item) insertUploaded(j.item);
                        else if (j && j.error) toast(j.error, true);
                    });
                    e.preventDefault();
                    break;
                }
            }
        });

        // Drag & drop файла изображения в холст.
        var wrap = $('#bCanvasWrap');
        wrap.addEventListener('dragover', function (e) { e.preventDefault(); });
        wrap.addEventListener('drop', function (e) {
            e.preventDefault();
            var files = (e.dataTransfer && e.dataTransfer.files) || [];
            for (var i = 0; i < files.length; i++) {
                if (files[i].type.indexOf('image/') === 0) {
                    uploadImage(files[i]).then(function (j) {
                        if (j && j.item) insertUploaded(j.item);
                        else if (j && j.error) toast(j.error, true);
                    });
                }
            }
        });

        renderInspector();
        pushHistory();
        updateUndoButtons();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
