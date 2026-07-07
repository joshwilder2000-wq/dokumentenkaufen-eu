/**
 * Live Chat Widget — Professional Design
 *
 * Two-phase UI:
 *   Phase 1: Pre-chat form (name + email + confirm email) — gates entry
 *   Phase 2: Chat panel with messages
 *
 * Messages POST to chat-submit.php, poll for admin replies every 10s.
 * Self-contained CSS + JS, dark theme matching Dokuments Hub.
 */
(function () {
    'use strict';

    // Don't show on admin pages.
    if (window.location.pathname.indexOf('/admin/') !== -1) return;

    // Determine the correct path to chat-submit.php.
    var depth = (window.location.pathname.replace(/\/$/, '').match(/\//g) || []).length;
    var chatUrl = depth > 1 ? '../chat-submit.php' : 'chat-submit.php';

    // Session ID (persists across pages).
    var SID_KEY = 'dk_chat_sid';
    var AUTH_KEY = 'dk_chat_authed';
    var sessionId = localStorage.getItem(SID_KEY);
    if (!sessionId) {
        sessionId = 'v' + Date.now() + Math.random().toString(36).substr(2, 6);
        localStorage.setItem(SID_KEY, sessionId);
    }
    var isAuthed = localStorage.getItem(AUTH_KEY) === '1';

    // --- CSS ---
    var css = document.createElement('style');
    css.textContent = '\
/* Chat button */\
.dk-chat-btn{position:fixed;bottom:24px;right:24px;width:60px;height:60px;border-radius:50%;\
background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border:none;cursor:pointer;z-index:99998;\
font-size:26px;display:flex;align-items:center;justify-content:center;\
box-shadow:0 4px 20px rgba(0,0,0,.4);transition:all .25s}\
.dk-chat-btn:hover{transform:scale(1.08);box-shadow:0 6px 28px rgba(0,0,0,.5)}\
.dk-chat-btn .pulse{position:absolute;top:0;right:0;width:14px;height:14px;border-radius:50%;\
background:#22c55e;border:2px solid #000}\
.dk-chat-btn .pulse::after{content:"";position:absolute;inset:-4px;border-radius:50%;\
background:#22c55e;opacity:.4;animation:dkRing 1.8s infinite}\
@keyframes dkRing{0%{transform:scale(.8);opacity:.5}100%{transform:scale(1.8);opacity:0}}\
\
/* Panel container */\
.dk-chat-panel{position:fixed;bottom:94px;right:24px;width:380px;max-width:calc(100vw - 48px);\
height:520px;max-height:calc(100vh - 130px);background:#fff;border-radius:16px;z-index:99998;\
display:none;flex-direction:column;overflow:hidden;border:1px solid #e0e0e0;\
box-shadow:0 16px 56px rgba(0,0,0,.28);animation:dkUp .3s cubic-bezier(.16,1,.3,1)}\
@keyframes dkUp{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}\
.dk-chat-panel.open{display:flex}\
\
/* Header */\
.dk-chat-head{background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;padding:18px 20px;\
display:flex;align-items:center;gap:12px;flex-shrink:0}\
.dk-chat-avatar{width:38px;height:38px;border-radius:50%;background:#fff;color:#000;\
display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex-shrink:0}\
.dk-chat-head-info{flex:1}\
.dk-chat-head-title{font-weight:600;font-size:15px;display:flex;align-items:center;gap:6px}\
.dk-chat-head-status{font-size:11px;color:#22c55e;display:flex;align-items:center;gap:4px}\
.dk-chat-head-status::before{content:"";width:6px;height:6px;border-radius:50%;background:#22c55e}\
.dk-chat-close{background:none;border:none;color:rgba(255,255,255,.5);font-size:24px;cursor:pointer;\
line-height:1;padding:0;margin-left:auto}\
.dk-chat-close:hover{color:#fff}\
\
/* Pre-chat form */\
.dk-chat-preform{flex:1;display:flex;flex-direction:column;justify-content:center;padding:28px 24px;\
overflow-y:auto}\
.dk-chat-preform h3{font-size:1.15rem;font-weight:700;color:#000;margin-bottom:6px;text-align:center}\
.dk-chat-preform .dk-preform-sub{font-size:.85rem;color:#888;text-align:center;margin-bottom:24px;line-height:1.5}\
.dk-chat-field{margin-bottom:14px}\
.dk-chat-field label{display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:5px}\
.dk-chat-field input{width:100%;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:10px;\
font-size:14px;font-family:inherit;background:#fafafa;box-sizing:border-box;transition:border-color .15s}\
.dk-chat-field input:focus{outline:none;border-color:#333;background:#fff}\
.dk-chat-field input.invalid{border-color:#ef4444}\
.dk-chat-field input.valid{border-color:#22c55e}\
.dk-chat-field-error{font-size:.75rem;color:#ef4444;margin-top:3px;display:none}\
.dk-chat-field-error.show{display:block}\
.dk-chat-start-btn{width:100%;padding:14px;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;\
border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .15s;margin-top:6px}\
.dk-chat-start-btn:hover{opacity:.88}\
.dk-chat-start-btn:disabled{opacity:.4;cursor:not-allowed}\
.dk-chat-privacy{font-size:.7rem;color:#aaa;text-align:center;margin-top:14px;line-height:1.5}\
\
/* Messages area */\
.dk-chat-msgs{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;background:#f8f9fa}\
.dk-chat-msgs::-webkit-scrollbar{width:5px}\
.dk-chat-msgs::-webkit-scrollbar-thumb{background:#d0d0d0;border-radius:3px}\
.dk-chat-msgs::-webkit-scrollbar-track{background:transparent}\
.dk-msg-day{text-align:center;font-size:.7rem;color:#aaa;margin:8px 0}\
.dk-msg{max-width:78%;padding:11px 15px;border-radius:16px;font-size:14px;line-height:1.55;word-wrap:break-word;\
position:relative;animation:dkMsgIn .25s ease}\
@keyframes dkMsgIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}\
.dk-msg.visitor{align-self:flex-end;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border-bottom-right-radius:4px}\
.dk-msg.admin{align-self:flex-start;background:#fff;color:#333;border:1px solid #e8e8e8;border-bottom-left-radius:4px;\
box-shadow:0 1px 3px rgba(0,0,0,.06)}\
.dk-msg .dk-msg-time{font-size:9px;opacity:.45;margin-top:4px}\
.dk-typing{align-self:flex-start;display:flex;gap:4px;padding:12px 16px;background:#fff;border:1px solid #e8e8e8;\
border-radius:16px;border-bottom-left-radius:4px}\
.dk-typing span{width:7px;height:7px;border-radius:50%;background:#bbb;animation:dkType 1.4s infinite}\
.dk-typing span:nth-child(2){animation-delay:.2s}\
.dk-typing span:nth-child(3){animation-delay:.4s}\
@keyframes dkType{0%,60%,100%{opacity:.3;transform:translateY(0)}30%{opacity:1;transform:translateY(-4px)}}\
\
/* Input area */\
.dk-chat-input-area{padding:14px 16px;border-top:1px solid #eee;background:#fff;flex-shrink:0}\
.dk-chat-input-row{display:flex;gap:8px;align-items:flex-end}\
.dk-chat-input-row textarea{flex:1;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:12px;\
font-size:14px;font-family:inherit;resize:none;height:46px;max-height:100px;box-sizing:border-box;\
background:#fafafa;transition:border-color .15s}\
.dk-chat-input-row textarea:focus{outline:none;border-color:#333;background:#fff}\
.dk-chat-send-btn{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#1a1a1a,#000);\
color:#fff;border:none;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;\
transition:opacity .15s;flex-shrink:0}\
.dk-chat-send-btn:hover{opacity:.85}\
.dk-chat-send-btn:disabled{opacity:.35;cursor:not-allowed}\
.dk-chat-foot-note{font-size:.7rem;color:#bbb;text-align:center;margin-top:6px}\
.dk-chat-error{font-size:.78rem;color:#ef4444;text-align:center;padding:6px}\
\
/* Mobile full-screen */\
@media(max-width:480px){\
.dk-chat-panel{right:0;left:0;bottom:0;width:100%;max-width:100%;height:100%;border-radius:0;border:none}\
.dk-chat-btn{bottom:16px;right:16px}\
}';
    document.head.appendChild(css);

    // --- Build DOM ---
    var btn = document.createElement('button');
    btn.className = 'dk-chat-btn';
    btn.innerHTML = '💬<span class="pulse"></span>';
    btn.title = 'Live Chat';
    btn.setAttribute('aria-label', 'Live Chat öffnen');
    document.body.appendChild(btn);

    var panel = document.createElement('div');
    panel.className = 'dk-chat-panel';
    document.body.appendChild(panel);

    // --- Render phases ---
    function renderPreForm() {
        panel.innerHTML = '\
<div class="dk-chat-head">\
  <div class="dk-chat-avatar">D</div>\
  <div class="dk-chat-head-info">\
    <div class="dk-chat-head-title">Live Chat</div>\
    <div class="dk-chat-head-status">Online · Antwort in Minuten</div>\
  </div>\
  <button class="dk-chat-close" id="dkClose">&times;</button>\
</div>\
<div class="dk-chat-preform">\
  <h3>💬 Chat mit einem Agenten</h3>\
  <p class="dk-preform-sub">Bevor wir starten, benötigen wir Ihre Kontaktdaten — damit wir Ihnen antworten können.</p>\
  <div class="dk-chat-field">\
    <label for="dkFName">Name</label>\
    <input type="text" id="dkFName" placeholder="Ihr Name" maxlength="60" autocomplete="name">\
    <div class="dk-chat-field-error" id="dkErrName">Bitte Ihren Namen eingeben.</div>\
  </div>\
  <div class="dk-chat-field">\
    <label for="dkFEmail">E-Mail-Adresse</label>\
    <input type="email" id="dkFEmail" placeholder="ihre@email.de" maxlength="120" autocomplete="email">\
    <div class="dk-chat-field-error" id="dkErrEmail">Bitte eine gültige E-Mail eingeben.</div>\
  </div>\
  <div class="dk-chat-field">\
    <label for="dkFEmail2">E-Mail bestätigen</label>\
    <input type="email" id="dkFEmail2" placeholder="E-Mail wiederholen" maxlength="120" autocomplete="email">\
    <div class="dk-chat-field-error" id="dkErrEmail2">Die Adressen stimmen nicht überein.</div>\
  </div>\
  <button class="dk-chat-start-btn" id="dkStartBtn" disabled>Chat starten →</button>\
  <p class="dk-chat-privacy">Mit dem Starten des Chats stimmen Sie zu, dass Ihre Angaben zur Bearbeitung Ihrer Anfrage verwendet werden.</p>\
</div>';
        bindClose();
        bindPreForm();
    }

    function renderChat() {
        panel.innerHTML = '\
<div class="dk-chat-head">\
  <div class="dk-chat-avatar">D</div>\
  <div class="dk-chat-head-info">\
    <div class="dk-chat-head-title">Live Chat</div>\
    <div class="dk-chat-head-status">Online · Agent verbunden</div>\
  </div>\
  <button class="dk-chat-close" id="dkClose">&times;</button>\
</div>\
<div class="dk-chat-msgs" id="dkMsgs"></div>\
<div class="dk-chat-input-area">\
  <div class="dk-chat-error" id="dkSendErr"></div>\
  <div class="dk-chat-input-row">\
    <textarea id="dkInput" placeholder="Nachricht schreiben…" maxlength="2000" rows="1"></textarea>\
    <button class="dk-chat-send-btn" id="dkSend" title="Senden">➤</button>\
  </div>\
  <div class="dk-chat-foot-note">Antworten erhalten Sie hier und per E-Mail.</div>\
</div>';
        bindClose();
        bindChat();

        // Add welcome message.
        addMsg('Willkommen! Wie können wir Ihnen helfen? Stellen Sie einfach Ihre Frage.', 'admin');
    }

    function bindClose() {
        var c = document.getElementById('dkClose');
        if (c) c.addEventListener('click', function () { panel.classList.remove('open'); });
    }

    // --- Pre-form validation ---
    function bindPreForm() {
        var nameI = document.getElementById('dkFName');
        var emailI = document.getElementById('dkFEmail');
        var email2I = document.getElementById('dkFEmail2');
        var startBtn = document.getElementById('dkStartBtn');

        function validate() {
            var nameOk = nameI.value.trim().length >= 2;
            var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailI.value.trim());
            var matchOk = emailOk && emailI.value.trim().toLowerCase() === email2I.value.trim().toLowerCase();

            nameI.className = nameI.value.trim() === '' ? '' : (nameOk ? 'valid' : 'invalid');
            emailI.className = emailI.value.trim() === '' ? '' : (emailOk ? 'valid' : 'invalid');
            email2I.className = email2I.value.trim() === '' ? '' : (matchOk ? 'valid' : 'invalid');

            document.getElementById('dkErrName').classList.toggle('show', !nameOk && nameI.value.trim() !== '');
            document.getElementById('dkErrEmail').classList.toggle('show', !emailOk && emailI.value.trim() !== '');
            document.getElementById('dkErrEmail2').classList.toggle('show', !matchOk && email2I.value.trim() !== '');

            startBtn.disabled = !(nameOk && emailOk && matchOk);
        }

        [nameI, emailI, email2I].forEach(function (el) { el.addEventListener('input', validate); });

        startBtn.addEventListener('click', function () {
            startBtn.disabled = true;
            startBtn.textContent = 'Wird gestartet…';

            // Store auth data + send first message.
            var body = new FormData();
            body.append('session_id', sessionId);
            body.append('name', nameI.value.trim());
            body.append('email', emailI.value.trim());
            body.append('email_confirm', email2I.value.trim());
            body.append('message', 'Chat gestartet von ' + nameI.value.trim());

            fetch(chatUrl, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        localStorage.setItem(AUTH_KEY, '1');
                        localStorage.setItem('dk_chat_name', nameI.value.trim());
                        localStorage.setItem('dk_chat_email', emailI.value.trim());
                        isAuthed = true;
                        renderChat();
                    } else {
                        startBtn.disabled = false;
                        startBtn.textContent = 'Chat starten →';
                        alert(data.error || 'Fehler beim Starten des Chats.');
                    }
                })
                .catch(function () {
                    startBtn.disabled = false;
                    startBtn.textContent = 'Chat starten →';
                    alert('Netzwerkfehler. Bitte erneut versuchen.');
                });
        });

        setTimeout(function () { nameI.focus(); }, 200);
    }

    // --- Chat panel ---
    function bindChat() {
        var input = document.getElementById('dkInput');
        var sendBtn = document.getElementById('dkSend');
        var errDiv = document.getElementById('dkSendErr');

        // Auto-resize textarea.
        input.addEventListener('input', function () {
            input.style.height = '46px';
            input.style.height = Math.min(input.scrollHeight, 100) + 'px';
        });

        // Enter to send.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
        sendBtn.addEventListener('click', send);

        function send() {
            var text = input.value.trim();
            if (!text) return;
            sendBtn.disabled = true;
            errDiv.textContent = '';

            var body = new FormData();
            body.append('session_id', sessionId);
            body.append('name', localStorage.getItem('dk_chat_name') || '');
            body.append('email', localStorage.getItem('dk_chat_email') || '');
            body.append('email_confirm', localStorage.getItem('dk_chat_email') || '');
            body.append('message', text);

            fetch(chatUrl, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        addMsg(text, 'visitor');
                        input.value = '';
                        input.style.height = '46px';
                    } else {
                        errDiv.textContent = data.error || 'Fehler beim Senden.';
                    }
                })
                .catch(function () { errDiv.textContent = 'Netzwerkfehler.'; })
                .finally(function () { sendBtn.disabled = false; input.focus(); });
        }

        setTimeout(function () { input.focus(); }, 200);
        startPolling();
    }

    // --- Helpers ---
    function addMsg(text, who) {
        var msgs = document.getElementById('dkMsgs');
        if (!msgs) return;
        var div = document.createElement('div');
        div.className = 'dk-msg ' + who;
        var t = new Date();
        var time = String(t.getHours()).padStart(2, '0') + ':' + String(t.getMinutes()).padStart(2, '0');
        div.innerHTML = escapeHtml(text) + '<div class="dk-msg-time">' + time + '</div>';
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // --- Poll for admin replies ---
    function startPolling() {
        var seenIds = {};
        setInterval(function () {
            if (!panel.classList.contains('open')) return;
            fetch(chatUrl + '?poll=1&session=' + encodeURIComponent(sessionId))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && data.replies) {
                        data.replies.forEach(function (rep) {
                            if (!seenIds[rep.id]) {
                                seenIds[rep.id] = true;
                                addMsg(rep.text, 'admin');
                            }
                        });
                    }
                })
                .catch(function () {});
        }, 10000);
    }

    // --- Toggle ---
    btn.addEventListener('click', function () {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            if (isAuthed) {
                if (!document.getElementById('dkMsgs')) renderChat();
            } else {
                if (!document.getElementById('dkStartBtn')) renderPreForm();
            }
        }
    });

    // --- Initial render ---
    if (isAuthed) {
        renderChat();
    } else {
        renderPreForm();
    }
})();
