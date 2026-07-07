/**
 * Live Chat Widget — self-contained (CSS + JS, no dependencies).
 *
 * Floating button (bottom-right) → opens a chat panel.
 * Messages POST to /chat-submit.php, poll for admin replies every 15s.
 * Works on both root pages (../chat-submit.php) and subdirectory pages.
 */
(function () {
    'use strict';

    // Determine the correct path to chat-submit.php based on current depth.
    var path = (window.location.pathname.match(/\//g) || []).length;
    var chatUrl = path > 1 ? '../chat-submit.php' : 'chat-submit.php';

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
.dk-chat-btn{position:fixed;bottom:20px;right:20px;width:60px;height:60px;border-radius:50%;\
background:#000;color:#fff;border:none;cursor:pointer;z-index:99998;font-size:26px;\
box-shadow:0 4px 16px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;transition:transform .2s}\
.dk-chat-btn:hover{transform:scale(1.08)}\
.dk-chat-panel{position:fixed;bottom:90px;right:20px;width:340px;max-width:calc(100vw - 40px);\
height:440px;max-height:calc(100vh - 120px);background:#fff;border-radius:12px;\
box-shadow:0 8px 32px rgba(0,0,0,.2);z-index:99998;display:none;flex-direction:column;overflow:hidden;\
border:1px solid #e0e0e0}\
.dk-chat-panel.open{display:flex}\
.dk-chat-head{background:#000;color:#fff;padding:14px 18px;font-weight:600;font-size:15px;\
display:flex;justify-content:space-between;align-items:center}\
.dk-chat-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1}\
.dk-chat-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;background:#f5f5f5}\
.dk-msg{max-width:80%;padding:10px 14px;border-radius:12px;font-size:14px;line-height:1.5;word-wrap:break-word}\
.dk-msg.visitor{align-self:flex-end;background:#000;color:#fff;border-bottom-right-radius:4px}\
.dk-msg.admin{align-self:flex-start;background:#fff;color:#333;border:1px solid #e0e0e0;border-bottom-left-radius:4px}\
.dk-msg-time{font-size:10px;opacity:.6;margin-top:2px}\
.dk-chat-form{padding:12px;border-top:1px solid #e0e0e0;background:#fff}\
.dk-chat-name-row{display:flex;gap:8px;margin-bottom:8px}\
.dk-chat-name-row input{flex:1;padding:8px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px}\
.dk-chat-input-row{display:flex;gap:8px}\
.dk-chat-input-row textarea{flex:1;padding:8px 10px;border:1px solid #e0e0e0;border-radius:6px;\
font-size:14px;font-family:inherit;resize:none;height:44px}\
.dk-chat-send{background:#000;color:#fff;border:none;border-radius:6px;padding:0 16px;\
cursor:pointer;font-size:20px;font-weight:600}\
.dk-chat-send:hover{background:#333}\
.dk-chat-send:disabled{opacity:.5}\
.dk-chat-status{font-size:11px;color:#888;text-align:center;padding:4px}\
@media(max-width:480px){\
.dk-chat-panel{right:0;bottom:80px;width:100%;max-width:100%;border-radius:12px 12px 0 0}\
.dk-chat-btn{bottom:16px;right:16px}\
}';
    document.head.appendChild(css);

    // --- Build DOM ---
    var btn = document.createElement('button');
    btn.className = 'dk-chat-btn';
    btn.innerHTML = '💬';
    btn.title = 'Live Chat';
    btn.setAttribute('aria-label', 'Live Chat öffnen');
    document.body.appendChild(btn);

    var panel = document.createElement('div');
    panel.className = 'dk-chat-panel';
    panel.innerHTML = '\
<div class="dk-chat-head">Live Chat <button class="dk-chat-close" aria-label="Schließen">&times;</button></div>\
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
  <div style="display:none"><input name="website"></div>\
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
        if (panel.classList.contains('open')) textArea.focus();
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
        if (msg) setTimeout(function () { statusDiv.textContent = ''; }, 4000);
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
        var now = new Date();
        var time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        div.innerHTML = escapeHtml(text) + '<div class="dk-msg-time">' + time + '</div>';
        msgsDiv.appendChild(div);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // --- Poll for admin replies every 15s ---
    var seenReplyIds = {};
    setInterval(function () {
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
