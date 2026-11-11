/* USMSmart Operations Suite
   Six admin productivity features wired into the live dashboard:
     1. Print-ready card sheets with generated QR   4. Renew from the expiry digest
     2. Guided duplicate merge                      5. Scheduled / on-demand email digest
     3. Bulk field editor                           6. Natural-language filters
   Loaded after dashboard.js so it can reuse the directory's live state and helpers.
   Kept in assets/ops/ (outside the Tailwind content scan) and styled by usmsmart-ops.css.
   Every surface renders bilingually through T() and re-renders on usm:langchange. */
(function () {
    'use strict';
    if (window.__usmOpsSuite) return;
    window.__usmOpsSuite = true;

    /* ===== Shared helpers ================================================= */
    var T = function (ms, en) { return (window.__usmLang === 'en' && en != null) ? en : ms; };
    function isAdmin() { try { return typeof USMSMART_IS_ADMIN !== 'undefined' && !!USMSMART_IS_ADMIN; } catch (e) { return false; } }
    function esc(v) { try { if (typeof window.escapeHtml === 'function') return window.escapeHtml(v); } catch (e) {} return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function students() { return (typeof allMasterStudents !== 'undefined' && Array.isArray(allMasterStudents)) ? allMasterStudents : []; }
    function filtered() { return (typeof filteredMasterStudents !== 'undefined' && Array.isArray(filteredMasterStudents)) ? filteredMasterStudents : students(); }
    function selectedRecords() {
        var ids = (typeof masterSelectedIds !== 'undefined' && masterSelectedIds) ? masterSelectedIds : new Set();
        return students().filter(function (r) { return ids.has(String(r.id)); });
    }
    function toast(msg) { try { if (typeof window.showToast === 'function') { window.showToast(msg); return; } } catch (e) {} }
    function refreshDirectory() { try { if (typeof window.loadMasterStudents === 'function') window.loadMasterStudents(); } catch (e) {} }
    function todayText() { var n = new Date(); return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0') + '-' + String(n.getDate()).padStart(2, '0'); }
    function cardStatus(r) { try { if (typeof window.getCardStatus === 'function') return window.getCardStatus(r, todayText()); } catch (e) {} var exp = String(r.expiryDate || '').slice(0, 10); if (!/^\d{4}-\d{2}-\d{2}$/.test(exp)) return 'unknown'; return exp < todayText() ? 'expired' : 'active'; }
    function icon(name) { var i = document.createElement('i'); i.className = 'fa-solid ' + name; i.setAttribute('aria-hidden', 'true'); return i; }
    function node(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }

    function postJSON(action, body) {
        var headers = { 'Content-Type': 'application/json' };
        try { if (typeof USMSMART_CSRF_TOKEN !== 'undefined') headers['X-CSRF-Token'] = USMSMART_CSRF_TOKEN; } catch (e) {}
        return fetch('index.php?action=' + encodeURIComponent(action), { method: 'POST', headers: headers, body: JSON.stringify(body || {}) })
            .then(function (res) { return res.json().catch(function () { return {}; }).then(function (data) { if (!res.ok || data.success === false) { var err = new Error(data.message || data.error || T('Permintaan gagal.', 'Request failed.')); err.data = data; throw err; } return data; }); });
    }

    /* ===== Modal manager (stack, Escape, focus, lang re-render) ============ */
    var modalStack = [];
    var langRenderers = new Set();
    function createModal(cfg) {
        var overlay = node('div', 'usm-op-overlay');
        overlay.setAttribute('data-usm-nolang', '');
        var modal = node('div', 'usm-op-modal' + (cfg.wide ? ' is-wide' : ''));
        modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.tabIndex = -1;
        var head = node('div', 'usm-op-modal-head');
        var ico = node('div', 'usm-op-head-ico'); ico.appendChild(icon(cfg.icon || 'fa-wand-magic-sparkles'));
        var headText = node('div', 'usm-op-head-text');
        var h3 = node('h3'); var sub = node('p');
        headText.appendChild(h3); headText.appendChild(sub);
        var closeBtn = node('button', 'usm-op-icon-btn'); closeBtn.type = 'button'; closeBtn.setAttribute('aria-label', T('Tutup', 'Close')); closeBtn.appendChild(icon('fa-xmark'));
        head.appendChild(ico); head.appendChild(headText); head.appendChild(closeBtn);
        var body = node('div', 'usm-op-modal-body');
        var foot = node('div', 'usm-op-modal-foot');
        modal.appendChild(head); modal.appendChild(body); modal.appendChild(foot);
        overlay.appendChild(modal);
        var prevFocus = document.activeElement;
        var api = { root: overlay, body: body, foot: foot, close: close };
        function paintHead() { h3.textContent = typeof cfg.title === 'function' ? cfg.title() : cfg.title; sub.textContent = typeof cfg.sub === 'function' ? cfg.sub() : (cfg.sub || ''); sub.hidden = !sub.textContent; }
        function rebuild() { paintHead(); body.innerHTML = ''; foot.innerHTML = ''; cfg.build(body, foot, api); }
        function close() {
            var idx = modalStack.indexOf(api); if (idx !== -1) modalStack.splice(idx, 1);
            langRenderers.delete(rebuild);
            overlay.remove();
            document.body.classList.remove('overflow-hidden');
            if (cfg.onClose) cfg.onClose();
            if (prevFocus && prevFocus.focus) try { prevFocus.focus(); } catch (e) {}
        }
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay && cfg.dismissable !== false) close(); });
        api.rebuild = rebuild;
        document.body.appendChild(overlay);
        document.body.classList.add('overflow-hidden');
        modalStack.push(api);
        langRenderers.add(rebuild);
        rebuild();
        setTimeout(function () { modal.focus(); }, 30);
        return api;
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalStack.length) { e.stopPropagation(); modalStack[modalStack.length - 1].close(); }
    }, true);
    window.addEventListener('usm:langchange', function () { langRenderers.forEach(function (fn) { try { fn(); } catch (e) {} }); });

    function btn(label, cls, onClick, iconName) {
        var b = node('button', 'usm-op-btn ' + (cls || 'usm-op-btn-ghost'));
        b.type = 'button';
        if (iconName) b.appendChild(icon(iconName));
        b.appendChild(node('span', null, label));
        if (onClick) b.addEventListener('click', onClick);
        return b;
    }
    function callout(kind, iconName, html) {
        var c = node('div', 'usm-op-callout is-' + kind);
        c.appendChild(icon(iconName));
        var t = node('div'); t.innerHTML = html; c.appendChild(t);
        return c;
    }
    function field(labelText, control, hint) {
        var f = node('label', 'usm-op-field');
        f.appendChild(node('span', 'usm-op-label', labelText));
        f.appendChild(control);
        if (hint) f.appendChild(node('div', 'usm-op-hint', hint));
        return f;
    }
    function selectEl(options, value) {
        var s = node('select', 'usm-op-select');
        options.forEach(function (o) { var op = node('option', null, o.label); op.value = o.value; if (String(o.value) === String(value)) op.selected = true; s.appendChild(op); });
        return s;
    }

    /* ===== Undo toast ====================================================== */
    var undoStack = null;
    function showUndo(message, onUndo, ms) {
        if (!undoStack) { undoStack = node('div', 'usm-op-undo-stack'); undoStack.setAttribute('data-usm-nolang', ''); document.body.appendChild(undoStack); }
        var duration = ms || 8000;
        var t = node('div', 'usm-op-undo');
        var ico = node('div', 'usm-op-undo-ico'); ico.appendChild(icon('fa-check'));
        var txt = node('div', 'usm-op-undo-text', message);
        var undoBtn = node('button', 'usm-op-undo-btn', T('Buat asal', 'Undo')); undoBtn.type = 'button';
        var closeBtn = node('button', 'usm-op-undo-close'); closeBtn.type = 'button'; closeBtn.setAttribute('aria-label', T('Tutup', 'Close')); closeBtn.textContent = '×';
        var bar = node('div', 'usm-op-undo-bar'); bar.style.animationDuration = duration + 'ms';
        t.appendChild(ico); t.appendChild(txt); t.appendChild(undoBtn); t.appendChild(closeBtn); t.appendChild(bar);
        undoStack.appendChild(t);
        var timer = setTimeout(dismiss, duration);
        function dismiss() { clearTimeout(timer); if (t.isConnected) t.remove(); }
        closeBtn.addEventListener('click', dismiss);
        undoBtn.addEventListener('click', function () { dismiss(); Promise.resolve(onUndo()).catch(function (err) { toast(T('Gagal membuat asal perubahan.', 'Could not undo the changes.')); }); });
    }
    function snapshotFields(ids, fields) {
        var map = {};
        students().forEach(function (r) { if (ids.indexOf(String(r.id)) !== -1 || ids.indexOf(Number(r.id)) !== -1) { var o = { id: Number(r.id) }; fields.forEach(function (f) { o[f] = r[f] == null ? '' : String(r[f]); }); map[String(r.id)] = o; } });
        return Object.keys(map).map(function (k) { return map[k]; });
    }
    function restoreUndo(items, label) {
        return postJSON('bulk_restore_fields', { items: items }).then(function (d) {
            var n = Number(d.restored != null ? d.restored : (items || []).length);
            toast(T(n + ' rekod dipulihkan.', n + ' record' + (n === 1 ? '' : 's') + ' restored.'));
            refreshDirectory();
        });
    }

    /* =====================================================================
       FEATURE 1 — Print-ready card sheets with generated QR
       ===================================================================== */
    var ACCENTS = [
        { name: 'Indigo', a: '#4f46e5', b: '#8b5cf6' }, { name: 'Violet', a: '#7c3aed', b: '#a855f7' },
        { name: 'Ocean', a: '#0284c7', b: '#22d3ee' }, { name: 'Emerald', a: '#059669', b: '#34d399' },
        { name: 'Rose', a: '#e11d48', b: '#fb7185' }, { name: 'Amber', a: '#d97706', b: '#fbbf24' },
        { name: 'Slate', a: '#334155', b: '#64748b' }, { name: 'Magenta', a: '#c026d3', b: '#f472b6' }
    ];
    function qrSvg(payload, color) {
        try {
            if (typeof window.qrcode !== 'function') return null;
            var qr = window.qrcode(0, 'M'); qr.addData(String(payload || '—')); qr.make();
            var count = qr.getModuleCount(); var d = '';
            for (var r = 0; r < count; r++) for (var c = 0; c < count; c++) if (qr.isDark(r, c)) d += 'M' + c + ' ' + r + 'h1v1h-1z';
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + count + ' ' + count + '" shape-rendering="crispEdges"><rect width="' + count + '" height="' + count + '" fill="#fff"/><path d="' + d + '" fill="' + (color || '#0f172a') + '"/></svg>';
        } catch (e) { return null; }
    }
    function qrPayloadFor(record, mode) {
        if (mode === 'serial') return String(record.serialNo || record.matric || record.id);
        if (mode === 'uid') return String(record.usmUid || record.matric || record.id);
        if (mode === 'verify') return 'USM:' + String(record.matric || record.id) + ':' + String(record.serialNo || '');
        return String(record.matric || record.serialNo || record.id);
    }
    function initials(name) { var p = String(name || '').trim().split(/\s+/); return ((p[0] || '')[0] || '' ).concat((p[1] || '')[0] || '').toUpperCase() || '--'; }

    function buildCard(record, m) {
        var accent = ACCENTS[m.accent] || ACCENTS[0];
        var card = node('div', 'usm-op-card' + (m.finish === 'gloss' ? ' is-gloss' : ''));
        card.style.setProperty('--card-a', accent.a); card.style.setProperty('--card-b', accent.b);
        card.style.borderRadius = m.rounding + 'px';
        var top = node('div', 'usm-op-card-top');
        top.appendChild(node('span', 'usm-op-card-brand', 'USMSmart • USM'));
        top.appendChild(node('span', 'usm-op-card-chip'));
        var mid = node('div', 'usm-op-card-mid');
        if (m.showAvatar) { var av = node('div', 'usm-op-card-avatar', initials(record.name)); mid.appendChild(av); }
        var idbox = node('div', 'usm-op-card-id');
        idbox.appendChild(node('div', 'usm-op-card-name', String(record.name || '—')));
        idbox.appendChild(node('div', 'usm-op-card-matric', String(record.matric || '—')));
        if (m.showSchool) idbox.appendChild(node('div', 'usm-op-card-school', String(record.school || '')));
        if (m.showPolicy && record.policy) idbox.appendChild(node('span', 'usm-op-card-policy', String(record.policy)));
        mid.appendChild(idbox);
        var qrBox = node('div', 'usm-op-card-qr');
        qrBox.style.width = m.qrSize + 'px'; qrBox.style.height = m.qrSize + 'px';
        var svg = qrSvg(qrPayloadFor(record, m.qrPayload), '#0f172a');
        if (svg) qrBox.innerHTML = svg; else qrBox.appendChild(node('span', null, String(record.matric || '').slice(0, 8)));
        mid.appendChild(qrBox);
        var bot = node('div', 'usm-op-card-bot');
        bot.appendChild(node('span', 'usm-op-card-uid', 'UID ' + String(record.usmUid || '—')));
        if (m.showExpiry) {
            var exp = node('span', 'usm-op-card-exp' + (cardStatus(record) === 'expired' ? ' is-expired' : ''), String(record.expiryDate || '—').slice(0, 10));
            bot.appendChild(exp);
        }
        card.appendChild(top); card.appendChild(mid); card.appendChild(bot);
        return card;
    }
    function buildPapers(records, m) {
        var frag = document.createDocumentFragment();
        var expanded = [];
        records.forEach(function (r) { for (var i = 0; i < m.copies; i++) expanded.push(r); });
        if (!expanded.length) { var e = node('div', 'usm-op-empty'); e.appendChild(icon('fa-id-card')); e.appendChild(node('p', null, T('Tiada rekod untuk dicetak.', 'No records to print.'))); frag.appendChild(e); return frag; }
        var per = m.perPage; var pages = Math.ceil(expanded.length / per);
        for (var p = 0; p < pages; p++) {
            var paper = node('div', 'usm-op-paper');
            paper.style.gridTemplateColumns = 'repeat(2, 1fr)';
            var label = node('div', 'usm-op-paper-label');
            label.appendChild(node('span', null, 'USMSmart — ' + T('Helaian kad', 'Card sheet') + ' ' + (p + 1) + '/' + pages));
            label.appendChild(node('span', null, T('Sila cetak warna', 'Print in colour')));
            paper.appendChild(label);
            expanded.slice(p * per, p * per + per).forEach(function (r) { paper.appendChild(buildCard(r, m)); });
            frag.appendChild(paper);
        }
        return frag;
    }

    function openPrintStudio(seedRecords) {
        var records = (seedRecords && seedRecords.length) ? seedRecords.slice() : filtered().slice(0, 10);
        var m = { perPage: 10, qrPayload: 'matric', qrSize: 52, accent: 0, finish: 'gloss', showAvatar: true, showSchool: true, showPolicy: true, showExpiry: true, copies: 1, rounding: 12 };
        var overlay = node('div', 'usm-op-overlay usm-op-studio-overlay');
        overlay.setAttribute('data-usm-nolang', '');
        var studio = node('div', 'usm-op-studio');
        var rail = node('div', 'usm-op-rail');
        var stage = node('div', 'usm-op-stage');
        studio.appendChild(rail); studio.appendChild(stage); overlay.appendChild(studio);
        var prevFocus = document.activeElement;

        function renderStage() {
            stage.innerHTML = '';
            var bar = node('div', 'usm-op-stage-bar');
            bar.appendChild(node('span', 'usm-op-stage-title', T('Pratonton cetakan', 'Print preview')));
            var meta = node('span', 'usm-op-stage-meta', records.length + ' ' + T('rekod', 'records') + ' • ' + (records.length * m.copies) + ' ' + T('kad', 'cards'));
            bar.appendChild(meta);
            var scroll = node('div', 'usm-op-stage-scroll');
            scroll.appendChild(buildPapers(records, m));
            stage.appendChild(bar); stage.appendChild(scroll);
        }
        function railField(label, control, valEl) {
            var f = node('div', 'usm-op-rail-field');
            var lab = node('div', 'usm-op-rail-label'); lab.appendChild(node('span', null, label));
            if (valEl) lab.appendChild(valEl);
            f.appendChild(lab); f.appendChild(control); return f;
        }
        function buildRail() {
            rail.innerHTML = '';
            var head = node('div', 'usm-op-rail-head');
            var hi = node('div', 'usm-op-head-ico'); hi.appendChild(icon('fa-id-card'));
            var ht = node('div'); ht.appendChild(node('h3', null, T('Studio Cetakan Kad', 'Card Print Studio')));
            ht.appendChild(node('p', null, T('Reka bentuk & cetak kad ID', 'Design & print ID cards')));
            var closeBtn = node('button', 'usm-op-icon-btn'); closeBtn.type = 'button'; closeBtn.setAttribute('aria-label', T('Tutup', 'Close')); closeBtn.appendChild(icon('fa-xmark'));
            closeBtn.addEventListener('click', closeStudio);
            head.appendChild(hi); head.appendChild(ht); head.appendChild(closeBtn);
            var body = node('div', 'usm-op-rail-body');

            var s1 = node('div', 'usm-op-rail-section'); s1.appendChild(node('div', 'usm-op-rail-title', T('Susun atur', 'Layout')));
            var perSel = selectEl([{ value: 8, label: '8 / ' + T('halaman', 'page') }, { value: 10, label: '10 / ' + T('halaman', 'page') }, { value: 12, label: '12 / ' + T('halaman', 'page') }], m.perPage);
            perSel.addEventListener('change', function () { m.perPage = parseInt(perSel.value, 10); renderStage(); });
            s1.appendChild(railField(T('Kad per halaman', 'Cards per page'), perSel));
            var roundVal = node('span', 'usm-op-range-val', m.rounding + 'px');
            var round = node('input', 'usm-op-range'); round.type = 'range'; round.min = 4; round.max = 20; round.value = m.rounding;
            round.addEventListener('input', function () { m.rounding = parseInt(round.value, 10); roundVal.textContent = m.rounding + 'px'; renderStage(); });
            s1.appendChild(railField(T('Bucu kad', 'Card corners'), round, roundVal));
            var copies = node('div', 'usm-op-stepper');
            var minus = node('button', null, '−'); minus.type = 'button';
            var cval = node('span', 'usm-op-step-val', String(m.copies));
            var plus = node('button', null, '+'); plus.type = 'button';
            minus.addEventListener('click', function () { m.copies = Math.max(1, m.copies - 1); cval.textContent = String(m.copies); renderStage(); });
            plus.addEventListener('click', function () { m.copies = Math.min(6, m.copies + 1); cval.textContent = String(m.copies); renderStage(); });
            copies.appendChild(minus); copies.appendChild(cval); copies.appendChild(plus);
            s1.appendChild(railField(T('Salinan setiap rekod', 'Copies per record'), copies));
            body.appendChild(s1);

            var s2 = node('div', 'usm-op-rail-section'); s2.appendChild(node('div', 'usm-op-rail-title', T('Kod QR', 'QR code')));
            var qrSel = selectEl([{ value: 'matric', label: T('No. matrik', 'Matric no.') }, { value: 'serial', label: T('No. siri kad', 'Card serial') }, { value: 'uid', label: 'USM UID' }, { value: 'verify', label: T('Payload pengesahan', 'Verify payload') }], m.qrPayload);
            qrSel.addEventListener('change', function () { m.qrPayload = qrSel.value; renderStage(); });
            s2.appendChild(railField(T('Kandungan QR', 'QR content'), qrSel));
            var qVal = node('span', 'usm-op-range-val', m.qrSize + 'px');
            var qSize = node('input', 'usm-op-range'); qSize.type = 'range'; qSize.min = 40; qSize.max = 64; qSize.value = m.qrSize;
            qSize.addEventListener('input', function () { m.qrSize = parseInt(qSize.value, 10); qVal.textContent = m.qrSize + 'px'; renderStage(); });
            s2.appendChild(railField(T('Saiz QR', 'QR size'), qSize, qVal));
            body.appendChild(s2);

            var s3 = node('div', 'usm-op-rail-section'); s3.appendChild(node('div', 'usm-op-rail-title', T('Gaya', 'Style')));
            var sw = node('div', 'usm-op-swatches');
            ACCENTS.forEach(function (a, i) {
                var b = node('button', 'usm-op-swatch' + (i === m.accent ? ' is-active' : '')); b.type = 'button';
                b.style.background = 'linear-gradient(135deg,' + a.a + ',' + a.b + ')'; b.title = a.name; b.setAttribute('aria-label', a.name);
                b.addEventListener('click', function () { m.accent = i; sw.querySelectorAll('.usm-op-swatch').forEach(function (x, j) { x.classList.toggle('is-active', j === i); }); renderStage(); });
                sw.appendChild(b);
            });
            s3.appendChild(railField(T('Warna utama', 'Accent colour'), sw));
            var finishSeg = node('div', 'usm-op-seg');
            ['matte', 'gloss'].forEach(function (f) {
                var b = node('button', f === m.finish ? 'is-active' : '', f === 'matte' ? T('Matte', 'Matte') : T('Berkilat', 'Gloss')); b.type = 'button';
                b.addEventListener('click', function () { m.finish = f; finishSeg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-active', x === b); }); renderStage(); });
                finishSeg.appendChild(b);
            });
            s3.appendChild(railField(T('Kemasan', 'Finish'), finishSeg));
            body.appendChild(s3);

            var s4 = node('div', 'usm-op-rail-section'); s4.appendChild(node('div', 'usm-op-rail-title', T('Medan dipaparkan', 'Shown fields')));
            var toggles = node('div', 'usm-op-rail-toggles');
            [['showAvatar', T('Avatar inisial', 'Initials avatar'), 'fa-user'], ['showSchool', T('Pusat pengajian', 'School'), 'fa-school'], ['showPolicy', T('Polisi kad', 'Card policy'), 'fa-shield-halved'], ['showExpiry', T('Tarikh luput', 'Expiry date'), 'fa-calendar-xmark']].forEach(function (pair) {
                var lab = node('label', 'usm-op-switch');
                var inp = node('input'); inp.type = 'checkbox'; inp.checked = m[pair[0]];
                var track = node('span', 'usm-op-track');
                var txt = node('span', 'usm-op-switch-label', pair[1]);
                inp.addEventListener('change', function () { m[pair[0]] = inp.checked; renderStage(); });
                lab.appendChild(inp); lab.appendChild(track); lab.appendChild(txt);
                toggles.appendChild(lab);
            });
            s4.appendChild(toggles); body.appendChild(s4);

            var foot = node('div', 'usm-op-rail-foot');
            var resetBtn = btn(T('Set semula', 'Reset'), 'usm-op-btn-ghost usm-op-btn-sm', function () { m.perPage = 10; m.qrPayload = 'matric'; m.qrSize = 52; m.accent = 0; m.finish = 'gloss'; m.showAvatar = m.showSchool = m.showPolicy = m.showExpiry = true; m.copies = 1; m.rounding = 12; buildRail(); renderStage(); }, 'fa-rotate-left');
            foot.appendChild(resetBtn);
            rail.appendChild(head); rail.appendChild(body); rail.appendChild(foot);
        }
        function doPrint() {
            document.body.classList.add('usm-op-printing');
            var cleanup = function () { document.body.classList.remove('usm-op-printing'); window.removeEventListener('afterprint', cleanup); };
            window.addEventListener('afterprint', cleanup);
            setTimeout(function () { window.print(); setTimeout(cleanup, 500); }, 60);
        }
        function downloadHtml() {
            var tmp = node('div'); tmp.appendChild(buildPapers(records, m));
            var css = '.usm-op-paper{background:#fff;width:794px;padding:28px;margin:0 auto 24px;display:grid;grid-template-columns:repeat(2,1fr);gap:16px;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}.usm-op-paper-label{grid-column:1/-1;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;display:flex;justify-content:space-between}.usm-op-card{position:relative;aspect-ratio:1.586/1;border-radius:12px;overflow:hidden;color:#fff;display:flex;flex-direction:column;background:linear-gradient(135deg,var(--card-a),var(--card-b))}.usm-op-card-top{display:flex;align-items:center;gap:7px;padding:9px 12px 0}.usm-op-card-brand{font-size:8.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.usm-op-card-chip{width:22px;height:16px;border-radius:4px;margin-left:auto;background:linear-gradient(135deg,#fde68a,#f59e0b)}.usm-op-card-mid{display:flex;gap:10px;padding:8px 12px;flex:1}.usm-op-card-avatar{flex:none;width:46px;height:56px;border-radius:8px;background:rgba(255,255,255,.2);display:grid;place-items:center;font-size:18px;font-weight:800}.usm-op-card-id{flex:1;min-width:0}.usm-op-card-name{font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.usm-op-card-matric{font-size:10.5px;font-weight:700;margin-top:2px}.usm-op-card-school{font-size:8.5px;opacity:.85;margin-top:4px}.usm-op-card-policy{display:inline-block;margin-top:5px;font-size:7.5px;font-weight:800;text-transform:uppercase;padding:2px 6px;border-radius:5px;background:rgba(255,255,255,.22)}.usm-op-card-qr{flex:none;align-self:flex-end;background:#fff;border-radius:7px;padding:4px;display:grid;place-items:center}.usm-op-card-qr svg{width:100%;height:100%}.usm-op-card-bot{display:flex;align-items:center;gap:8px;padding:0 12px 9px;font-size:8px}.usm-op-card-uid{font-weight:700}.usm-op-card-exp{margin-left:auto;font-weight:800;padding:2px 7px;border-radius:5px;background:rgba(0,0,0,.22)}@media print{.usm-op-paper{break-after:page;page-break-after:always;margin:0;width:100%}@page{size:A4;margin:8mm}}';
            var html = '<!DOCTYPE html><html lang="' + (window.__usmLang === 'en' ? 'en' : 'ms') + '"><head><meta charset="utf-8"><title>USMSmart — ' + T('Kad ID', 'ID cards') + '</title><style>' + css + '</style></head><body>' + tmp.innerHTML + '</body></html>';
            var blob = new Blob([html], { type: 'text/html;charset=utf-8;' });
            var url = URL.createObjectURL(blob); var a = node('a'); a.href = url; a.download = 'usmsmart-id-cards.html'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
            toast(T('Fail cetakan dimuat turun.', 'Print file downloaded.'));
        }
        function closeStudio() {
            langRenderers.delete(rebuildAll);
            overlay.remove(); document.body.classList.remove('overflow-hidden');
            if (prevFocus && prevFocus.focus) try { prevFocus.focus(); } catch (e) {}
        }
        function rebuildAll() { buildRail(); renderStage(); }
        // stage toolbar actions live in the rail foot area; add Print + Download to stage bar instead
        function addStageActions() {
            var bar = stage.querySelector('.usm-op-stage-bar');
            if (!bar) return;
            var dl = btn(T('Muat turun HTML', 'Download HTML'), 'usm-op-btn-ghost usm-op-btn-sm', downloadHtml, 'fa-file-arrow-down');
            var pr = btn(T('Cetak', 'Print'), 'usm-op-btn-primary usm-op-btn-sm', doPrint, 'fa-print');
            bar.appendChild(dl); bar.appendChild(pr);
        }
        var origRenderStage = renderStage;
        renderStage = function () { origRenderStage(); addStageActions(); };

        document.body.appendChild(overlay); document.body.classList.add('overflow-hidden');
        langRenderers.add(rebuildAll);
        buildRail(); renderStage();
        setTimeout(function () { rail.querySelector('select, button, input') && rail.querySelector('select, button, input').focus(); }, 40);
    }

    /* =====================================================================
       FEATURE 3 — Bulk field editor
       ===================================================================== */
    function schoolOptions() {
        var sel = document.getElementById('master-school-filter'); var out = [];
        if (sel) Array.prototype.forEach.call(sel.options, function (o) { if (o.value) out.push({ value: o.value, label: o.textContent.trim() }); });
        if (!out.length) { var seen = {}; students().forEach(function (r) { var s = String(r.school || '').trim(); if (s && !seen[s]) { seen[s] = 1; out.push({ value: s, label: s }); } }); }
        return out;
    }
    function policyOptions() {
        var opts = (typeof existingPolicyOptions !== 'undefined' && Array.isArray(existingPolicyOptions)) ? existingPolicyOptions : [];
        var out = opts.map(function (p) { return { value: (p && p.name) ? p.name : String(p), label: (p && p.name) ? p.name : String(p) }; });
        if (!out.length) { var seen = {}; students().forEach(function (r) { var p = String(r.policy || '').trim(); if (p && !seen[p]) { seen[p] = 1; out.push({ value: p, label: p }); } }); }
        return out;
    }
    function openBulkEditor(ids) {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh mengedit kelompok.', 'Only admins can bulk edit.')); return; }
        ids = ids.map(String);
        var records = students().filter(function (r) { return ids.indexOf(String(r.id)) !== -1; });
        if (!records.length) { toast(T('Tiada rekod dipilih.', 'No records selected.')); return; }
        var model = { field: 'school', value: '', days: 365 };
        createModal({
            icon: 'fa-pen-to-square', wide: true,
            title: function () { return T('Editor Kelompok', 'Bulk Field Editor'); },
            sub: function () { return records.length + ' ' + T('rekod dipilih akan dikemas kini serentak.', 'selected records will be updated together.'); },
            build: function (body, foot, api) {
                var fieldSel = selectEl([
                    { value: 'school', label: T('Pusat pengajian', 'School') },
                    { value: 'policy', label: T('Polisi kad', 'Card policy') },
                    { value: 'expiry', label: T('Tarikh luput', 'Expiry date') },
                    { value: 'extend', label: T('Lanjutkan tempoh (hari)', 'Extend by (days)') }
                ], model.field);
                var valueHost = node('div');
                function currentValue() {
                    if (model.field === 'extend') return String(model.days);
                    return model.value;
                }
                function buildValue() {
                    valueHost.innerHTML = '';
                    if (model.field === 'school') {
                        var s = selectEl([{ value: '', label: T('— pilih pusat pengajian —', '— choose school —') }].concat(schoolOptions()), model.value);
                        s.addEventListener('change', function () { model.value = s.value; renderPreview(); });
                        valueHost.appendChild(s);
                    } else if (model.field === 'policy') {
                        var p = selectEl([{ value: '', label: T('— pilih polisi —', '— choose policy —') }].concat(policyOptions()), model.value);
                        p.addEventListener('change', function () { model.value = p.value; renderPreview(); });
                        valueHost.appendChild(p);
                    } else if (model.field === 'expiry') {
                        var d = node('input', 'usm-op-input'); d.type = 'date'; d.value = model.value;
                        d.addEventListener('change', function () { model.value = d.value; renderPreview(); });
                        valueHost.appendChild(d);
                    } else {
                        var wrap = node('div', 'usm-op-stepper');
                        var minus = node('button', null, '−'); minus.type = 'button';
                        var val = node('span', 'usm-op-step-val', String(model.days));
                        var plus = node('button', null, '+'); plus.type = 'button';
                        minus.addEventListener('click', function () { model.days = Math.max(1, model.days - 30); val.textContent = String(model.days); renderPreview(); });
                        plus.addEventListener('click', function () { model.days = Math.min(1825, model.days + 30); val.textContent = String(model.days); renderPreview(); });
                        wrap.appendChild(minus); wrap.appendChild(val); wrap.appendChild(plus);
                        valueHost.appendChild(wrap);
                        valueHost.appendChild(node('div', 'usm-op-hint', T('Hari dilanjutkan dari tarikh luput semasa (atau hari ini jika telah luput).', 'Days added from the current expiry (or today if already expired).')));
                    }
                }
                fieldSel.addEventListener('change', function () { model.field = fieldSel.value; model.value = ''; buildValue(); renderPreview(); });
                body.appendChild(field(T('Medan untuk diubah', 'Field to change'), fieldSel));
                body.appendChild(field(T('Nilai baharu', 'New value'), valueHost));
                var previewTitle = node('div', 'usm-op-label', T('Pratonton perubahan', 'Change preview'));
                previewTitle.style.cssText = 'font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--op-text-3);margin-bottom:7px';
                body.appendChild(previewTitle);
                var list = node('div', 'usm-op-preview-list'); body.appendChild(list);
                function newDisplay() {
                    if (model.field === 'extend') return '+' + model.days + ' ' + T('hari', 'days');
                    return model.value || T('(belum dipilih)', '(not set)');
                }
                function oldFor(r) {
                    if (model.field === 'school') return r.school || '—';
                    if (model.field === 'policy') return r.policy || '—';
                    return String(r.expiryDate || '—').slice(0, 10);
                }
                function renderPreview() {
                    list.innerHTML = '';
                    records.slice(0, 12).forEach(function (r) {
                        var row = node('div', 'usm-op-preview-row');
                        row.appendChild(node('span', 'usm-op-pr-name', String(r.name || r.matric || r.id)));
                        row.appendChild(node('span', 'usm-op-pr-old', String(oldFor(r))));
                        row.appendChild(node('span', 'usm-op-pr-arrow', '→'));
                        row.appendChild(node('span', 'usm-op-pr-new', String(newDisplay())));
                        list.appendChild(row);
                    });
                    if (records.length > 12) list.appendChild(node('div', 'usm-op-preview-row', '… ' + (records.length - 12) + ' ' + T('lagi', 'more')));
                }
                buildValue(); renderPreview();
                var note = node('span', 'usm-op-foot-note', T('Boleh dibuat asal selepas disimpan.', 'Can be undone after saving.'));
                foot.appendChild(note);
                foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', api.close));
                var applyBtn = btn(T('Kemas kini', 'Update'), 'usm-op-btn-primary', function () {
                    var val = currentValue();
                    if (model.field !== 'extend' && !val) { toast(T('Sila pilih nilai baharu.', 'Please choose a new value.')); return; }
                    if (model.field === 'expiry' && !/^\d{4}-\d{2}-\d{2}$/.test(val)) { toast(T('Tarikh tidak sah.', 'Invalid date.')); return; }
                    var typeMap = { school: 'set_school', policy: 'set_policy', expiry: 'set_expiry', extend: 'extend_days' };
                    var snapField = model.field === 'school' ? 'school' : model.field === 'policy' ? 'policy' : 'expiryDate';
                    var snap = snapshotFields(ids, [snapField]);
                    applyBtn.disabled = true; applyBtn.innerHTML = '<span class="usm-op-spin"></span>';
                    postJSON('bulk_update_students', { ids: ids.map(Number), update_type: typeMap[model.field], value: val })
                        .then(function (d) {
                            api.close(); refreshDirectory();
                            var n = Number(d.count != null ? d.count : records.length);
                            toast(T(n + ' rekod dikemas kini.', n + ' record' + (n === 1 ? '' : 's') + ' updated.'));
                            showUndo(records.length + ' ' + T('rekod dikemas kini.', 'records updated.'), function () { return restoreUndo(snap); });
                        })
                        .catch(function (err) { applyBtn.disabled = false; applyBtn.innerHTML = ''; applyBtn.appendChild(icon('fa-check')); applyBtn.appendChild(node('span', null, T('Kemas kini', 'Update'))); toast(T('Kemas kini gagal. Semak nilai dan cuba lagi.', 'Update failed. Check the value and try again.')); });
                }, 'fa-check');
                foot.appendChild(applyBtn);
            }
        });
    }

    /* =====================================================================
       FEATURE 2 — Guided duplicate merge
       ===================================================================== */
    var MERGE_FIELDS = [['name', 'Nama', 'Name'], ['matric', 'Matrik', 'Matric'], ['school', 'Pusat pengajian', 'School'], ['usmUid', 'UID kad', 'Card UID'], ['serialNo', 'No. siri kad', 'Card serial'], ['policy', 'Polisi', 'Policy'], ['dateJoined', 'Tarikh daftar', 'Date joined'], ['expiryDate', 'Tarikh luput', 'Expiry date']];
    function openMerge(issueId) {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh menggabungkan rekod.', 'Only admins can merge records.')); return; }
        var issues = (typeof dataQualityIssues !== 'undefined' && Array.isArray(dataQualityIssues)) ? dataQualityIssues : [];
        var matching = issues.filter(function (i) { return Number(i.id) === Number(issueId) && String(i.type || '').indexOf('_duplicate') !== -1 && Array.isArray(i.duplicateMatches); });
        if (!matching.length) { toast(T('Tiada padanan pendua untuk rekod ini.', 'No duplicate matches for this record.')); return; }
        var base = matching[0];
        var current = { id: base.id, name: base.name, matric: base.matric, school: base.school, usmUid: base.usmUid, serialNo: base.serialNo, policy: base.policy, dateJoined: base.dateJoined, expiryDate: base.expiryDate };
        var records = [current];
        matching.forEach(function (i) { (i.duplicateMatches || []).forEach(function (mt) { if (!records.some(function (r) { return Number(r.id) === Number(mt.id); })) records.push(mt); }); });
        if (records.length < 2) { toast(T('Perlukan sekurang-kurangnya dua rekod.', 'Need at least two records.')); return; }
        var model = { keepId: Number(current.id), choices: {} };
        MERGE_FIELDS.forEach(function (f) { model.choices[f[0]] = Number(current.id); });
        // Auto-fill blank survivor fields from the first record that has a value.
        MERGE_FIELDS.forEach(function (f) {
            var keep = records.find(function (r) { return Number(r.id) === model.keepId; });
            if (!String(keep[f[0]] || '').trim()) { var donor = records.find(function (r) { return String(r[f[0]] || '').trim(); }); if (donor) model.choices[f[0]] = Number(donor.id); }
        });
        function valOf(rec, key) { return String(rec[key] == null ? '' : rec[key]).trim(); }
        function mergedValue(key) { var src = records.find(function (r) { return Number(r.id) === Number(model.choices[key]); }) || records[0]; return valOf(src, key); }
        createModal({
            icon: 'fa-code-merge', wide: true, dismissable: false,
            title: function () { return T('Gabungan Rekod Pendua', 'Merge Duplicate Records'); },
            sub: function () { return T('Pilih rekod yang dikekalkan, kemudian pilih nilai menang bagi setiap medan.', 'Pick the record to keep, then choose the winning value for each field.'); },
            build: function (body, foot, api) {
                body.appendChild(callout('warn', 'fa-triangle-exclamation', T('<strong>Rekod yang tidak dikekalkan akan dibuang.</strong> Tindakan ini direkodkan dalam log audit dan tidak boleh dibuat asal secara automatik.', '<strong>Records not kept will be deleted.</strong> This action is written to the audit log and cannot be automatically undone.')));
                var grid = node('div', 'usm-op-merge-grid');
                records.forEach(function (rec) {
                    var col = node('div', 'usm-op-merge-col' + (Number(rec.id) === model.keepId ? ' is-keep' : ''));
                    var ch = node('div', 'usm-op-merge-col-head');
                    var radio = node('input', 'usm-op-radio'); radio.type = 'radio'; radio.name = 'usm-merge-keep'; radio.checked = Number(rec.id) === model.keepId;
                    radio.addEventListener('change', function () { model.keepId = Number(rec.id); api.rebuild(); });
                    var ht = node('div'); ht.appendChild(node('h4', null, valOf(rec, 'name') || ('#' + rec.id)));
                    ht.appendChild(node('p', null, T('Rekod', 'Record') + ' #' + rec.id + (Number(rec.id) === model.keepId ? ' • ' + T('dikekalkan', 'kept') : ' • ' + T('akan dibuang', 'will be removed'))));
                    ch.appendChild(radio); ch.appendChild(ht); col.appendChild(ch);
                    var fields = node('div', 'usm-op-merge-fields');
                    MERGE_FIELDS.forEach(function (f) {
                        var row = node('div', 'usm-op-fieldrow');
                        row.appendChild(node('span', 'usm-op-fr-label', T(f[1], f[2])));
                        row.appendChild(node('span', 'usm-op-fr-val', valOf(rec, f[0]) || '—'));
                        fields.appendChild(row);
                    });
                    col.appendChild(fields); grid.appendChild(col);
                });
                body.appendChild(grid);
                var result = node('div', 'usm-op-merge-result');
                result.appendChild(node('h5', null, T('Nilai gabungan (pilih sumber)', 'Merged values (choose source)')));
                MERGE_FIELDS.forEach(function (f) {
                    var row = node('div', 'usm-op-fieldrow');
                    row.appendChild(node('span', 'usm-op-fr-label', T(f[1], f[2])));
                    var distinct = [];
                    records.forEach(function (r) { var v = valOf(r, f[0]); if (!distinct.some(function (d) { return d.v === v; })) distinct.push({ v: v, id: Number(r.id) }); });
                    if (distinct.length <= 1) {
                        row.appendChild(node('span', 'usm-op-fr-val', mergedValue(f[0]) || '—'));
                    } else {
                        var sel = node('select');
                        distinct.forEach(function (d) { var op = node('option', null, (d.v || '—') + '  (#' + d.id + ')'); op.value = String(d.id); if (Number(model.choices[f[0]]) === d.id) sel.selected = true; sel.appendChild(op); });
                        sel.addEventListener('change', function () { model.choices[f[0]] = Number(sel.value); });
                        row.appendChild(sel); row.classList.add('is-diff');
                    }
                    result.appendChild(row);
                });
                body.appendChild(result);
                var drops = records.filter(function (r) { return Number(r.id) !== model.keepId; });
                var note = node('span', 'usm-op-foot-note', drops.length + ' ' + T('rekod akan dibuang', 'record(s) will be removed'));
                foot.appendChild(note);
                foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', api.close));
                var mergeBtn = btn(T('Gabungkan', 'Merge'), 'usm-op-btn-danger', function () {
                    var overrides = {};
                    MERGE_FIELDS.forEach(function (f) { overrides[f[0]] = mergedValue(f[0]); });
                    mergeBtn.disabled = true; mergeBtn.innerHTML = '<span class="usm-op-spin"></span>';
                    var chain = Promise.resolve();
                    drops.forEach(function (drop, idx) {
                        chain = chain.then(function () { return postJSON('merge_students', { keepId: model.keepId, dropId: Number(drop.id), overrides: idx === 0 ? overrides : {} }); });
                    });
                    chain.then(function () {
                        api.close(); toast(T('Rekod berjaya digabungkan.', 'Records merged successfully.'));
                        refreshDirectory();
                        try { if (typeof window.runDataQualityScan === 'function') window.runDataQualityScan(); } catch (e) {}
                    }).catch(function (err) { mergeBtn.disabled = false; mergeBtn.innerHTML = ''; mergeBtn.appendChild(icon('fa-code-merge')); mergeBtn.appendChild(node('span', null, T('Gabungkan', 'Merge'))); toast(T('Gabungan gagal — nilai mungkin bercanggah dengan rekod lain. Semak UID, matrik dan nombor siri.', 'Merge failed — values may conflict with another record. Check UID, matric and serial number.')); });
                }, 'fa-code-merge');
                foot.appendChild(mergeBtn);
            }
        });
    }
    function injectMergeButton(recordId) {
        var content = document.getElementById('duplicate-preview-content');
        if (!content || content.querySelector('.usm-op-merge-launch')) return;
        if (!isAdmin()) return;
        var wrap = node('div', 'usm-op-merge-launch');
        wrap.style.cssText = 'margin-top:14px;display:flex;justify-content:flex-end';
        var b = btn(T('Gabungkan rekod pendua', 'Merge duplicate records'), 'usm-op-btn-danger usm-op-btn-sm', function () { openMerge(recordId); }, 'fa-code-merge');
        wrap.appendChild(b); content.appendChild(wrap);
    }

    /* =====================================================================
       FEATURE 4 — Renew straight from the expiry digest
       ===================================================================== */
    function expiringRecords() {
        return students().filter(function (r) { var s = cardStatus(r); return s === 'expired' || (typeof s === 'string' && s.indexOf('expiring:') === 0); });
    }
    function openRenew() {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh memperbaharui kad.', 'Only admins can renew cards.')); return; }
        var records = expiringRecords();
        if (!records.length) { toast(T('Tiada kad yang perlu dilanjutkan.', 'No cards need renewing.')); return; }
        var ids = records.map(function (r) { return String(r.id); });
        var model = { days: 365 };
        createModal({
            icon: 'fa-calendar-check',
            title: function () { return T('Perbaharui Kad Luput', 'Renew Expiring Cards'); },
            sub: function () { return records.length + ' ' + T('kad luput / hampir luput akan dilanjutkan.', 'expired / expiring cards will be extended.'); },
            build: function (body, foot, api) {
                body.appendChild(callout('info', 'fa-circle-info', T('Lanjutan dikira dari tarikh luput semasa setiap kad (atau hari ini jika telah luput). Setiap kad disimpan dalam sejarah kad.', 'Extension is counted from each card\'s current expiry (or today if already expired). Every card is saved to card history.')));
                var preset = selectEl([{ value: 30, label: '30 ' + T('hari', 'days') }, { value: 90, label: '90 ' + T('hari', 'days') }, { value: 180, label: '180 ' + T('hari', 'days') }, { value: 365, label: '365 ' + T('hari (1 tahun)', 'days (1 year)') }, { value: 730, label: '730 ' + T('hari (2 tahun)', 'days (2 years)') }], model.days);
                var list = node('div', 'usm-op-preview-list');
                preset.addEventListener('change', function () {
                    model.days = parseInt(preset.value, 10);
                    list.querySelectorAll('.usm-op-pr-new').forEach(function (el) { el.textContent = '+' + model.days + ' ' + T('hari', 'days'); });
                });
                body.appendChild(field(T('Tempoh lanjutan', 'Extension period'), preset));
                records.slice(0, 10).forEach(function (r) {
                    var row = node('div', 'usm-op-preview-row');
                    row.appendChild(node('span', 'usm-op-pr-name', String(r.name || r.matric || r.id)));
                    row.appendChild(node('span', 'usm-op-pr-old', String(r.expiryDate || '—').slice(0, 10)));
                    row.appendChild(node('span', 'usm-op-pr-arrow', '→'));
                    row.appendChild(node('span', 'usm-op-pr-new', '+' + model.days + ' ' + T('hari', 'days')));
                    list.appendChild(row);
                });
                if (records.length > 10) list.appendChild(node('div', 'usm-op-preview-row', '… ' + (records.length - 10) + ' ' + T('lagi', 'more')));
                body.appendChild(list);
                foot.appendChild(node('span', 'usm-op-foot-note', T('Boleh dibuat asal.', 'Can be undone.')));
                foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', api.close));
                var renewBtn = btn(T('Perbaharui', 'Renew'), 'usm-op-btn-primary', function () {
                    var snap = snapshotFields(ids, ['expiryDate']);
                    renewBtn.disabled = true; renewBtn.innerHTML = '<span class="usm-op-spin"></span>';
                    postJSON('bulk_update_students', { ids: ids.map(Number), update_type: 'extend_days', value: String(model.days) })
                        .then(function (d) { api.close(); refreshDirectory(); var n = Number(d.count != null ? d.count : records.length); toast(T(n + ' kad diperbaharui.', n + ' card' + (n === 1 ? '' : 's') + ' renewed.')); showUndo(records.length + ' ' + T('kad diperbaharui.', 'cards renewed.'), function () { return restoreUndo(snap); }); })
                        .catch(function (err) { renewBtn.disabled = false; renewBtn.innerHTML = ''; renewBtn.appendChild(icon('fa-calendar-check')); renewBtn.appendChild(node('span', null, T('Perbaharui', 'Renew'))); toast(T('Pembaharuan gagal. Cuba lagi.', 'Renewal failed. Please try again.')); });
                }, 'fa-calendar-check');
                foot.appendChild(renewBtn);
            }
        });
    }

    /* =====================================================================
       FEATURE 5 — On-demand email digest (weekly auto runs server-side)
       ===================================================================== */
    function openEmailDigest() {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh menghantar ringkasan.', 'Only admins can send the digest.')); return; }
        createModal({
            icon: 'fa-envelope-circle-check',
            title: function () { return T('Hantar Ringkasan Ops', 'Send Operations Digest'); },
            sub: function () { return T('E-mel ringkasan luput & kualiti data kepada semua pentadbir.', 'Emails the expiry & data-quality summary to all admins.'); },
            build: function (body, foot, api) {
                body.appendChild(callout('info', 'fa-circle-info', T('Ringkasan mingguan dihantar automatik pada permintaan pentadbir pertama setiap minggu apabila SMTP dikonfigurasikan. Butang ini menghantar serta-merta.', 'The weekly digest sends automatically on the first admin request each week when SMTP is configured. This button sends it immediately.')));
                var status = node('div', 'usm-op-hint'); status.style.marginTop = '4px';
                body.appendChild(status);
                foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', api.close));
                var sendBtn = btn(T('Hantar sekarang', 'Send now'), 'usm-op-btn-primary', function () {
                    sendBtn.disabled = true; sendBtn.innerHTML = '<span class="usm-op-spin"></span>';
                    status.textContent = T('Menghantar…', 'Sending…');
                    postJSON('send_ops_digest', {})
                        .then(function (d) {
                            api.close();
                            var msg = T('Ringkasan dihantar ke ' + d.sent + ' pentadbir.', 'Digest sent to ' + d.sent + ' admin' + (Number(d.sent) === 1 ? '' : 's') + '.');
                            if (Number(d.failed) > 0) msg += ' ' + T(d.failed + ' gagal.', d.failed + ' failed.');
                            toast(msg);
                        })
                        .catch(function (err) {
                            sendBtn.disabled = false; sendBtn.innerHTML = ''; sendBtn.appendChild(icon('fa-paper-plane')); sendBtn.appendChild(node('span', null, T('Hantar sekarang', 'Send now')));
                            var data = err.data || {};
                            status.textContent = '';
                            toast(Number(data.recipients) === 0
                                ? T('Tiada e-mel pentadbir yang sah. Tetapkan e-mel dalam Pengurusan Akaun dahulu.', 'No valid admin emails. Set them in Account Management first.')
                                : T('Gagal menghantar ringkasan.', 'Failed to send the digest.'));
                        });
                }, 'fa-paper-plane');
                foot.appendChild(sendBtn);
            }
        });
    }

    /* =====================================================================
       FEATURE 6 — Natural-language filters
       ===================================================================== */
    var smartState = { active: false, facets: [] };
    function parseSmartQuery(raw) {
        var q = String(raw || '').toLowerCase().trim();
        if (!q) return null;
        var out = { status: '', category: '', education: '', school: '', keyword: '', facets: [] };
        var matched = false;
        function setStatus(v, labelMs, labelEn) { out.status = v; out.facets.push({ ms: labelMs, en: labelEn }); matched = true; }
        if (/(expired|overdue|luput sudah|tamat tempoh|dah luput|sudah luput)/.test(q)) setStatus('expired', 'Tamat tempoh', 'Expired');
        else if (/(active|aktif|sah)/.test(q)) setStatus('active', 'Aktif', 'Active');
        else if (/(expir|luput|tamat|nak luput|akan luput)/.test(q)) {
            if (/(week|minggu)/.test(q)) setStatus('7', 'Luput ≤7 hari', 'Expiring ≤7 days');
            else if (/(fortnight|dua minggu)/.test(q)) setStatus('14', 'Luput ≤14 hari', 'Expiring ≤14 days');
            else setStatus('30', 'Luput ≤30 hari', 'Expiring ≤30 days');
        }
        if (/(international|antarabangsa)/.test(q)) { out.category = 'International'; out.facets.push({ ms: 'Antarabangsa', en: 'International' }); matched = true; }
        else if (/(local|tempatan)/.test(q)) { out.category = 'Local'; out.facets.push({ ms: 'Tempatan', en: 'Local' }); matched = true; }
        var eduSel = document.getElementById('master-education-filter');
        if (eduSel) {
            var eduOpts = Array.prototype.map.call(eduSel.options, function (o) { return { v: o.value, t: o.textContent.toLowerCase() }; }).filter(function (o) { return o.v; });
            var eduHit = null;
            if (/(postgrad|siswazah|sarjana|master|phd|doctor)/.test(q)) eduHit = eduOpts.find(function (o) { return /postgrad|siswazah|sarjana/.test(o.v.toLowerCase()) || /postgrad|siswazah/.test(o.t); });
            else if (/(undergrad|prasiswazah|degree|ijazah)/.test(q)) eduHit = eduOpts.find(function (o) { return /undergrad|prasiswazah/.test(o.v.toLowerCase()) || /undergrad|prasiswazah/.test(o.t); });
            if (eduHit) { out.education = eduHit.v; out.facets.push({ ms: eduHit.t, en: eduHit.v }); matched = true; }
        }
        var schoolSel = document.getElementById('master-school-filter');
        if (schoolSel) {
            var schoolOpts = Array.prototype.map.call(schoolSel.options, function (o) { return { v: o.value, t: o.textContent.toLowerCase() }; }).filter(function (o) { return o.v; });
            var schoolHit = null;
            if (/(engineering|kejuruteraan)/.test(q)) schoolHit = schoolOpts.find(function (o) { return /kejuruteraan|engineering/.test(o.t) || /kejuruteraan|engineering/.test(o.v.toLowerCase()); });
            else if (/(science|sains)/.test(q)) schoolHit = schoolOpts.find(function (o) { return /sains|science/.test(o.t); });
            else if (/(medical|perubatan)/.test(q)) schoolHit = schoolOpts.find(function (o) { return /perubatan|medical/.test(o.t); });
            else if (/(management|pengurusan)/.test(q)) schoolHit = schoolOpts.find(function (o) { return /pengurusan|management/.test(o.t); });
            if (schoolHit) { out.school = schoolHit.v; out.facets.push({ ms: schoolHit.t, en: schoolHit.t }); matched = true; }
        }
        if (!matched) return null;
        // Residual keyword: strip recognised intent words (whole-word only), keep a possible name.
        var residual = q
            .replace(/\b(students?|pelajar|staff|staf|records?|rekod|cards?|kad|show|senarai|list|all|semua|yang|with|dengan|in|di|expiring|expired|active|aktif|luput|tamat|tempoh|international|antarabangsa|local|tempatan|postgraduate|undergraduate|siswazah|prasiswazah|engineering|kejuruteraan|science|sains|this|itu|ini|the|a|an|of|for|pada|untuk|by|to|bulan|month|week|minggu|fortnight|nak|akan|sudah|dah|overdue|sah|sarjana|master|phd|doctor|degree|ijazah|management|pengurusan|medical|perubatan)\b/g, ' ')
            .replace(/\s+/g, ' ').trim();
        out.keyword = residual;
        return out;
    }
    function applySmart(parsed) {
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; };
        set('master-status-filter', parsed.status);
        set('master-category-filter', parsed.category);
        set('master-education-filter', parsed.education);
        set('master-school-filter', parsed.school);
        set('master-search', parsed.keyword);
        var sort = document.getElementById('master-sort'); if (sort && parsed.status) sort.value = 'expiry_date_asc';
        smartState.active = true; smartState.facets = parsed.facets.slice();
        if (parsed.keyword) smartState.facets.push({ ms: '“' + parsed.keyword + '”', en: '“' + parsed.keyword + '”' });
        try { if (typeof window.filterMasterTable === 'function') window.filterMasterTable(); } catch (e) {}
        renderSmartChips();
    }
    function clearSmart() {
        smartState.active = false; smartState.facets = [];
        ['master-status-filter', 'master-category-filter', 'master-education-filter', 'master-school-filter', 'master-search'].forEach(function (id) { var el = document.getElementById(id); if (el) el.value = ''; });
        try { if (typeof window.filterMasterTable === 'function') window.filterMasterTable(); } catch (e) {}
        renderSmartChips();
    }
    var smartChipsHost = null;
    function renderSmartChips() {
        if (!smartChipsHost) return;
        smartChipsHost.innerHTML = '';
        smartChipsHost.hidden = !smartState.active;
        if (!smartState.active) return;
        smartChipsHost.appendChild(node('span', 'usm-op-smart-label', T('Penapis pintar', 'Smart filter')));
        smartState.facets.forEach(function (f) { smartChipsHost.appendChild(node('span', 'usm-op-chip', T(f.ms, f.en))); });
        var clear = node('span', 'usm-op-chip');
        clear.style.cursor = 'pointer';
        clear.appendChild(icon('fa-xmark'));
        clear.appendChild(node('span', null, T('Kosongkan', 'Clear')));
        clear.addEventListener('click', clearSmart);
        smartChipsHost.appendChild(clear);
    }
    function initSmartFilter() {
        var search = document.getElementById('master-search');
        if (!search || search.dataset.usmSmart) return;
        search.dataset.usmSmart = '1';
        var parent = search.parentElement;
        if (parent) { parent.style.position = 'relative'; search.style.paddingRight = '40px'; }
        var b = node('button', 'usm-op-smart-btn'); b.type = 'button';
        b.title = T('Penapis pintar — cth: “expiring this month in engineering”', 'Smart filter — e.g. “expiring this month in engineering”');
        b.setAttribute('aria-label', b.title);
        b.appendChild(icon('fa-wand-magic-sparkles'));
        function run() {
            var parsed = parseSmartQuery(search.value);
            if (parsed) { applySmart(parsed); toast(T('Penapis pintar digunakan.', 'Smart filter applied.')); }
            else { toast(T('Cuba frasa seperti “expiring this month in engineering” atau “pelajar antarabangsa”.', 'Try a phrase like “expiring this month in engineering” or “international students”.')); }
        }
        b.addEventListener('click', run);
        search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var parsed = parseSmartQuery(search.value); if (parsed) { e.preventDefault(); applySmart(parsed); } } });
        if (parent) parent.appendChild(b);
        smartChipsHost = node('div', 'usm-op-smart-chips'); smartChipsHost.hidden = true;
        smartChipsHost.setAttribute('data-usm-nolang', '');
        var anchor = document.querySelector('.usm-views-bar') || (parent ? parent.parentElement : null);
        if (anchor && anchor.parentElement) anchor.parentElement.insertBefore(smartChipsHost, anchor.nextSibling);
        else if (parent) parent.appendChild(smartChipsHost);
        window.addEventListener('usm:langchange', function () { b.title = T('Penapis pintar — cth: “expiring this month in engineering”', 'Smart filter — e.g. “expiring this month in engineering”'); b.setAttribute('aria-label', b.title); renderSmartChips(); });
    }

    /* =====================================================================
       FEATURE 7 — ccadmin live write: admin setup + push converted UIDs
       The write engine runs server-side after the UID is converted. These two
       surfaces let an admin configure that path (write-only credentials, fixed
       target host, read-only connection test) and push selected cards on demand.
       Nothing here writes until the admin explicitly confirms a push.
       ===================================================================== */
    var CC_FIELDS = [
        { key: 'identifier', ms: 'Lajur pengenal', en: 'Identifier column', req: true, hintMs: 'Matrik / no. staf — kunci rekod', hintEn: 'Matric / staff no — the record key' },
        { key: 'uid', ms: 'Lajur UID', en: 'UID column', req: true, hintMs: 'Kad UID ditulis ke lajur ini', hintEn: 'The card UID is written here' },
        { key: 'serial', ms: 'Lajur no. siri', en: 'Serial column', req: false },
        { key: 'policy', ms: 'Lajur polisi', en: 'Policy column', req: false },
        { key: 'expiry', ms: 'Lajur luput', en: 'Expiry column', req: false }
    ];
    // Mirrors usmsmartUidLittleEndian() on the server so the preview shows exactly
    // what the backend will stage: strip spaces, uppercase, reverse the byte pairs.
    function uidLittleEndian(hex) {
        var clean = String(hex == null ? '' : hex).replace(/\s+/g, '').toUpperCase();
        if (!clean || !/^(?:[0-9A-F]{2})+$/.test(clean)) return '';
        return clean.match(/.{2}/g).reverse().join('');
    }
    function ccValidUid(r) { return uidLittleEndian(r && r.usmUid) !== ''; }

    function ccPermChip(right, ok) {
        var c = node('span', 'usm-op-cc-perm ' + (ok ? 'is-ok' : 'is-bad'));
        c.appendChild(icon(ok ? 'fa-check' : 'fa-xmark'));
        c.appendChild(node('span', null, right.toUpperCase()));
        return c;
    }
    function ccPill(kind, iconName, text) {
        var p = node('span', 'usm-op-cc-pill is-' + kind);
        p.appendChild(icon(iconName)); p.appendChild(node('span', null, text));
        return p;
    }

    function openCcadminSetup() {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh konfigurasi penulisan ccadmin.', 'Only admins can configure ccadmin writes.')); return; }
        var state = null, loading = true, loadError = '', testResult = null, testing = false, saving = false;
        var dlId = 'usm-cc-cols-' + Math.random().toString(36).slice(2, 8);
        var model = { enabled: false, username: '', password: '', table: '', uidMode: 'source', columns: { identifier: '', uid: '', serial: '', policy: '', expiry: '' } };
        var api = createModal({
            icon: 'fa-database', wide: true,
            title: function () { return T('Setup Penulisan ccadmin', 'ccadmin Write Setup'); },
            sub: function () { return T('Tulis kad yang ditukar UID ke pangkalan data kad hidup. Login tulis sahaja — hos sasaran tetap pada pelayan baca.', 'Write converted-UID cards to the live card database. Write login only — the target host stays fixed to the read server.'); },
            build: function (body, foot, a) {
                if (loading) {
                    var lw = node('div', 'usm-op-empty'); lw.appendChild(node('span', 'usm-op-spin usm-op-spin-dark')); lw.appendChild(node('p', null, T('Memuatkan konfigurasi…', 'Loading configuration…'))); body.appendChild(lw);
                    foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                    return;
                }
                if (loadError) {
                    body.appendChild(callout('danger', 'fa-triangle-exclamation', esc(loadError)));
                    foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                    foot.appendChild(btn(T('Cuba lagi', 'Retry'), 'usm-op-btn-primary', function () { loading = true; loadError = ''; a.rebuild(); fetchState(); }, 'fa-rotate-right'));
                    return;
                }
                // Readiness summary — credential-free flags only.
                var stats = node('div', 'usm-op-cc-stats');
                stats.appendChild(ccPill(state.driverAvailable ? 'ok' : 'bad', state.driverAvailable ? 'fa-circle-check' : 'fa-circle-xmark', T('Pemacu sqlsrv', 'sqlsrv driver') + ': ' + (state.driverAvailable ? T('ada', 'present') : T('tiada', 'missing'))));
                stats.appendChild(ccPill(state.readLoginReady ? 'ok' : 'warn', 'fa-server', T('Login baca', 'Read login') + ': ' + (state.readLoginReady ? T('sedia', 'ready') : T('belum', 'not set'))));
                stats.appendChild(ccPill(state.writeLoginFilled ? 'ok' : 'warn', 'fa-key', T('Login tulis', 'Write login') + ': ' + (state.writeLoginFilled ? T('disimpan', 'stored') : T('belum diisi', 'not set'))));
                stats.appendChild(ccPill(state.configWritable ? 'ok' : 'bad', 'fa-file-pen', T('Fail boleh tulis', 'Config writable') + ': ' + (state.configWritable ? T('ya', 'yes') : T('tidak', 'no'))));
                body.appendChild(stats);
                if (state.ready) body.appendChild(callout('success', 'fa-circle-check', T('Laluan tulis sedia. Kad yang dipilih boleh ditulis ke <strong>' + esc(state.table) + '</strong> (' + (state.uidMode === 'converted' ? T('UID ditukar', 'converted UID') : T('UID asal', 'raw UID')) + ').', 'The write path is ready. Selected cards can be written to <strong>' + esc(state.table) + '</strong> (' + (state.uidMode === 'converted' ? 'converted UID' : 'raw UID') + ').')));
                else if (state.enabled) body.appendChild(callout('warn', 'fa-triangle-exclamation', T('Penulisan diaktifkan tetapi belum lengkap. Perlu: ', 'Writes are enabled but incomplete. Still needed: ') + '<strong>' + esc((state.missing || []).join(', ')) + '</strong>'));
                else body.appendChild(callout('info', 'fa-circle-info', T('Penulisan ccadmin dimatikan. Eksport UDA kekal MySQL sahaja sehingga anda aktifkan dan simpan di bawah.', 'ccadmin writes are off. UDA export stays MySQL-only until you enable and save below.')));

                // Enable toggle.
                var sw = node('label', 'usm-op-switch');
                var cb = node('input'); cb.type = 'checkbox'; cb.checked = !!model.enabled;
                var track = node('span', 'usm-op-track');
                var swl = node('span', 'usm-op-switch-label', T('Aktifkan penulisan terus ke ccadmin', 'Enable live writes to ccadmin'));
                cb.addEventListener('change', function () { model.enabled = cb.checked; });
                sw.appendChild(cb); sw.appendChild(track); sw.appendChild(swl);
                var swField = node('div', 'usm-op-field'); swField.appendChild(sw);
                swField.appendChild(node('div', 'usm-op-hint', T('Simpan selepas menukar. Apabila mati, tiada kad ditulis ke pangkalan data kad.', 'Save after changing. When off, no cards are written to the card database.')));
                body.appendChild(swField);

                // Write login (write-only, never pre-filled).
                var loginRow = node('div', 'usm-op-row');
                var u = node('input', 'usm-op-input'); u.type = 'text'; u.autocomplete = 'off'; u.value = model.username;
                u.placeholder = state.writeLoginFilled ? T('(disimpan — biar kosong untuk kekal)', '(stored — leave blank to keep)') : T('nama login tulis', 'write login name');
                u.addEventListener('input', function () { model.username = u.value; });
                var p = node('input', 'usm-op-input'); p.type = 'password'; p.autocomplete = 'new-password'; p.value = model.password;
                p.placeholder = state.writeLoginFilled ? T('(disimpan — biar kosong untuk kekal)', '(stored — leave blank to keep)') : T('kata laluan tulis', 'write password');
                p.addEventListener('input', function () { model.password = p.value; });
                loginRow.appendChild(field(T('Nama login tulis', 'Write username'), u));
                loginRow.appendChild(field(T('Kata laluan tulis', 'Write password'), p));
                body.appendChild(loginRow);
                body.appendChild(node('div', 'usm-op-hint usm-op-cc-note', T('Kelayakan tulis sahaja: tidak pernah dipaparkan semula. Hos / pangkalan data / login baca tidak boleh diedar di sini — sasaran tetap pada pelayan baca yang dikonfigurasikan.', 'Write-only credentials: never shown back. Host / database / read login cannot be changed here — the target stays fixed to the configured read server.')));

                // Target table.
                var tb = node('input', 'usm-op-input'); tb.type = 'text'; tb.value = model.table; tb.placeholder = 'dbo.ccadmin';
                tb.addEventListener('input', function () { model.table = tb.value; });
                body.appendChild(field(T('Jadual sasaran', 'Target table'), tb, T('Jadual kad pada pelayan baca, cth. dbo.ccadmin.', 'The card table on the read server, e.g. dbo.ccadmin.')));

                // UID mode segmented.
                var segWrap = node('div', 'usm-op-field');
                segWrap.appendChild(node('span', 'usm-op-label', T('Bentuk UID ditulis', 'UID form written')));
                var seg = node('div', 'usm-op-seg');
                var uidHint = node('div', 'usm-op-hint');
                function paintUidHint() { uidHint.textContent = model.uidMode === 'converted' ? T('Little-endian: pasangan bait diterbalikkan (A1B2C3D4 → D4C3B2A1) — bentuk yang UDA simpan.', 'Little-endian: byte pairs reversed (A1B2C3D4 → D4C3B2A1) — the form UDA stages.') : T('UID mentah seperti dicetak pada kad (big-endian).', 'The raw UID as printed on the card (big-endian).'); }
                [{ v: 'source', ms: 'UID asal', en: 'Raw UID' }, { v: 'converted', ms: 'Ditukar', en: 'Converted' }].forEach(function (o) {
                    var b = node('button', null, T(o.ms, o.en)); b.type = 'button';
                    if (model.uidMode === o.v) b.classList.add('is-active');
                    b.addEventListener('click', function () { model.uidMode = o.v; Array.prototype.forEach.call(seg.children, function (c) { c.classList.remove('is-active'); }); b.classList.add('is-active'); paintUidHint(); });
                    seg.appendChild(b);
                });
                segWrap.appendChild(seg); paintUidHint(); segWrap.appendChild(uidHint);
                body.appendChild(segWrap);

                // Column mapping with a discover helper.
                var mapHead = node('div', 'usm-op-cc-maphead');
                mapHead.appendChild(node('span', 'usm-op-label', T('Pemetaan lajur', 'Column mapping')));
                var disc = btn(T('Temui lajur (ujian baca)', 'Discover columns (read-only test)'), 'usm-op-btn-subtle usm-op-btn-sm', function () { runTest(); }, 'fa-magnifying-glass');
                mapHead.appendChild(disc);
                body.appendChild(mapHead);
                if (testResult && testResult.success && (testResult.columns || []).length) {
                    var dl = node('datalist'); dl.id = dlId;
                    testResult.columns.forEach(function (c) { var o = node('option'); o.value = c; dl.appendChild(o); });
                    body.appendChild(dl);
                }
                var map = node('div', 'usm-op-cc-map');
                CC_FIELDS.forEach(function (f) {
                    var inp = node('input', 'usm-op-input'); inp.type = 'text'; inp.value = model.columns[f.key] || '';
                    inp.placeholder = f.req ? T('wajib', 'required') : T('pilihan', 'optional');
                    if (testResult && testResult.success && (testResult.columns || []).length) inp.setAttribute('list', dlId);
                    inp.addEventListener('input', function () { model.columns[f.key] = inp.value; });
                    var lbl = T(f.ms, f.en) + (f.req ? ' *' : '');
                    var wrap = field(lbl, inp, f.hintMs ? T(f.hintMs, f.hintEn) : null);
                    map.appendChild(wrap);
                });
                body.appendChild(map);

                // Test result panel.
                if (testing) {
                    body.appendChild(callout('info', 'fa-circle-notch', T('Menguji sambungan (baca sahaja)…', 'Testing connection (read-only)…')));
                } else if (testResult) {
                    var panel = node('div', 'usm-op-cc-test');
                    if (testResult.success) {
                        var th = node('div', 'usm-op-cc-test-head');
                        th.appendChild(icon('fa-circle-check'));
                        th.appendChild(node('span', null, T('Sambungan berjaya — login ' + testResult.login + ' · ' + testResult.table, 'Connected — ' + testResult.login + ' login · ' + testResult.table)));
                        panel.appendChild(th);
                        var perms = node('div', 'usm-op-cc-perms');
                        var pm = testResult.permissions || {};
                        ['select', 'insert', 'update'].forEach(function (r) { perms.appendChild(ccPermChip(r, !!pm[r])); });
                        panel.appendChild(perms);
                        if ((testResult.planMissing || []).length) panel.appendChild(node('div', 'usm-op-hint usm-op-cc-warn', T('Belum dipetakan: ' + testResult.planMissing.join(', ') + '. Isi medan wajib di atas.', 'Not mapped yet: ' + testResult.planMissing.join(', ') + '. Fill the required fields above.')));
                        var colLabel = node('div', 'usm-op-label', T('Lajur sebenar jadual (' + (testResult.columns || []).length + ')', 'Actual table columns (' + (testResult.columns || []).length + ')'));
                        colLabel.style.marginTop = '10px'; panel.appendChild(colLabel);
                        var chips = node('div', 'usm-op-cc-cols');
                        (testResult.columns || []).forEach(function (c) { chips.appendChild(node('span', 'usm-op-chip', c)); });
                        panel.appendChild(chips);
                    } else {
                        var failMsg = (!state.writeLoginFilled && !state.readLoginReady)
                            ? T('Tiada login untuk diuji. Isi login tulis dahulu.', 'No login to test. Fill in the write login first.')
                            : T('Sambungan gagal. Semak server, login tulis dan kebenaran (GRANT SELECT, INSERT, UPDATE).', 'Connection failed. Check the server, write login and permissions (GRANT SELECT, INSERT, UPDATE).');
                        panel.appendChild(callout('danger', 'fa-triangle-exclamation', failMsg));
                    }
                    body.appendChild(panel);
                }

                // Footer.
                foot.appendChild(node('span', 'usm-op-foot-note', T('Ujian tidak menulis apa-apa. Simpan menyimpan konfigurasi sahaja.', 'The test writes nothing. Save stores configuration only.')));
                foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                var testBtn = btn(T('Uji sambungan', 'Test connection'), 'usm-op-btn-ghost', function () { runTest(); }, 'fa-plug');
                if (testing) { testBtn.disabled = true; testBtn.innerHTML = '<span class="usm-op-spin usm-op-spin-dark"></span>'; }
                foot.appendChild(testBtn);
                var saveBtn = btn(T('Simpan konfigurasi', 'Save configuration'), 'usm-op-btn-primary', function () { doSave(); }, 'fa-floppy-disk');
                if (saving) { saveBtn.disabled = true; saveBtn.innerHTML = '<span class="usm-op-spin"></span>'; }
                foot.appendChild(saveBtn);

                function runTest() {
                    if (testing) return;
                    testing = true; testResult = null; a.rebuild();
                    postJSON('sysweb_write_test', {}).then(function (d) { testing = false; testResult = d; a.rebuild(); })
                        .catch(function (err) { testing = false; testResult = (err && err.data) ? err.data : { success: false, message: T('Sambungan gagal. Semak server, login dan kebenaran.', 'Connection failed. Check server, login and permissions.') }; a.rebuild(); });
                }
                function doSave() {
                    if (saving) return;
                    if (model.enabled && !String(model.columns.identifier).trim()) { toast(T('Lajur pengenal wajib diisi sebelum aktifkan.', 'The identifier column is required before enabling.')); return; }
                    if (model.enabled && !String(model.columns.uid).trim()) { toast(T('Lajur UID wajib diisi sebelum aktifkan.', 'The UID column is required before enabling.')); return; }
                    saving = true; a.rebuild();
                    postJSON('sysweb_write_save', {
                        write_enabled: model.enabled, write_username: model.username, write_password: model.password,
                        write_table: model.table, write_uid: model.uidMode, card_columns: model.columns
                    }).then(function (d) {
                        saving = false; state = d.state; model.username = ''; model.password = '';
                        model.enabled = state.enabled; model.table = state.table || ''; model.uidMode = state.uidMode || 'source';
                        CC_FIELDS.forEach(function (f) { model.columns[f.key] = (state.columnNames && state.columnNames[f.key]) || ''; });
                        testResult = null; a.rebuild();
                        toast(T('Konfigurasi penulisan ccadmin disimpan.', 'ccadmin write configuration saved.'));
                    }).catch(function (err) {
                        saving = false; a.rebuild();
                        toast((err && err.message) ? err.message : T('Gagal menyimpan konfigurasi.', 'Failed to save configuration.'));
                    });
                }
            }
        });
        function fetchState() {
            postJSON('sysweb_write_state', {}).then(function (d) {
                state = d.state; loading = false;
                model.enabled = !!state.enabled; model.table = state.table || ''; model.uidMode = state.uidMode || 'source';
                CC_FIELDS.forEach(function (f) { model.columns[f.key] = (state.columnNames && state.columnNames[f.key]) || ''; });
                api.rebuild();
            }).catch(function (err) { loading = false; loadError = (err && err.message) ? err.message : T('Gagal memuatkan konfigurasi.', 'Failed to load configuration.'); api.rebuild(); });
        }
        fetchState();
    }

    function openCcadminPush() {
        if (!isAdmin()) { toast(T('Hanya pentadbir boleh menulis ke ccadmin.', 'Only admins can write to ccadmin.')); return; }
        var records = selectedRecords();
        if (!records.length) { toast(T('Pilih kad dahulu dalam direktori.', 'Select cards in the directory first.')); return; }
        var state = null, loading = true, loadError = '', pushing = false, pushResult = null;
        var valid = records.filter(ccValidUid);
        var skipped = records.length - valid.length;
        var api = createModal({
            icon: 'fa-cloud-arrow-up', wide: true,
            title: function () { return T('Tulis Kad ke ccadmin', 'Write Cards to ccadmin'); },
            sub: function () { return T(valid.length + ' kad dengan UID sah akan ditulis ke pangkalan data kad hidup.', valid.length + ' cards with a valid UID will be written to the live card database.'); },
            build: function (body, foot, a) {
                if (loading) {
                    var lw = node('div', 'usm-op-empty'); lw.appendChild(node('span', 'usm-op-spin usm-op-spin-dark')); lw.appendChild(node('p', null, T('Memuatkan status penulisan…', 'Loading write status…'))); body.appendChild(lw);
                    foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                    return;
                }
                if (loadError) {
                    body.appendChild(callout('danger', 'fa-triangle-exclamation', esc(loadError)));
                    foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                    foot.appendChild(btn(T('Cuba lagi', 'Retry'), 'usm-op-btn-primary', function () { loading = true; loadError = ''; a.rebuild(); fetchState(); }, 'fa-rotate-right'));
                    return;
                }
                var mode = state.uidMode === 'converted' ? 'converted' : 'source';
                // Target + mode summary.
                var stats = node('div', 'usm-op-cc-stats');
                stats.appendChild(ccPill(state.ready ? 'ok' : 'warn', 'fa-database', T('Sasaran', 'Target') + ': ' + (state.table || T('(belum ditetapkan)', '(unset)'))));
                stats.appendChild(ccPill('info', 'fa-shuffle', T('UID ditulis', 'UID written') + ': ' + (mode === 'converted' ? T('ditukar (little-endian)', 'converted (little-endian)') : T('asal (raw)', 'raw (source)'))));
                stats.appendChild(ccPill(valid.length ? 'ok' : 'bad', 'fa-id-card', valid.length + ' ' + T('kad sah', 'valid cards') + (skipped ? ' · ' + skipped + ' ' + T('dilangkau', 'skipped') : '')));
                body.appendChild(stats);

                if (!state.ready) {
                    body.appendChild(callout('warn', 'fa-triangle-exclamation', T('Laluan tulis belum sedia. Perlu: ', 'The write path is not ready. Still needed: ') + '<strong>' + esc((state.missing || []).join(', ')) + '</strong>. ' + T('Konfigurasikan dahulu sebelum menulis.', 'Configure it before writing.')));
                }

                // Conversion preview: source → little-endian, written form emphasised.
                var pvTitle = node('div', 'usm-op-label', T('Pratonton penukaran UID', 'UID conversion preview'));
                pvTitle.style.cssText = 'font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--op-text-3);margin:4px 0 7px';
                body.appendChild(pvTitle);
                var list = node('div', 'usm-op-preview-list usm-op-cc-preview');
                if (!valid.length) {
                    var em = node('div', 'usm-op-empty'); em.appendChild(icon('fa-id-card')); em.appendChild(node('p', null, T('Tiada kad dipilih dengan UID hexadecimal yang sah.', 'No selected cards have a valid hexadecimal UID.'))); list.appendChild(em);
                } else {
                    valid.slice(0, 50).forEach(function (r) {
                        var src = String(r.usmUid || '').replace(/\s+/g, '').toUpperCase();
                        var conv = uidLittleEndian(r.usmUid);
                        var row = node('div', 'usm-op-preview-row usm-op-cc-uidrow');
                        row.appendChild(node('span', 'usm-op-pr-name', String(r.name || r.matric || r.id)));
                        row.appendChild(node('span', 'usm-op-cc-src' + (mode === 'source' ? ' is-written' : ''), src));
                        row.appendChild(node('span', 'usm-op-pr-arrow', '→'));
                        row.appendChild(node('span', 'usm-op-cc-conv' + (mode === 'converted' ? ' is-written' : ''), conv));
                        list.appendChild(row);
                    });
                    if (valid.length > 50) list.appendChild(node('div', 'usm-op-preview-row', '… ' + (valid.length - 50) + ' ' + T('lagi', 'more')));
                }
                body.appendChild(list);
                if (skipped) body.appendChild(node('div', 'usm-op-hint', T(skipped + ' rekod dilangkau kerana tiada UID hexadecimal yang sah.', skipped + ' record' + (skipped === 1 ? '' : 's') + ' skipped — no valid hexadecimal UID.')));

                // Push result.
                if (pushResult) {
                    var res = pushResult.result || {};
                    if (res.status === 'written') body.appendChild(callout('success', 'fa-circle-check', T('<strong>' + res.written + '/' + res.total + '</strong> kad ditulis ke ' + esc(res.table) + ' (' + (res.uid === 'converted' ? T('UID ditukar', 'converted UID') : T('UID asal', 'raw UID')) + ').', '<strong>' + res.written + '/' + res.total + '</strong> cards written to ' + esc(res.table) + ' (' + (res.uid === 'converted' ? 'converted UID' : 'raw UID') + ').')));
                    else if (res.status === 'not_configured') {
                        var miss = res.missing || [];
                        var ncMsg = (miss.length === 1 && miss[0] === 'write_enabled')
                            ? T('Penulisan ccadmin dimatikan. Aktifkan dalam Setup Penulisan ccadmin.', 'ccadmin writes are disabled. Enable them in ccadmin Write Setup.')
                            : T('Penulisan ccadmin belum lengkap. Perlu: ', 'ccadmin writes are incomplete. Still needed: ') + '<strong>' + esc(miss.join(', ')) + '</strong>';
                        body.appendChild(callout('warn', 'fa-triangle-exclamation', ncMsg));
                    } else body.appendChild(callout('danger', 'fa-triangle-exclamation', T('Penulisan ke ccadmin gagal dan telah dibatalkan. Eksport MySQL kekal; semak sambungan dan kebenaran tulis (GRANT SELECT, INSERT, UPDATE), kemudian cuba lagi.', 'The ccadmin write failed and was rolled back. The MySQL export is intact; check the connection and write permissions (GRANT SELECT, INSERT, UPDATE), then retry.')));
                }

                // Footer.
                var res0 = pushResult && pushResult.result;
                var succeeded = res0 && res0.status === 'written';
                foot.appendChild(node('span', 'usm-op-foot-note', succeeded ? T('Penulisan selesai.', 'Write complete.') : T('Menulis terus ke pangkalan data kad hidup. Tidak boleh dibuat asal.', 'Writes directly to the live card database. Cannot be undone.')));
                if (succeeded) {
                    foot.appendChild(btn(T('Selesai', 'Done'), 'usm-op-btn-primary', a.close, 'fa-check'));
                } else {
                    foot.appendChild(btn(T('Batal', 'Cancel'), 'usm-op-btn-ghost', a.close));
                    if (!state.ready || !valid.length || (res0 && res0.status === 'not_configured')) {
                        foot.appendChild(btn(T('Konfigurasikan', 'Configure'), 'usm-op-btn-primary', function () { a.close(); openCcadminSetup(); }, 'fa-database'));
                    } else {
                        var writeBtn = btn(T('Tulis ' + valid.length + ' kad', 'Write ' + valid.length + ' card' + (valid.length === 1 ? '' : 's')), 'usm-op-btn-primary', function () { doPush(); }, 'fa-cloud-arrow-up');
                        if (pushing) { writeBtn.disabled = true; writeBtn.innerHTML = '<span class="usm-op-spin"></span>'; }
                        foot.appendChild(writeBtn);
                    }
                }
                function doPush() {
                    if (pushing) return;
                    if (valid.length > 500) { toast(T('Had maksimum 500 kad pada satu masa.', 'Maximum 500 cards at a time.')); return; }
                    pushing = true; pushResult = null; a.rebuild();
                    postJSON('push_cards_ccadmin', { ids: valid.map(function (r) { return Number(r.id); }) })
                        .then(function (d) {
                            pushing = false; pushResult = d; a.rebuild();
                            var res = d.result || {};
                            if (res.status === 'written') { toast(T(res.written + ' kad ditulis ke ccadmin.', res.written + ' card' + (Number(res.written) === 1 ? '' : 's') + ' written to ccadmin.')); }
                            else if (res.status === 'not_configured') { toast(T('Penulisan ccadmin belum dikonfigurasikan.', 'ccadmin writes are not configured.')); }
                            else { toast(T('Penulisan ke ccadmin gagal dan dibatalkan.', 'The ccadmin write failed and was rolled back.')); }
                        })
                        .catch(function (err) { pushing = false; a.rebuild(); toast(T('Gagal menulis ke ccadmin. Semak sambungan dan cuba lagi.', 'Failed to write to ccadmin. Check the connection and try again.')); });
                }
            }
        });
        function fetchState() {
            postJSON('sysweb_write_state', {}).then(function (d) { state = d.state; loading = false; api.rebuild(); })
                .catch(function (err) { loading = false; loadError = (err && err.message) ? err.message : T('Gagal memuatkan status penulisan.', 'Failed to load write status.'); api.rebuild(); });
        }
        fetchState();
    }

    /* ===== Wiring: bulk bar, digest strip, palette ========================= */
    function injectBulkButtons() {
        var bar = document.getElementById('master-bulk-bar');
        if (!bar || bar.querySelector('.usm-op-bulk-injected')) return;
        var group = bar.querySelector('div:last-child') || bar;
        var edit = node('button', 'usm-op-bulk-btn usm-op-bulk-injected'); edit.type = 'button';
        edit.appendChild(icon('fa-pen-to-square')); edit.appendChild(node('span', null, T('Edit medan', 'Edit fields')));
        edit.addEventListener('click', function () { openBulkEditor(selectedRecords().map(function (r) { return r.id; })); });
        var print = node('button', 'usm-op-bulk-btn usm-op-bulk-injected'); print.type = 'button';
        print.appendChild(icon('fa-id-card')); print.appendChild(node('span', null, T('Cetak kad', 'Print cards')));
        print.addEventListener('click', function () { openPrintStudio(selectedRecords()); });
        group.appendChild(edit); group.appendChild(print);
        var ccadmin = null;
        if (isAdmin()) {
            ccadmin = node('button', 'usm-op-bulk-btn usm-op-bulk-injected usm-op-cc-bulk'); ccadmin.type = 'button';
            ccadmin.appendChild(icon('fa-cloud-arrow-up')); ccadmin.appendChild(node('span', null, T('Tulis ke ccadmin', 'Write to ccadmin')));
            ccadmin.addEventListener('click', function () { openCcadminPush(); });
            group.appendChild(ccadmin);
        }
        window.addEventListener('usm:langchange', function () {
            edit.querySelector('span').textContent = T('Edit medan', 'Edit fields');
            print.querySelector('span').textContent = T('Cetak kad', 'Print cards');
            if (ccadmin) ccadmin.querySelector('span').textContent = T('Tulis ke ccadmin', 'Write to ccadmin');
        });
    }
    function injectDigestActions() {
        var strip = document.querySelector('.usm-expiry-digest');
        if (!strip || strip.querySelector('.usm-op-digest-actions')) return false;
        var actions = node('div', 'usm-op-digest-actions');
        actions.setAttribute('data-usm-nolang', '');
        if (isAdmin()) {
            var renew = node('button', 'usm-op-digest-btn'); renew.type = 'button';
            renew.appendChild(icon('fa-calendar-check')); renew.appendChild(node('span', null, T('Perbaharui', 'Renew')));
            renew.addEventListener('click', openRenew);
            var email = node('button', 'usm-op-digest-btn is-ghost'); email.type = 'button';
            email.appendChild(icon('fa-envelope')); email.appendChild(node('span', null, T('E-mel ringkasan', 'Email summary')));
            email.addEventListener('click', openEmailDigest);
            actions.appendChild(renew); actions.appendChild(email);
            window.addEventListener('usm:langchange', function () {
                renew.querySelector('span').textContent = T('Perbaharui', 'Renew');
                email.querySelector('span').textContent = T('E-mel ringkasan', 'Email summary');
            });
        }
        var closeBtn = strip.querySelector('.usm-expiry-dismiss');
        if (closeBtn) strip.insertBefore(actions, closeBtn); else strip.appendChild(actions);
        return true;
    }
    function wrapPalette() {
        if (typeof window.usmPaletteCommands !== 'function' || window.usmPaletteCommands.__usmOps) return;
        var orig = window.usmPaletteCommands;
        var wrapped = function () {
            var items = orig.apply(this, arguments);
            items.push({ group: T('Tindakan', 'Actions'), label: T('Studio Cetakan Kad', 'Card Print Studio'), meta: T('Cetak kad ID dengan QR', 'Print ID cards with QR'), icon: 'fa-id-card', keywords: 'cetak kad print card qr studio', run: function () { openPrintStudio(selectedRecords()); } });
            items.push({ group: T('Tindakan', 'Actions'), label: T('Penapis Pintar', 'Smart Filter'), meta: T('Tapis dengan bahasa mudah', 'Filter with plain language'), icon: 'fa-wand-magic-sparkles', keywords: 'pintar smart filter natural bahasa', run: function () { var s = document.getElementById('master-search'); if (s) { s.focus(); } initSmartFilter(); } });
            if (isAdmin()) {
                items.push({ group: T('Tindakan', 'Actions'), label: T('Perbaharui Kad Luput', 'Renew Expiring Cards'), meta: T('Lanjutkan kad hampir luput', 'Extend expiring cards'), icon: 'fa-calendar-check', keywords: 'perbaharui renew luput expiry lanjut', run: openRenew });
                items.push({ group: T('Tindakan', 'Actions'), label: T('Hantar Ringkasan Ops', 'Send Operations Digest'), meta: T('E-mel ringkasan kepada pentadbir', 'Email summary to admins'), icon: 'fa-envelope', keywords: 'emel email ringkasan digest hantar', run: openEmailDigest });
                items.push({ group: T('Tindakan', 'Actions'), label: T('Setup Penulisan ccadmin', 'ccadmin Write Setup'), meta: T('Konfigurasi penulisan kad hidup', 'Configure live card writes'), icon: 'fa-database', keywords: 'ccadmin sysweb tulis write database konfigurasi setup lajur column', run: openCcadminSetup });
                items.push({ group: T('Tindakan', 'Actions'), label: T('Tulis Kad ke ccadmin', 'Write Cards to ccadmin'), meta: T('Tulis kad dipilih yang ditukar UID', 'Push selected converted-UID cards'), icon: 'fa-cloud-arrow-up', keywords: 'ccadmin tulis write push kad card uid hantar', run: openCcadminPush });
            }
            return items;
        };
        wrapped.__usmOps = true;
        window.usmPaletteCommands = wrapped;
    }
    function wrapDuplicatePreview() {
        if (typeof window.openDuplicatePreview !== 'function' || window.openDuplicatePreview.__usmOps) return;
        var orig = window.openDuplicatePreview;
        var wrapped = function (recordId) { var r = orig.apply(this, arguments); try { injectMergeButton(recordId); } catch (e) {} return r; };
        wrapped.__usmOps = true;
        window.openDuplicatePreview = wrapped;
    }

    /* ===== Boot ============================================================ */
    function boot() {
        injectBulkButtons();
        wrapPalette();
        wrapDuplicatePreview();
        initSmartFilter();
        if (!injectDigestActions()) {
            var tries = 0;
            var obs = new MutationObserver(function () {
                if (injectDigestActions() || ++tries > 40) obs.disconnect();
            });
            obs.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { obs.disconnect(); }, 12000);
        }
        window.addEventListener('load', function () { injectBulkButtons(); injectDigestActions(); initSmartFilter(); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    /* Expose a tiny API for console / future hooks. */
    window.__usmOps = { openPrintStudio: openPrintStudio, openBulkEditor: openBulkEditor, openMerge: openMerge, openRenew: openRenew, openEmailDigest: openEmailDigest, parseSmartQuery: parseSmartQuery, openCcadminSetup: openCcadminSetup, openCcadminPush: openCcadminPush, uidLittleEndian: uidLittleEndian };
})();