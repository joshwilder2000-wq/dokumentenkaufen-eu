<?php
/**
 * Live chat endpoint (public).
 *
 * Modes:
 *   POST         → store a visitor message (+ forward to Telegram with reply markup)
 *   GET ?poll=1  → return admin replies for a session (for the widget to poll)
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// --- GET: poll for admin replies ---
if (($_GET['poll'] ?? '') === '1') {
    $sessionId = substr(trim((string)($_GET['session'] ?? '')), 0, 64);
    if ($sessionId === '') {
        echo json_encode(['ok' => true, 'replies' => []]);
        exit;
    }

    // Mark messages as read (visitor has seen the thread).
    dk_db()->prepare('UPDATE chat_messages SET is_read = 1 WHERE session_id = ?')
        ->execute([$sessionId]);

    $stmt = dk_db()->prepare(
        "SELECT id, admin_reply, updated_at FROM chat_messages
         WHERE session_id = ? AND replied = 1 AND admin_reply != ''
         ORDER BY updated_at ASC LIMIT 50"
    );
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll();

    $replies = [];
    foreach ($rows as $r) {
        $replies[] = [
            'id'   => (int)$r['id'],
            'text' => (string)$r['admin_reply'],
            'time' => (string)$r['updated_at'],
        ];
    }

    echo json_encode(['ok' => true, 'replies' => $replies]);
    exit;
}

// --- POST: store a new message ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Honeypot.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true, 'fake' => true]);
    exit;
}

$sessionId = substr(trim((string)($_POST['session_id'] ?? '')), 0, 64);
$name      = trim((string)($_POST['name'] ?? ''));
$email     = trim((string)($_POST['email'] ?? ''));
$emailConf = trim((string)($_POST['email_confirm'] ?? ''));
$message   = trim((string)($_POST['message'] ?? ''));

if ($sessionId === '') {
    $sessionId = 'v' . substr(md5(uniqid('', true)), 0, 12);
}
if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Bitte Ihren Namen angeben.']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
    exit;
}
if (strtolower($email) !== strtolower($emailConf)) {
    echo json_encode(['ok' => false, 'error' => 'Die E-Mail-Adressen stimmen nicht überein.']);
    exit;
}
if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'Nachricht darf nicht leer sein.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    echo json_encode(['ok' => false, 'error' => 'Nachricht zu lang (max. 2000 Zeichen).']);
    exit;
}

// Rate limit: max 5 messages per IP per minute.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($ip !== 'unknown') {
    $stmt = dk_db()->prepare(
        "SELECT COUNT(*) FROM chat_messages WHERE session_id = ? AND created_at > datetime('now', '-1 minute')"
    );
    $stmt->execute([$sessionId]);
    if ((int)$stmt->fetchColumn() >= 5) {
        echo json_encode(['ok' => false, 'error' => 'Zu viele Nachrichten. Bitte warten Sie einen Moment.']);
        exit;
    }
}

// Store in DB.
dk_db()->prepare(
    'INSERT INTO chat_messages (session_id, visitor_name, visitor_email, message, visitor_confirmed)
     VALUES (?,?,?,?,1)'
)->execute([$sessionId, $name, $email, $message]);

$msgId = (int) dk_db()->lastInsertId();

// Forward to Telegram with reply markup (ForceReply so admin can reply directly).
$tgText = "💬 <b>Neue Live-Chat-Nachricht</b>\n\n"
        . "👤 <b>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</b>\n"
        . "📧 " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "\n"
        . "🔑 Session: <code>" . htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8') . "</code>\n"
        . "🆔 Msg: <code>" . $msgId . "</code>\n\n"
        . "💬 " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n\n"
        . "<i>Antworte direkt hier → die Antwort geht an den Besucher.</i>";

// Use ForceReply so Telegram prompts the admin to reply to this specific message.
$replyMarkup = [
    'force_reply'       => true,
    'selective'         => false,
    'input_field_placeholder' => 'Antwort an ' . $name . '...',
];

$tgMsgId = dk_send_telegram($tgText, $replyMarkup);
if ($tgMsgId !== '') {
    dk_db()->prepare('UPDATE chat_messages SET telegram_message_id = ? WHERE id = ?')
        ->execute([$tgMsgId, $msgId]);
}

echo json_encode(['ok' => true, 'session_id' => $sessionId]);
