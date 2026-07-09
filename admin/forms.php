<?php
/**
 * Form submissions viewer.
 *
 * Lists all form submissions from the dedicated form pages.
 * Admin can view, mark as read, delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare("UPDATE form_submissions SET status = 'read' WHERE id = ?")->execute([$id]);
        header('Location: forms.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('DELETE FROM form_submissions WHERE id = ?')->execute([$id]);
        dk_flash('success', 'Eintrag gelöscht.');
        header('Location: forms.php');
        exit;
    }
}

$filter = (string)($_GET['filter'] ?? 'new');
$sql = 'SELECT * FROM form_submissions';
if ($filter === 'new') $sql .= " WHERE status = 'new'";
elseif ($filter === 'read') $sql .= " WHERE status = 'read'";
$sql .= ' ORDER BY created_at DESC';

$rows = dk_db()->query($sql)->fetchAll();
$newCount = (int)dk_db()->query("SELECT COUNT(*) FROM form_submissions WHERE status='new'")->fetchColumn();
$total = (int)dk_db()->query("SELECT COUNT(*) FROM form_submissions")->fetchColumn();

$pageTitle = 'Formulare';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Formulare <span class="dk-muted dk-count">(<?php echo $newCount; ?> neu · <?php echo $total; ?> gesamt)</span></h1>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <select name="filter" class="dk-input">
        <option value="new" <?php echo $filter === 'new' ? 'selected' : ''; ?>>Neu (<?php echo $newCount; ?>)</option>
        <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Gelesen</option>
        <option value="" <?php echo $filter === '' ? 'selected' : ''; ?>>Alle</option>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filtern</button>
</form>

<div class="dk-review-list">
<?php if (!$rows): ?>
    <div class="dk-card dk-empty">Keine Formular-Einträge.</div>
<?php endif; ?>
<?php foreach ($rows as $r):
    $data = json_decode($r['form_data'], true) ?: [];
?>
    <div class="dk-card <?php echo $r['status'] === 'new' ? 'dk-review-editing' : ''; ?>">
        <div class="dk-review-head">
            <strong><?php echo e($r['form_type']); ?></strong>
            <?php if ($r['status'] === 'new'): ?>
                <span class="dk-badge dk-badge-draft">Neu</span>
            <?php else: ?>
                <span class="dk-badge dk-badge-ok">Gelesen</span>
            <?php endif; ?>
        </div>
        <div class="dk-review-meta dk-muted">
            <?php echo e($r['visitor_name']); ?> · <?php echo e($r['visitor_email']); ?> · <?php echo e(dk_format_date($r['created_at'])); ?>
        </div>
        <table class="dk-table dk-table-compact" style="margin:12px 0">
            <tbody>
            <?php foreach ($data as $k => $v): ?>
                <tr>
                    <td style="width:180px;font-weight:600;color:#666"><?php echo e(ucfirst(str_replace('_', ' ', $k))); ?></td>
                    <td><?php echo e($v); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="dk-review-actions">
            <?php if ($r['status'] === 'new'): ?>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-ghost">Als gelesen markieren</button>
                </form>
            <?php endif; ?>
            <?php if ($r['visitor_whatsapp']): ?>
                <a href="https://wa.me/<?php echo e(preg_replace('/[^0-9+]/', '', $r['visitor_whatsapp'])); ?>" target="_blank" class="dk-btn dk-btn-sm">WhatsApp ↗</a>
            <?php endif; ?>
            <a href="mailto:<?php echo e($r['visitor_email']); ?>" class="dk-btn dk-btn-sm">E-Mail ↗</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Löschen?');">
                <?php echo dk_csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button type="submit" class="dk-btn dk-btn-sm dk-btn-danger">🗑</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
