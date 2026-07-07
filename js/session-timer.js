/**
 * Session Timer + Agent Popup.
 *
 * After 120 seconds on the site (tracked across page navigations via localStorage),
 * shows a full-screen modal overlay that blocks all interaction and directs the
 * user to connect with an agent. Self-contained — no dependencies.
 */
(function () {
    'use strict';

    var TIMEOUT_MS = 120 * 1000; // 120 seconds
    var KEY = 'dk_session_start';
    var DISMISS_KEY = 'dk_session_dismissed_until';

    // Get or set the session start time (persists across navigations).
    var now = Date.now();
    var start = parseInt(localStorage.getItem(KEY) || '0', 10);
    if (!start || start > now) {
        start = now;
        localStorage.setItem(KEY, String(start));
    }

    // Check if previously dismissed (and still within the dismiss window).
    var dismissedUntil = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);

    function timeUp() {
        if (now < dismissedUntil) {
            // Still within a "Später" dismiss → restart timer for the remaining time.
            startTimer(dismissedUntil - now);
            return;
        }
        showOverlay();
    }

    function startTimer(delay) {
        setTimeout(timeUp, Math.max(delay, 1000));
    }

    // --- The overlay ---
    function showOverlay() {
        if (document.getElementById('dk-timer-overlay')) return;

        var css = document.createElement('style');
        css.textContent = '\
#dk-timer-overlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.95);\
display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;\
font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif}\
#dk-timer-box{max-width:500px;background:#fff;border-radius:16px;padding:48px 36px;\
box-shadow:0 24px 64px rgba(0,0,0,.4)}\
#dk-timer-box h2{font-size:1.5rem;color:#000;margin-bottom:16px;font-weight:700}\
#dk-timer-box p{font-size:1.05rem;color:#555;line-height:1.7;margin-bottom:28px}\
.dk-timer-actions{display:flex;flex-direction:column;gap:12px}\
.dk-timer-btn{display:flex;align-items:center;justify-content:center;gap:8px;\
padding:14px 24px;border-radius:10px;text-decoration:none;font-weight:600;font-size:1rem;\
transition:transform .15s,box-shadow .15s}\
.dk-timer-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}\
.dk-timer-btn.telegram{background:#0088cc;color:#fff}\
.dk-timer-btn.whatsapp{background:#25d366;color:#fff}\
.dk-timer-btn.email{background:#000;color:#fff}\
.dk-timer-later{margin-top:20px;font-size:.85rem;color:#999;text-decoration:underline;cursor:pointer;\
background:none;border:none;font-family:inherit}';
        document.head.appendChild(css);

        var overlay = document.createElement('div');
        overlay.id = 'dk-timer-overlay';
        overlay.innerHTML = '\
<div id="dk-timer-box">\
  <h2>👋 Noch Fragen?</h2>\
  <p>Sie sind seit 2 Minuten auf unserer Seite. Haben Sie weitere Fragen?<br>\
     Verbinden Sie sich direkt mit einem Agenten — wir helfen Ihnen gerne weiter.</p>\
  <div class="dk-timer-actions">\
    <a href="https://t.me/mikibucherbox" target="_blank" rel="noopener" class="dk-timer-btn telegram">✈️ Telegram</a>\
    <a href="https://wa.me/+491791530217" target="_blank" rel="noopener" class="dk-timer-btn whatsapp">💬 WhatsApp</a>\
    <a href="mailto:leitung@akademischergrad.de" class="dk-timer-btn email">📧 E-Mail</a>\
  </div>\
  <button class="dk-timer-later" id="dkTimerLater">Später erinnern</button>\
</div>';
        document.body.appendChild(overlay);

        // Block scrolling.
        document.body.style.overflow = 'hidden';

        // "Später" button → dismiss for another 120s.
        document.getElementById('dkTimerLater').addEventListener('click', function () {
            var dismissUntil = Date.now() + TIMEOUT_MS;
            localStorage.setItem(DISMISS_KEY, String(dismissUntil));
            overlay.remove();
            document.body.style.overflow = '';
            startTimer(TIMEOUT_MS);
        });
    }

    // Start.
    var elapsed = now - start;
    var remaining = TIMEOUT_MS - elapsed;
    startTimer(remaining);
})();
