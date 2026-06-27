/**
 * Dokuments Hub Admin — dashboard interactions.
 *
 * - Copy-URL button: copies the full product URL to clipboard.
 * - Quick-edit: inline editing of title + short description, saved via AJAX;
 *   on save the product re-renders server-side and Google is pinged.
 */
(function () {
    'use strict';

    var csrf = window.DK_CSRF || '';

    // ----- Copy URL button -----
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.dk-copy-btn');
        if (!btn) return;

        var url = btn.getAttribute('data-url') || '';
        var done = function () {
            var orig = btn.textContent;
            btn.textContent = '✓';
            btn.classList.add('dk-copy-ok');
            setTimeout(function () {
                btn.textContent = orig;
                btn.classList.remove('dk-copy-ok');
            }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(function () {
                fallbackCopy(url); done();
            });
        } else {
            fallbackCopy(url); done();
        }
    });

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    // ----- Quick edit -----
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.dk-quickedit-btn');
        if (!btn) return;
        var row = btn.closest('tr');
        if (!row || row.classList.contains('dk-quick-active')) return;

        openQuickEdit(row);
    });

    function openQuickEdit(row) {
        row.classList.add('dk-quick-active');
        var id = row.getAttribute('data-id');
        var targets = row.querySelectorAll('.dk-quick-target');

        targets.forEach(function (cell) {
            var field = cell.getAttribute('data-field');
            var cur = cell.querySelector('.dk-row-title, .dk-row-short');
            var val = cur ? cur.textContent.trim() : '';
            if (val === '—') val = '';

            var input = document.createElement(field === 'short_description' ? 'textarea' : 'input');
            input.className = 'dk-quick-input';
            input.setAttribute('data-field', field);
            input.value = val;
            if (field === 'short_description') input.rows = 2;

            cell.innerHTML = '';
            cell.appendChild(input);
        });

        // Action buttons row (save / cancel).
        var actTd = row.querySelector('.col-actions');
        var origActions = actTd.innerHTML;
        actTd.innerHTML =
            '<button type="button" class="dk-icon-btn dk-qe-save" title="Speichern + Google anpingen" style="color:#15803d">💾</button>' +
            '<button type="button" class="dk-icon-btn dk-qe-cancel" title="Abbrechen">✕</button>';

        actTd.querySelector('.dk-qe-cancel').addEventListener('click', function () {
            cancelQuickEdit(row, origActions);
        });
        actTd.querySelector('.dk-qe-save').addEventListener('click', function () {
            saveQuickEdit(row, id, actTd, origActions);
        });

        // Focus the title input.
        var titleInput = row.querySelector('.dk-quick-input[data-field="title"]');
        if (titleInput) titleInput.focus();
    }

    function saveQuickEdit(row, id, actTd, origActions) {
        var titleInput = row.querySelector('.dk-quick-input[data-field="title"]');
        var shortInput = row.querySelector('.dk-quick-input[data-field="short_description"]');
        var title = titleInput ? titleInput.value.trim() : '';
        var short = shortInput ? shortInput.value.trim() : '';

        if (!title) {
            alert('Der Titel darf nicht leer sein.');
            if (titleInput) titleInput.focus();
            return;
        }

        var saveBtn = actTd.querySelector('.dk-qe-save');
        saveBtn.textContent = '⏳';
        saveBtn.disabled = true;

        var body = new URLSearchParams();
        body.append('csrf_token', csrf);
        body.append('id', id);
        body.append('title', title);
        body.append('short_description', short);

        fetch('dashboard.php?ajax=quick_edit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                // Restore view with new values.
                var titleCell = row.querySelector('.dk-quick-target[data-field="title"]');
                var shortCell = row.querySelector('.dk-quick-target[data-field="short_description"]');
                titleCell.innerHTML = '<strong class="dk-row-title">' + escapeHtml(data.title) + '</strong>';
                shortCell.innerHTML = '<span class="dk-row-short">' + escapeHtml(data.short_description || '—') + '</span>';

                var upd = row.querySelector('.dk-updated');
                if (upd && data.updated_at) upd.textContent = data.updated_at;

                actTd.innerHTML = origActions;
                row.classList.remove('dk-quick-active');
                row.classList.add('dk-flash-ok');
                setTimeout(function () { row.classList.remove('dk-flash-ok'); }, 1200);

                var note = data.pinged ? 'Gespeichert + Google angepingt.' : 'Gespeichert (Google-Ping fehlgeschlagen).';
                flashToast(note, data.pinged ? 'ok' : 'warn');
            } else {
                saveBtn.textContent = '💾';
                saveBtn.disabled = false;
                alert('Fehler: ' + (data.error || 'unbekannt'));
            }
        })
        .catch(function () {
            saveBtn.textContent = '💾';
            saveBtn.disabled = false;
            alert('Netzwerkfehler beim Speichern.');
        });
    }

    function cancelQuickEdit(row, origActions) {
        // Reload the row's display values from the server would be cleanest,
        // but simplest: just reload the page.
        window.location.reload();
    }

    // ----- Helpers -----
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function flashToast(msg, kind) {
        var t = document.createElement('div');
        t.className = 'dk-toast dk-toast-' + (kind || 'ok');
        t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('dk-toast-show'); });
        setTimeout(function () {
            t.classList.remove('dk-toast-show');
            setTimeout(function () { t.remove(); }, 300);
        }, 2500);
    }
})();
