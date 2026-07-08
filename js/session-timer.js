/**
 * Session Timer + Agent Popup.
 *
 * After 120 seconds on the site (tracked across page navigations via localStorage),
 * shows a full-screen modal overlay directing the user to connect with an agent.
 *
 * IMPORTANT: Uses a per-tab "first load" check — if the page was just loaded
 * (not a background tab that's been open for hours), the timer starts fresh.
 * The localStorage value is capped and validated to prevent stale-trigger bugs.
 */
(function () {
    'use strict';

    var TIMEOUT_MS = 120 * 1000; // 120 seconds
    var KEY = 'dk_session_start';
    var DISMISS_KEY = 'dk_session_dismissed_until';

    var now = Date.now();

    // --- Session start logic ---
    // If the navigation type is a fresh page load (not back-forward from cache),
    // OR the stored start time is invalid/ancient (older than 1 hour), reset it.
    var navType = (performance && performance.getEntriesByType) ?
        (performance.getEntriesByType('navigation')[0] || {}).type : 'navigate';

    var start = parseInt(localStorage.getItem(KEY) || '0', 10);

    // Reset the timer if:
    // 1. No start time stored (first visit)
    // 2. Start time is in the future (corrupted)
    // 3. Start time is older than 1 hour (stale from a previous session)
    // 4. Navigation type is a reload (fresh start)
    if (!start || start > now || (now - start) > 3600000) {
        start = now;
        localStorage.setItem(KEY, String(start));
    }

    // Check dismiss state.
    var dismissedUntil = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);

    function showOverlay() {
        // Don't show if already present.
        if (document.getElementById('dk-timer-overlay')) return;
        // Don't show on admin pages.
        if (window.location.pathname.indexOf('/admin/') !== -1) return;

        // Inject CSS.
        if (!document.getElementById('dk-timer-css')) {
            var css = document.createElement('style');
            css.id = 'dk-timer-css';
            css.textContent = '\
#dk-timer-overlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);\
backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);\
display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;\
font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;animation:dkFadeIn .3s ease}\
@keyframes dkFadeIn{from{opacity:0}to{opacity:1}}\
#dk-timer-box{max-width:480px;width:100%;background:linear-gradient(135deg,#1a1a1a 0%,#000 100%);\
border:1px solid #333;border-radius:16px;padding:48px 36px;box-shadow:0 24px 64px rgba(0,0,0,.5);\
color:#fff}\
#dk-timer-box h2{font-size:1.6rem;color:#fff;margin-bottom:12px;font-weight:700}\
#dk-timer-box .dk-timer-sub{font-size:1.05rem;color:#aaa;line-height:1.7;margin-bottom:32px}\
.dk-timer-actions{display:flex;flex-direction:column;gap:12px}\
.dk-timer-btn{display:flex;align-items:center;justify-content:center;gap:8px;\
padding:16px 24px;border-radius:10px;text-decoration:none;font-weight:600;font-size:1rem;\
transition:transform .15s,box-shadow .15s,background .15s}\
.dk-timer-btn:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.3)}\
.dk-timer-btn.telegram{background:#0088cc;color:#fff}\
.dk-timer-btn.whatsapp{background:#25d366;color:#fff}\
.dk-timer-btn.email{background:#fff;color:#000}';
            document.head.appendChild(css);
        }

        var overlay = document.createElement('div');
        overlay.id = 'dk-timer-overlay';
        overlay.innerHTML = '\
<div id="dk-timer-box">\
  <h2>👋 Noch Fragen?</h2>\
  <p class="dk-timer-sub">Sie sind seit 2 Minuten auf unserer Seite.<br>\
     Verbinden Sie sich direkt mit einem Agenten — wir helfen Ihnen gerne weiter.</p>\
  <div class="dk-timer-actions">\
    <a href="https://t.me/mikibucherbox" target="_blank" rel="noopener" class="dk-timer-btn telegram">✈️ Telegram</a>\
    <a href="https://wa.me/+491791530217" target="_blank" rel="noopener" class="dk-timer-btn whatsapp">💬 WhatsApp</a>\
    <a href="mailto:leitung@akademischergrad.de" class="dk-timer-btn email">📧 E-Mail</a>\
  </div>\
</div>';
        document.body.appendChild(overlay);

        // Block scrolling permanently — no dismiss option.
        document.body.style.overflow = 'hidden';
    }

    function schedulePopup(delay) {
        // Clamp delay: never less than 5s, never more than TIMEOUT_MS.
        delay = Math.max(5000, Math.min(delay, TIMEOUT_MS));
        setTimeout(showOverlay, delay);
    }

    // Calculate remaining time.
    var elapsed = now - start;
    var remaining = TIMEOUT_MS - elapsed;

    // If within a dismiss window, restart for the remaining dismiss time.
    if (now < dismissedUntil) {
        remaining = dismissedUntil - now;
    }

    schedulePopup(remaining);
})();
