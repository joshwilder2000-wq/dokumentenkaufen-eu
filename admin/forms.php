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
    } elseif ($action === 'generate_code') {
        $formSlug  = trim((string)($_POST['form_slug'] ?? ''));
        $maxUses   = (int)($_POST['max_uses'] ?? 2);
        $validHrs  = (int)($_POST['valid_hours'] ?? 72);
        if ($formSlug === '') {
            dk_flash('success', 'Bitte Formular wählen.');
        } else {
            $code = strtoupper(substr(md5(uniqid('', true)), 0, 6));
            $expires = date('Y-m-d H:i:s', strtotime("+{$validHrs} hours"));
            dk_db()->prepare(
                'INSERT INTO form_access_codes (form_slug, access_code, max_uses, uses_count, expires_at, is_active)
                 VALUES (?,?,?,0,?,1)'
            )->execute([$formSlug, $code, $maxUses, $expires]);
            dk_flash('success', "Code <strong>{$code}</strong> für {$formSlug} erstellt (gültig {$validHrs}h, max {$maxUses}x).");
        }
        header('Location: forms.php');
        exit;
    } elseif ($action === 'deactivate_code') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE form_access_codes SET is_active = 0 WHERE id = ?')->execute([$id]);
        dk_flash('success', 'Code deaktiviert.');
        header('Location: forms.php');
        exit;
    } elseif ($action === 'delete_code') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('DELETE FROM form_access_codes WHERE id = ?')->execute([$id]);
        dk_flash('success', 'Code gelöscht.');
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

// Fetch access codes for the UI.
$accessCodes = dk_db()->query('SELECT * FROM form_access_codes ORDER BY created_at DESC')->fetchAll();
$formSlugs = ['Hochschulabschluss', 'formular-fuer-sprachpruefungen', 'hwk-zeugnisvorform', 'ihk-zeugnisvorform', 'fuhrerscheinantragsformular', 'ausweisformular'];
?>
<div class="dk-page-head">
    <h1>Formulare <span class="dk-muted dk-count">(<?php echo $newCount; ?> neu · <?php echo $total; ?> gesamt)</span></h1>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo $msg; ?></div>
<?php endif; ?>

<!-- ===== Access Code Management ===== -->
<div class="dk-card">
    <h3>🔑 Zugangscode-Verwaltung</h3>
    <p class="dk-muted">Generieren Sie Zugriffs-Codes für einzelne Formulare. Codes gelten 72h, max. 2 Einreichungen pro Code.</p>
    <form method="post" class="dk-filters" style="flex-wrap:wrap">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="action" value="generate_code">
        <select name="form_slug" class="dk-input" required>
            <option value="">Formular wählen…</option>
            <?php foreach ($formSlugs as $fs): ?>
                <option value="<?php echo e($fs); ?>"><?php echo e($fs); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="max_uses" class="dk-input" style="max-width:120px">
            <option value="2">Max 2x</option>
            <option value="1">Max 1x</option>
            <option value="3">Max 3x</option>
        </select>
        <select name="valid_hours" class="dk-input" style="max-width:120px">
            <option value="72">72 Stunden</option>
            <option value="24">24 Stunden</option>
            <option value="48">48 Stunden</option>
            <option value="168">7 Tage</option>
        </select>
        <button type="submit" class="dk-btn dk-btn-primary">Code generieren</button>
    </form>

    <?php if ($accessCodes): ?>
    <div class="dk-table-wrap" style="margin-top:16px">
    <table class="dk-table dk-table-compact">
        <thead><tr>
            <th>Code</th><th>Formular</th><th>Nutzung</th><th>Gültig bis</th><th>Status</th><th>Aktion</th>
        </tr></thead>
        <tbody>
        <?php foreach ($accessCodes as $ac):
            $expired = $ac['expires_at'] !== '' && strtotime($ac['expires_at']) < time();
            $exhausted = (int)$ac['uses_count'] >= (int)$ac['max_uses'];
        ?>
            <tr>
                <td><code style="font-size:1.1rem;font-weight:700;letter-spacing:2px"><?php echo e($ac['access_code']); ?></code></td>
                <td><?php echo e($ac['form_slug']); ?></td>
                <td><?php echo (int)$ac['uses_count']; ?>/<?php echo (int)$ac['max_uses']; ?></td>
                <td class="dk-muted"><?php echo e($ac['expires_at']); ?></td>
                <td>
                    <?php if (!$ac['is_active']): ?>
                        <span class="dk-badge dk-badge-draft">Inaktiv</span>
                    <?php elseif ($expired): ?>
                        <span class="dk-badge dk-badge-rej">Abgelaufen</span>
                    <?php elseif ($exhausted): ?>
                        <span class="dk-badge dk-badge-rej">Aufgebraucht</span>
                    <?php else: ?>
                        <span class="dk-badge dk-badge-ok">Aktiv</span>
                    <?php endif; ?>
                </td>
                <td class="dk-actions">
                    <?php if ($ac['is_active']): ?>
                    <form method="post" style="display:inline">
                        <?php echo dk_csrf_field(); ?>
                        <input type="hidden" name="action" value="deactivate_code">
                        <input type="hidden" name="id" value="<?php echo (int)$ac['id']; ?>">
                        <button type="submit" class="dk-btn dk-btn-sm dk-btn-ghost" title="Deaktivieren">◐</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Code löschen?');">
                        <?php echo dk_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_code">
                        <input type="hidden" name="id" value="<?php echo (int)$ac['id']; ?>">
                        <button type="submit" class="dk-btn dk-btn-sm dk-btn-danger" title="Löschen">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

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
