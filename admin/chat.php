<?php
/**
 * Live Chat admin — view visitor messages and reply.
 *
 * Lists all chat messages (filterable: unread/replied/all).
 * Admin can reply to a message; the reply is stored and picked up by the visitor widget.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// --- Handle reply ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'reply') {
        $id    = (int)($_POST['id'] ?? 0);
        $reply = trim((string)($_POST['reply'] ?? ''));
        if ($id > 0 && $reply !== '') {
            dk_db()->prepare(
                'UPDATE chat_messages SET admin_reply = ?, replied = 1, is_read = 1, updated_at = datetime("now") WHERE id = ?'
            )->execute([$reply, $id]);
            dk_flash('success', 'Antwort gesendet. Der Besucher sieht sie im Chat-Widget.');
        }
        header('Location: chat.php');
        exit;
    } elseif ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE chat_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
        header('Location: chat.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('DELETE FROM chat_messages WHERE id = ?')->execute([$id]);
        dk_flash('success', 'Nachricht gelöscht.');
        header('Location: chat.php');
        exit;
    }
}

// --- Read messages ---
$filter = (string)($_GET['filter'] ?? 'unread');
$sql = 'SELECT * FROM chat_messages';
$args = [];
if ($filter === 'unread') {
    $sql .= ' WHERE is_read = 0';
} elseif ($filter === 'replied') {
    $sql .= ' WHERE replied = 1';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$messages = $stmt->fetchAll();

$unread = (int) dk_db()->query("SELECT COUNT(*) FROM chat_messages WHERE is_read = 0")->fetchColumn();
$total  = (int) dk_db()->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();

$pageTitle = 'Live Chat';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Live Chat <span class="dk-muted dk-count">(<?php echo $unread; ?> ungelesen · <?php echo $total; ?> gesamt)</span></h1>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<?php if (dk_setting('telegram_bot_token', '') === ''): ?>
    <div class="dk-alert dk-alert-error">
        ⚠️ <strong>Telegram-Bot nicht konfiguriert.</strong> Nachrichten werden nur hier gespeichert.
        Um sie an Telegram weiterzuleiten, unter <a href="settings.php">Einstellungen</a> den Bot-Token eintragen.
    </div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <select name="filter" class="dk-input">
        <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Ungelesen (<?php echo $unread; ?>)</option>
        <option value="replied" <?php echo $filter === 'replied' ? 'selected' : ''; ?>>Beantwortet</option>
        <option value="" <?php echo $filter === '' ? 'selected' : ''; ?>>Alle</option>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filtern</button>
</form>

<div class="dk-review-list">
<?php if (!$messages): ?>
    <div class="dk-card dk-empty">Keine Chat-Nachrichten.</div>
<?php endif; ?>
<?php foreach ($messages as $m): ?>
    <div class="dk-card <?php echo !$m['is_read'] ? 'dk-review-editing' : ''; ?>">
        <div class="dk-review-head">
            <strong><?php echo e($m['visitor_name'] ?: 'Anonym'); ?></strong>
            <?php if ($m['visitor_email']): ?>
                <span class="dk-muted"><?php echo e($m['visitor_email']); ?></span>
            <?php endif; ?>
            <?php if (!$m['is_read']): ?>
                <span class="dk-badge dk-badge-draft">Neu</span>
            <?php endif; ?>
            <?php if ($m['replied']): ?>
                <span class="dk-badge dk-badge-ok">Beantwortet</span>
            <?php endif; ?>
        </div>
        <div class="dk-review-meta dk-muted">
            Session: <code><?php echo e($m['session_id']); ?></code>
            · <?php echo e(dk_format_date($m['created_at'])); ?>
        </div>
        <p class="dk-review-body"><?php echo nl2br(e($m['message'])); ?></p>

        <?php if ($m['admin_reply']): ?>
            <p class="dk-review-body" style="background:#dcfce7;padding:10px;border-radius:6px;border:1px solid #bbf7d0">
                <strong>Antwort:</strong> <?php echo nl2br(e($m['admin_reply'])); ?>
            </p>
        <?php endif; ?>

        <?php if (!$m['replied']): ?>
            <form method="post" style="margin-top:8px">
                <?php echo dk_csrf_field(); ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                <div class="dk-field" style="margin:0">
                    <textarea name="reply" rows="2" placeholder="Antwort an den Besucher…" required style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font:inherit"></textarea>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-primary">Antworten</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="dk-review-actions">
            <?php if (!$m['is_read']): ?>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-ghost">Als gelesen markieren</button>
                </form>
            <?php endif; ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Nachricht löschen?');">
                <?php echo dk_csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                <button type="submit" class="dk-btn dk-btn-sm dk-btn-danger">🗑 Löschen</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
