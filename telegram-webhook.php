<?php
/**
 * Telegram Webhook Receiver — Bidirectional Reply Router.
 *
 * Handles admin replies from Telegram and routes them to the correct visitor:
 *
 * Case 1: Admin REPLIES to a specific bot message (reply_to_message present)
 *   → Match by telegram_message_id in DB → route to that session
 *
 * Case 2: Admin sends a standalone message (no reply_to_message)
 *   → Extract [SID:xxx] from the most recent bot message in the chat
 *   → Route to that session (most recent active chat)
 *
 * Case 3: Message contains [SID:xxx] in the text itself
 *   → Extract and route directly
 *
 * The reply is:
 *   1. Stored in chat_messages.admin_reply (picked up by visitor widget polling)
 *   2. Emailed to the visitor
 *   3. Sent to the visitor's WhatsApp (as a wa.me link in the admin's Telegram)
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

$rawInput = file_get_contents('php://input');
if (!$rawInput) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$update = json_decode($rawInput, true);
if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Ignore messages FROM the bot itself (prevent loops).
$fromId = $message['from']['id'] ?? 0;
$botInfo = $message['from']['is_bot'] ?? false;
if ($botInfo) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$replyText = trim((string)($message['text'] ?? ''));
if ($replyText === '') {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Strip any [SID:xxx] tags from the reply text (don't send tags to visitor).
$cleanReply = preg_replace('/\[SID:[^\]]+\]/', '', $replyText);
$cleanReply = trim($cleanReply);
if ($cleanReply === '') {
    http_response_code(200);
    echo 'OK';
    exit;
}

$targetSession = null;
$targetRow = null;

// --- Strategy 1: Reply to a specific message ---
$replyTo = $message['reply_to_message'] ?? null;
if ($replyTo) {
    $origTgMsgId = (string)($replyTo['message_id'] ?? '');
    $origText = (string)($replyTo['text'] ?? '');

    // Try matching by telegram_message_id stored in DB.
    if ($origTgMsgId) {
        $stmt = dk_db()->prepare(
            'SELECT * FROM chat_messages WHERE telegram_message_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$origTgMsgId]);
        $targetRow = $stmt->fetch();
    }

    // Fallback: extract [SID:xxx] from the original message text.
    if (!$targetRow && preg_match('/\[SID:([^\]]+)\]/', $origText, $sidMatch)) {
        $targetSession = $sidMatch[1];
    }
}

// --- Strategy 2: Extract [SID:xxx] from the admin's reply text itself ---
if (!$targetRow && !$targetSession) {
    if (preg_match('/\[SID:([^\]]+)\]/', $replyText, $sidMatch)) {
        $targetSession = $sidMatch[1];
    }
}

// --- Strategy 3: No match — try the most recent unreplied chat ---
if (!$targetRow && !$targetSession) {
    // Find the most recent message that hasn't been replied to yet.
    $stmt = dk_db()->query(
        "SELECT * FROM chat_messages WHERE replied = 0 ORDER BY created_at DESC LIMIT 1"
    );
    $targetRow = $stmt->fetch();
    if ($targetRow) {
        $targetSession = $targetRow['session_id'];
    }
}

// --- If we have a session but no row, find the latest row for it ---
if ($targetSession && !$targetRow) {
    $stmt = dk_db()->prepare(
        'SELECT * FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$targetSession]);
    $targetRow = $stmt->fetch();
}

if (!$targetRow) {
    // No matching chat found — send a hint to the admin.
    dk_send_telegram("⚠️ Keine aktive Chat-Sitzung gefunden.\n\nDie Nachricht konnte nicht zugeordnet werden. Bitte antworten Sie direkt auf eine Chat-Nachricht vom Bot.");
    http_response_code(200);
    echo 'OK';
    exit;
}

// --- Store the reply ---
dk_db()->prepare(
    'UPDATE chat_messages SET admin_reply = ?, replied = 1, updated_at = datetime("now") WHERE id = ?'
)->execute([$cleanReply, (int)$targetRow['id']]);

// --- Email the reply to the visitor ---
if (!empty($targetRow['visitor_email'])) {
    $subject = 'Antwort auf Ihre Live-Chat-Nachricht | Dokuments Hub';
    $emailBody = "Hallo " . $targetRow['visitor_name'] . ",\n\n"
               . "Sie haben eine Antwort auf Ihre Nachricht erhalten:\n\n"
               . "\"" . $cleanReply . "\"\n\n"
               . "--\nDokuments Hub\nhttps://dokumentenkaufen.eu";
    $headers = "From: noreply@dokumentenkaufen.eu\r\n"
             . "Reply-To: leitung@akademischergrad.de\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($targetRow['visitor_email'], $subject, $emailBody, $headers);
}

// --- Confirm to admin in Telegram ---
$confirmText = "✅ Antwort gesendet an <b>" . htmlspecialchars($targetRow['visitor_name']) . "</b>\n\n"
             . "📬 E-Mail: " . htmlspecialchars($targetRow['visitor_email']) . "\n"
             . "📱 WhatsApp: " . htmlspecialchars($targetRow['visitor_whatsapp'] ?: '(nicht angegeben)') . "\n\n"
             . "Ihre Antwort:\n\"" . htmlspecialchars($cleanReply) . "\"";
dk_send_telegram($confirmText);

http_response_code(200);
echo 'OK';
