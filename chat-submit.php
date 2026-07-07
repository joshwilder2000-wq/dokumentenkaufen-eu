<?php
/**
 * Live chat endpoint (public).
 *
 * Handles two modes:
 *   POST → store a new chat message from a visitor (+ forward to Telegram).
 *   GET  ?poll=1 → return admin replies for a session (for the chat widget to poll).
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

    $stmt = dk_db()->prepare(
        "SELECT id, admin_reply, updated_at FROM chat_messages
         WHERE session_id = ? AND replied = 1 AND admin_reply != ''
         ORDER BY updated_at DESC LIMIT 20"
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
$message   = trim((string)($_POST['message'] ?? ''));

if ($sessionId === '') {
    $sessionId = 'anon-' . substr(md5(uniqid('', true)), 0, 12);
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
        "SELECT COUNT(*) FROM chat_messages WHERE visitor_email = ? AND created_at > datetime('now', '-1 minute')"
    );
    // Use IP as the rate-limit key (stored in visitor_email field if email empty, else use session).
    $stmt->execute(['ip:' . $ip]);
    if ((int)$stmt->fetchColumn() >= 5) {
        echo json_encode(['ok' => false, 'error' => 'Zu viele Nachrichten. Bitte warten Sie einen Moment.']);
        exit;
    }
}

// Store in DB.
dk_db()->prepare(
    'INSERT INTO chat_messages (session_id, visitor_name, visitor_email, message)
     VALUES (?,?,?,?)'
)->execute([$sessionId, $name, $email, $message]);

// Forward to Telegram (if bot token configured).
$displayName = $name ?: 'Anonym';
$displayEmail = $email !== '' ? "\n📧 {$email}" : '';
$tgText = "💬 <b>Neue Live-Chat-Nachricht</b>\n\n"
        . "👤 <b>{$displayName}</b>{$displayEmail}\n"
        . "🔑 Session: <code>{$sessionId}</code>\n\n"
        . "💬 " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

$tgMsgId = dk_send_telegram($tgText);
if ($tgMsgId !== '') {
    // Store the telegram message_id for potential reply threading.
    $lastId = (int) dk_db()->lastInsertId();
    dk_db()->prepare('UPDATE chat_messages SET telegram_message_id = ? WHERE id = ?')
        ->execute([$tgMsgId, $lastId]);
}

echo json_encode(['ok' => true, 'session_id' => $sessionId]);
