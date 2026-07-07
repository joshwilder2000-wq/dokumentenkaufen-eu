/**
 * Live Chat Widget — dark theme matching Dokuments Hub.
 *
 * Floating button (bottom-right) → opens a sleek chat panel.
 * Messages POST to chat-submit.php, poll for admin replies every 15s.
 * Self-contained (CSS + JS, no dependencies).
 */
(function () {
    'use strict';

    // Don't show on admin pages.
    if (window.location.pathname.indexOf('/admin/') !== -1) return;

    // Determine the correct path to chat-submit.php based on current depth.
    var depth = (window.location.pathname.replace(/\/$/, '').match(/\//g) || []).length;
    var chatUrl = depth > 1 ? '../chat-submit.php' : 'chat-submit.php';

    // Session ID (persists across pages).
    var SID_KEY = 'dk_chat_sid';
    var sessionId = localStorage.getItem(SID_KEY);
    if (!sessionId) {
        sessionId = 'v' + Date.now() + Math.random().toString(36).substr(2, 6);
        localStorage.setItem(SID_KEY, sessionId);
    }

    // --- Inject CSS ---
    var css = document.createElement('style');
    css.textContent = '\
.dk-chat-btn{position:fixed;bottom:24px;right:24px;width:58px;height:58px;border-radius:50%;\
background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border:2px solid #333;cursor:pointer;\
z-index:99998;font-size:24px;display:flex;align-items:center;justify-content:center;\
box-shadow:0 4px 20px rgba(0,0,0,.4);transition:all .25s ease}\
.dk-chat-btn:hover{transform:scale(1.08);box-shadow:0 6px 28px rgba(0,0,0,.5);border-color:#555}\
.dk-chat-btn .dk-chat-pulse{position:absolute;top:-2px;right:-2px;width:12px;height:12px;\
border-radius:50%;background:#22c55e;border:2px solid #000;animation:dkPulse 2s infinite}\
@keyframes dkPulse{0%,100%{opacity:1}50%{opacity:.5}}\
.dk-chat-panel{position:fixed;bottom:94px;right:24px;width:360px;max-width:calc(100vw - 48px);\
height:480px;max-height:calc(100vh - 130px);background:#fff;border-radius:16px;\
box-shadow:0 12px 48px rgba(0,0,0,.25);z-index:99998;display:none;flex-direction:column;\
overflow:hidden;border:1px solid #e0e0e0;animation:dkSlideUp .25s ease}\
@keyframes dkSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}\
.dk-chat-panel.open{display:flex}\
.dk-chat-head{background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;padding:16px 20px;\
font-weight:600;font-size:15px;display:flex;justify-content:space-between;align-items:center}\
.dk-chat-head .dk-chat-title{display:flex;align-items:center;gap:8px}\
.dk-chat-head .dk-chat-online{width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block}\
.dk-chat-close{background:none;border:none;color:#999;font-size:24px;cursor:pointer;line-height:1;padding:0 4px}\
.dk-chat-close:hover{color:#fff}\
.dk-chat-intro{padding:12px 16px;background:#f5f5f5;font-size:12px;color:#666;border-bottom:1px solid #e0e0e0}\
.dk-chat-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#fafafa}\
.dk-chat-msgs::-webkit-scrollbar{width:5px}\
.dk-chat-msgs::-webkit-scrollbar-thumb{background:#ccc;border-radius:3px}\
.dk-msg{max-width:80%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.5;word-wrap:break-word}\
.dk-msg.visitor{align-self:flex-end;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border-bottom-right-radius:4px}\
.dk-msg.admin{align-self:flex-start;background:#fff;color:#333;border:1px solid #e0e0e0;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,.06)}\
.dk-msg-time{font-size:10px;opacity:.5;margin-top:3px}\
.dk-chat-form{padding:12px 14px;border-top:1px solid #e0e0e0;background:#fff}\
.dk-chat-name-row{margin-bottom:8px}\
.dk-chat-name-row input{width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;\
font-family:inherit;background:#fafafa;box-sizing:border-box}\
.dk-chat-name-row input:focus{outline:none;border-color:#999;background:#fff}\
.dk-chat-input-row{display:flex;gap:8px}\
.dk-chat-input-row textarea{flex:1;padding:10px 12px;border:1px solid #ddd;border-radius:8px;\
font-size:14px;font-family:inherit;resize:none;height:44px;box-sizing:border-box;background:#fafafa}\
.dk-chat-input-row textarea:focus{outline:none;border-color:#999;background:#fff}\
.dk-chat-send{background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border:none;border-radius:8px;\
padding:0 18px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;\
transition:opacity .15s}\
.dk-chat-send:hover{opacity:.85}\
.dk-chat-send:disabled{opacity:.4;cursor:default}\
.dk-chat-status{font-size:11px;color:#888;text-align:center;padding:2px 0 0;min-height:14px}\
@media(max-width:480px){\
.dk-chat-panel{right:0;left:0;bottom:0;width:100%;max-width:100%;height:100%;border-radius:0;border:none}\
.dk-chat-btn{bottom:16px;right:16px}\
}';
    document.head.appendChild(css);

    // --- Build DOM ---
    var btn = document.createElement('button');
    btn.className = 'dk-chat-btn';
    btn.innerHTML = '💬<span class="dk-chat-pulse"></span>';
    btn.title = 'Live Chat';
    btn.setAttribute('aria-label', 'Live Chat öffnen');
    document.body.appendChild(btn);

    var panel = document.createElement('div');
    panel.className = 'dk-chat-panel';
    panel.innerHTML = '\
<div class="dk-chat-head">\
  <span class="dk-chat-title"><span class="dk-chat-online"></span> Live Chat</span>\
  <button class="dk-chat-close" aria-label="Schließen">&times;</button>\
</div>\
<div class="dk-chat-intro">Stellen Sie Ihre Frage — ein Agent antwortet in der Regel innerhalb weniger Minuten.</div>\
<div class="dk-chat-msgs" id="dkChatMsgs"></div>\
<div class="dk-chat-form">\
  <div class="dk-chat-name-row">\
    <input type="text" id="dkChatName" placeholder="Ihr Name (optional)" maxlength="60">\
  </div>\
  <div class="dk-chat-input-row">\
    <textarea id="dkChatText" placeholder="Schreiben Sie eine Nachricht…" maxlength="2000"></textarea>\
    <button class="dk-chat-send" id="dkChatSend" title="Senden">➤</button>\
  </div>\
  <div class="dk-chat-status" id="dkChatStatus"></div>\
</div>';
    document.body.appendChild(panel);

    var msgsDiv = document.getElementById('dkChatMsgs');
    var textArea = document.getElementById('dkChatText');
    var nameInput = document.getElementById('dkChatName');
    var sendBtn = document.getElementById('dkChatSend');
    var statusDiv = document.getElementById('dkChatStatus');
    var closeBtn = panel.querySelector('.dk-chat-close');

    // --- Toggle panel ---
    btn.addEventListener('click', function () {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            setTimeout(function () { textArea.focus(); }, 100);
        }
    });
    closeBtn.addEventListener('click', function () { panel.classList.remove('open'); });

    // Enter to send (Shift+Enter for newline).
    textArea.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
    });
    sendBtn.addEventListener('click', sendMsg);

    function setStatus(msg, isError) {
        statusDiv.textContent = msg;
        statusDiv.style.color = isError ? '#b91c1c' : '#888';
        if (msg) setTimeout(function () { if (statusDiv.textContent === msg) statusDiv.textContent = ''; }, 4000);
    }

    // --- Send message ---
    function sendMsg() {
        var text = textArea.value.trim();
        if (!text) return;
        sendBtn.disabled = true;
        setStatus('Wird gesendet…', false);

        var body = new FormData();
        body.append('session_id', sessionId);
        body.append('name', nameInput.value.trim());
        body.append('message', text);

        fetch(chatUrl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    addMsg(text, 'visitor');
                    textArea.value = '';
                    setStatus('Nachricht gesendet ✓', false);
                    if (data.session_id) sessionId = data.session_id;
                } else {
                    setStatus(data.error || 'Fehler beim Senden.', true);
                }
            })
            .catch(function () { setStatus('Netzwerkfehler.', true); })
            .finally(function () { sendBtn.disabled = false; });
    }

    function addMsg(text, who) {
        var div = document.createElement('div');
        div.className = 'dk-msg ' + who;
        var t = new Date();
        var time = String(t.getHours()).padStart(2, '0') + ':' + String(t.getMinutes()).padStart(2, '0');
        div.innerHTML = escapeHtml(text) + '<div class="dk-msg-time">' + time + '</div>';
        msgsDiv.appendChild(div);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // --- Poll for admin replies every 15s (only when panel is open) ---
    var seenReplyIds = {};
    setInterval(function () {
        if (!panel.classList.contains('open')) return; // only poll when open
        fetch(chatUrl + '?poll=1&session=' + encodeURIComponent(sessionId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok && data.replies) {
                    data.replies.forEach(function (rep) {
                        if (!seenReplyIds[rep.id]) {
                            seenReplyIds[rep.id] = true;
                            addMsg(rep.text, 'admin');
                        }
                    });
                }
            })
            .catch(function () {});
    }, 15000);
})();
