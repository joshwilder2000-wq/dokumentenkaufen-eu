/**
 * Dokuments Hub Admin — dashboard interactions.
 *
 * - Copy-URL button
 * - Quick-edit modal: fetches full product data, shows popup with image preview,
 *   title, short description, meta description, category. Saves via AJAX.
 */
(function () {
    'use strict';
    var csrf = window.DK_CSRF || '';
    var base = window.DK_BASE || '/admin/';

    // ----- Copy URL -----
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.dk-copy-btn');
        if (!btn) return;
        var url = btn.getAttribute('data-url') || '';
        var done = function () { var o = btn.textContent; btn.textContent = '✓'; setTimeout(function(){btn.textContent=o;},1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done).catch(function(){fallbackCopy(url);done();}); }
        else { fallbackCopy(url); done(); }
    });
    function fallbackCopy(t) { var ta=document.createElement('textarea');ta.value=t;ta.style.position='fixed';ta.style.left='-9999px';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');}catch(e){}document.body.removeChild(ta); }

    // ----- Quick-edit modal -----
    var modal = document.getElementById('dkModal');
    if (!modal) return;

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.dk-quickedit-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        openModal(id);
    });

    function openModal(id) {
        fetch(base + 'dashboard.php?ajax=get_product&id=' + id)
            .then(function(r){return r.json();})
            .then(function(d){
                if (!d.ok) { alert('Produkt nicht gefunden.'); return; }
                document.getElementById('dkModalTitle').textContent = d.title || 'Bearbeiten';
                document.getElementById('dkModalInputTitle').value = d.title || '';
                document.getElementById('dkModalInputShort').value = d.short_description || '';
                document.getElementById('dkModalInputMeta').value = d.meta_description || '';
                if (d.category) document.getElementById('dkModalInputCat').value = d.category;

                var imgDiv = document.getElementById('dkModalImage');
                imgDiv.innerHTML = d.og_image
                    ? '<img src="../' + d.og_image + '" alt="" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #e0e0e0">'
                    : '<span style="color:#999;font-size:.85rem">Kein Bild</span>';

                document.getElementById('dkModalFullEdit').href = base + 'product-edit.php?id=' + id;
                modal.setAttribute('data-pid', id);
                modal.style.display = 'flex';
            })
            .catch(function(){ alert('Netzwerkfehler.'); });
    }

    var saveBtn = document.getElementById('dkModalSave');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var pid = modal.getAttribute('data-pid');
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Speichern...';

            var body = new URLSearchParams();
            body.append('csrf_token', csrf);
            body.append('id', pid);
            body.append('title', document.getElementById('dkModalInputTitle').value.trim());
            body.append('short_description', document.getElementById('dkModalInputShort').value.trim());
            body.append('meta_description', document.getElementById('dkModalInputMeta').value.trim());
            body.append('category', document.getElementById('dkModalInputCat').value);

            fetch(base + 'dashboard.php?ajax=quick_edit', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: body.toString()
            })
            .then(function(r){return r.json();})
            .then(function(d){
                if (d.ok) {
                    modal.style.display = 'none';
                    flashToast(d.pinged ? 'Gespeichert + Google angepingt.' : 'Gespeichert.', 'ok');
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 Speichern + Google anpingen';
                    alert(d.error || 'Fehler.');
                }
            })
            .catch(function(){
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Speichern + Google anpingen';
                alert('Netzwerkfehler.');
            });
        });
    }

    modal.addEventListener('click', function(ev) {
        if (ev.target === modal) modal.style.display = 'none';
    });

    function flashToast(msg, kind) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:8px;color:#fff;font-weight:500;z-index:99999;opacity:0;transition:opacity .3s';
        t.style.background = kind === 'warn' ? '#b45309' : '#15803d';
        document.body.appendChild(t);
        requestAnimationFrame(function(){t.style.opacity='1';});
        setTimeout(function(){t.style.opacity='0';setTimeout(function(){t.remove();},300);},2500);
    }
})();
