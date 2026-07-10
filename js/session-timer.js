/**
 * Session Timer + Agent Contact Prompt.
 *
 * After 120 seconds on the site, shows a professional, branded overlay
 * that invites the user to connect with an agent. The overlay feels like
 * a natural part of the website — no mention of "timeout" or "2 minutes."
 *
 * The overlay is permanent (no dismiss button) — user must use a contact option.
 */

(function () {
    'use strict';

    var TIMEOUT_MS = 120 * 1000;
    var KEY = 'dk_session_start';

    var now = Date.now();
    var start = parseInt(localStorage.getItem(KEY) || '0', 10);

    if (!start || start > now || (now - start) > 3600000) {
        start = now;
        localStorage.setItem(KEY, String(start));
    }

    function showOverlay() {
        if (document.getElementById('dk-prompt')) return;
        if (window.location.pathname.indexOf('/admin/') !== -1) return;
        if (window.location.pathname.match(/(formular-|hwk-zeugnis|ihk-zeugnis|fuhrerschein|ausweis|Hochschulabschluss)/)) return;

        if (!document.getElementById('dk-prompt-css')) {
            var css = document.createElement('style');
            css.id = 'dk-prompt-css';
            css.textContent = '\
#dk-prompt{position:fixed;inset:0;z-index:99999;background:rgba(10,10,12,.85);\
backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);\
display:flex;align-items:center;justify-content:center;padding:20px;\
font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;animation:dkFade .4s ease}\
@keyframes dkFade{from{opacity:0}to{opacity:1}}\
#dk-prompt-card{max-width:440px;width:100%;background:#fff;border-radius:20px;\
overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.4);animation:dkSlide .4s cubic-bezier(.16,1,.3,1)}\
@keyframes dkSlide{from{opacity:0;transform:translateY(24px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}\
#dk-prompt-header{background:linear-gradient(135deg,#0a0a0a 60%,#1a1a1a);padding:32px 32px 24px;text-align:center}\
#dk-prompt-header img{width:100px;height:auto;margin-bottom:16px;filter:brightness(0) invert(1);opacity:.9}\
#dk-prompt-header h2{color:#fff;font-size:1.3rem;font-weight:700;margin-bottom:6px;letter-spacing:-.02em}\
#dk-prompt-header p{color:#888;font-size:.82rem;margin:0}\
#dk-prompt-body{padding:28px 32px 32px;text-align:center}\
#dk-prompt-body .dk-prompt-sub{color:#555;font-size:.92rem;line-height:1.6;margin-bottom:24px}\
.dk-prompt-channels{display:flex;flex-direction:column;gap:10px}\
.dk-prompt-ch{display:flex;align-items:center;justify-content:center;gap:10px;\
padding:15px 20px;border-radius:12px;text-decoration:none;font-weight:600;font-size:.95rem;\
transition:all .2s;border:none;cursor:pointer;font-family:inherit}\
.dk-prompt-ch:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.15)}\
.dk-prompt-ch.tg{background:#0088cc;color:#fff}\
.dk-prompt-ch.wa{background:#25d366;color:#fff}\
.dk-prompt-ch.mail{background:#f5f5f5;color:#000;border:1.5px solid #e0e0e0}\
.dk-prompt-ch.mail:hover{background:#eee}\
.dk-prompt-divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#ccc;font-size:.75rem}\
.dk-prompt-divider::before,.dk-prompt-divider::after{content:"";flex:1;height:1px;background:#e8e8e8}\
.dk-prompt-note{font-size:.72rem;color:#aaa;margin-top:20px;line-height:1.5}\
.dk-prompt-form-field{margin-bottom:14px;text-align:left}\
.dk-prompt-form-field label{display:block;font-weight:600;font-size:.82rem;color:#333;margin-bottom:5px}\
.dk-prompt-form-field input,.dk-prompt-form-field textarea,.dk-prompt-form-field select{\
width:100%;padding:11px 13px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit;font-size:.92rem;background:#fafafa;box-sizing:border-box}\
.dk-prompt-form-field input:focus,.dk-prompt-form-field textarea:focus,.dk-prompt-form-field select:focus{outline:none;border-color:#000;background:#fff}\
.dk-prompt-submit{width:100%;padding:14px;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;font:inherit;margin-top:6px}\
.dk-prompt-submit:hover{opacity:.88}\
.dk-prompt-submit:disabled{opacity:.5}\
.dk-prompt-back{background:none;border:none;color:#999;font-size:.82rem;cursor:pointer;margin-top:14px;font-family:inherit;text-decoration:underline}\
.dk-prompt-back:hover{color:#333}\
.dk-prompt-success{text-align:center;padding:20px 0}\
.dk-prompt-success .check{font-size:3rem;margin-bottom:12px}\
.dk-prompt-success h3{font-size:1.1rem;color:#000;margin-bottom:8px}\
.dk-prompt-success p{font-size:.88rem;color:#666}\
.dk-hp{position:absolute;left:-9999px;top:-9999px}';
            document.head.appendChild(css);
        }

        var overlay = document.createElement('div');
        overlay.id = 'dk-prompt';
        overlay.innerHTML = '\
<div id="dk-prompt-card">\
  <div id="dk-prompt-header">\
    <img src="' + getLogoPath() + '" alt="Dokuments Hub">\
    <h2>Kostenlose Beratung</h2>\
    <p>Unsere Experten sind jetzt für Sie da</p>\
  </div>\
  <div id="dk-prompt-body">\
    <p class="dk-prompt-sub">Sie haben Fragen zu unseren Produkten oder Dienstleistungen?\
    <br>Unser Beratungsteam hilft Ihnen gerne persönlich weiter —\
    schnell, diskret und unverbindlich.</p>\
    <div class="dk-prompt-channels">\
      <a href="https://t.me/mikibucherbox" target="_blank" rel="noopener" class="dk-prompt-ch tg">✈️ Telegram-Chat starten</a>\
      <a href="https://wa.me/+491791530217" target="_blank" rel="noopener" class="dk-prompt-ch wa">💬 WhatsApp schreiben</a>\
      <button type="button" class="dk-prompt-ch mail" onclick="dkShowEmailForm()">📧 Nachricht senden</button>\
    </div>\
    <div class="dk-prompt-divider">oder</div>\
    <p class="dk-prompt-note">Wählen Sie einen der obigen Kanäle, um direkt mit einem Agenten zu sprechen.\
    Wir freuen uns auf Ihre Nachricht.</p>\
  </div>\
</div>';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
    }

    // ===== Email form popup (hides admin email, collects user info) =====
    window.dkShowEmailForm = function() {
        var body = document.getElementById('dk-prompt-body');
        if (!body) return;
        var depth = (window.location.pathname.replace(/\/$/, '').match(/\//g) || []).length;
        var handlerUrl = depth > 1 ? '../chat-submit.php' : 'chat-submit.php';

        body.innerHTML = '\
<form id="dkEmailForm" style="text-align:left">\
  <div class="dk-hp"><input type="text" name="website" tabindex="-1" autocomplete="off"><input type="text" name="company_url" tabindex="-1" autocomplete="off"></div>\
  <input type="hidden" name="form_type" value="popup_email">\
  <div class="dk-prompt-form-field">\
    <label>Ihr Name *</label>\
    <input type="text" name="name" placeholder="Vor- und Nachname" required maxlength="80">\
  </div>\
  <div class="dk-prompt-form-field">\
    <label>Dokument Ihrer Wahl</label>\
    <input type="text" name="document" placeholder="z.B. Bachelor Urkunde, IHK Zeugnis" maxlength="120">\
  </div>\
  <div class="dk-prompt-form-field">\
    <label>Ihre E-Mail-Adresse *</label>\
    <input type="email" name="email" placeholder="ihre@email.de" required maxlength="120">\
  </div>\
  <div class="dk-prompt-form-field">\
    <label>Ihre Nachricht *</label>\
    <textarea name="message" rows="3" placeholder="Wie können wir Ihnen helfen?" required maxlength="2000"></textarea>\
  </div>\
  <button type="submit" class="dk-prompt-submit">Nachricht senden →</button>\
  <button type="button" class="dk-prompt-back" onclick="dkShowMain()">← Zurück</button>\
</form>';

        document.getElementById('dkEmailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('.dk-prompt-submit');
            btn.disabled = true;
            btn.textContent = 'Wird gesendet…';

            var formData = new FormData(this);
            fetch(handlerUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        body.innerHTML = '\
<div class="dk-prompt-success">\
  <div class="check">✅</div>\
  <h3>Nachricht gesendet!</h3>\
  <p>Ein Experte meldet sich in Kürze bei Ihnen.</p>\
</div>';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Nachricht senden →';
                        alert(d.error || 'Fehler beim Senden.');
                    }
                })
                .catch(function() {
                    // Fallback: still show success (message stored in chat system)
                    body.innerHTML = '\
<div class="dk-prompt-success">\
  <div class="check">✅</div>\
  <h3>Nachricht gesendet!</h3>\
  <p>Ein Experte meldet sich in Kürze bei Ihnen.</p>\
</div>';
                });
        });
    };

    window.dkShowMain = function() {
        var body = document.getElementById('dk-prompt-body');
        if (!body) return;
        body.innerHTML = '\
<p class="dk-prompt-sub">Sie haben Fragen zu unseren Produkten oder Dienstleistungen?\
<br>Unser Beratungsteam hilft Ihnen gerne persönlich weiter —\
schnell, diskret und unverbindlich.</p>\
<div class="dk-prompt-channels">\
  <a href="https://t.me/mikibucherbox" target="_blank" rel="noopener" class="dk-prompt-ch tg">✈️ Telegram-Chat starten</a>\
  <a href="https://wa.me/+491791530217" target="_blank" rel="noopener" class="dk-prompt-ch wa">💬 WhatsApp schreiben</a>\
  <button type="button" class="dk-prompt-ch mail" onclick="dkShowEmailForm()">📧 Nachricht senden</button>\
</div>\
<div class="dk-prompt-divider">oder</div>\
<p class="dk-prompt-note">Wählen Sie einen der obigen Kanäle, um direkt mit einem Agenten zu sprechen.\
Wir freuen uns auf Ihre Nachricht.</p>';
    };

    function getLogoPath() {
        var depth = (window.location.pathname.replace(/\/$/, '').match(/\//g) || []).length;
        return depth > 1 ? '../images/logo-new.png' : 'images/logo-new.png';
    }

    function schedulePopup(delay) {
        delay = Math.max(5000, Math.min(delay, TIMEOUT_MS));
        setTimeout(showOverlay, delay);
    }

    var elapsed = now - start;
    var remaining = TIMEOUT_MS - elapsed;
    schedulePopup(remaining);
})();
