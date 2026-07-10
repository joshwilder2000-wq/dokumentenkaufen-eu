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
.dk-prompt-note{font-size:.72rem;color:#aaa;margin-top:20px;line-height:1.5}';
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
      <a href="mailto:leitung@akademischergrad.de" class="dk-prompt-ch mail">📧 E-Mail senden</a>\
    </div>\
    <div class="dk-prompt-divider">oder</div>\
    <p class="dk-prompt-note">Wählen Sie einen der obigen Kanäle, um direkt mit einem Agenten zu sprechen.\
    Wir freuen uns auf Ihre Nachricht.</p>\
  </div>\
</div>';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
    }

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
