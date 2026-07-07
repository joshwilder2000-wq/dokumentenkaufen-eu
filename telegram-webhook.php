<?php
/**
 * Telegram Webhook Receiver.
 *
 * Telegram sends POST requests here whenever the admin replies to a bot message.
 * We match the reply to the original chat message and route the reply back to:
 *   1. The visitor's chat widget (stored in chat_messages.admin_reply, picked up via poll)
 *   2. The visitor's email (via PHP mail())
 *
 * Set up with: https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://dokumentenkaufen.eu/telegram-webhook.php
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

// Telegram sends raw JSON.
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

// We only care about messages that are REPLIES (reply_to_message present).
$message = $update['message'] ?? null;
if (!$message) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$replyText = trim((string)($message['text'] ?? ''));
$replyTo   = $message['reply_to_message'] ?? null;

if (!$replyTo || $replyText === '') {
    http_response_code(200);
    echo 'OK';
    exit;
}

// The original Telegram message we sent has a telegram_message_id.
// Match it to our chat_messages row.
$originalTgMsgId = (string)($replyTo['message_id'] ?? '');

if ($originalTgMsgId === '') {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Find the chat message that this Telegram message corresponded to.
$stmt = dk_db()->prepare(
    'SELECT * FROM chat_messages WHERE telegram_message_id = ? ORDER BY id DESC LIMIT 1'
);
$stmt->execute([$originalTgMsgId]);
$chatMsg = $stmt->fetch();

if (!$chatMsg) {
    // Fallback: try to extract the Msg ID from the original message text.
    $origText = (string)($replyTo['text'] ?? '');
    if (preg_match('/Msg:\s*<code>(\d+)<\/code>/', $origText, $m)) {
        $stmt2 = dk_db()->prepare('SELECT * FROM chat_messages WHERE id = ? OR session_id = ? ORDER BY id DESC LIMIT 1');
        $stmt2->execute([(int)$m[1], $m[1]]);
        $chatMsg = $stmt2->fetch();
    }
}

if (!$chatMsg) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Store the admin's reply.
dk_db()->prepare(
    'UPDATE chat_messages SET admin_reply = ?, replied = 1, updated_at = datetime("now") WHERE id = ?'
)->execute([$replyText, (int)$chatMsg['id']]);

// Email the reply to the visitor.
if (!empty($chatMsg['visitor_email'])) {
    $subject = 'Antwort auf Ihre Live-Chat-Nachricht | Dokuments Hub';
    $emailBody = "Hallo " . $chatMsg['visitor_name'] . ",\n\n"
               . "Sie haben eine Antwort auf Ihre Nachricht erhalten:\n\n"
               . "\"{$replyText}\"\n\n"
               . "--\nDokuments Hub\nhttps://dokumentenkaufen.eu";
    $headers = "From: noreply@dokumentenkaufen.eu\r\n"
             . "Reply-To: leitung@akademischergrad.de\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($chatMsg['visitor_email'], $subject, $emailBody, $headers);
}

// Confirm to Telegram.
http_response_code(200);
echo 'OK';
