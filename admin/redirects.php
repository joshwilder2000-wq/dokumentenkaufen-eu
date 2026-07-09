<?php
/**
 * Redirect management.
 *
 * Admin can add/edit/delete URL redirects. Stored in DB.
 * Also generates the .htaccess redirect rules so they work at the Apache level.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $source = trim((string)($_POST['source_path'] ?? ''));
        $target = trim((string)($_POST['target_url'] ?? ''));
        if ($source === '' || $target === '') {
            $errors[] = 'Quell-Pfad und Ziel-URL dürfen nicht leer sein.';
        } else {
            // Normalize: remove leading slash from source for consistency.
            $source = ltrim($source, '/');
            dk_db()->prepare(
                'INSERT OR REPLACE INTO redirects (source_path, target_url, status_code, is_active)
                 VALUES (?,?,301,1)'
            )->execute([$source, $target]);
            dk_regenerate_htaccess_redirects();
            dk_flash('success', 'Weiterleitung hinzugefügt: /' . e($source) . ' → ' . e($target));
            header('Location: redirects.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('DELETE FROM redirects WHERE id = ?')->execute([$id]);
        dk_regenerate_htaccess_redirects();
        dk_flash('success', 'Weiterleitung gelöscht.');
        header('Location: redirects.php');
        exit;
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE redirects SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        dk_regenerate_htaccess_redirects();
        header('Location: redirects.php');
        exit;
    }
}

$redirects = dk_db()->query('SELECT * FROM redirects ORDER BY source_path ASC')->fetchAll();

$pageTitle = 'Weiterleitungen';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head"><h1>Weiterleitungen</h1></div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<div class="dk-card">
    <h3>Neue Weiterleitung hinzufügen</h3>
    <form method="post" class="dk-filters" style="flex-wrap:wrap">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <input type="text" name="source_path" class="dk-input" placeholder="Quell-Pfad (z.B. product/fuhrerschein-kaufen)" style="min-width:280px;flex:1" required>
        <input type="text" name="target_url" class="dk-input" placeholder="Ziel-URL (z.B. https://example.com/)" style="min-width:280px;flex:1" required>
        <button type="submit" class="dk-btn dk-btn-primary">Hinzufügen</button>
    </form>
</div>

<div class="dk-table-wrap">
<table class="dk-table dk-table-compact">
    <thead>
        <tr>
            <th>Quell-Pfad</th>
            <th>Ziel-URL</th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$redirects): ?>
        <tr><td colspan="4" class="dk-empty">Keine Weiterleitungen.</td></tr>
    <?php endif; ?>
    <?php foreach ($redirects as $r): ?>
        <tr>
            <td><code>/<?php echo e($r['source_path']); ?></code></td>
            <td><a href="<?php echo e($r['target_url']); ?>" target="_blank"><?php echo e($r['target_url']); ?></a></td>
            <td>
                <?php if ($r['is_active']): ?>
                    <span class="dk-badge dk-badge-ok">Aktiv</span>
                <?php else: ?>
                    <span class="dk-badge dk-badge-draft">Inaktiv</span>
                <?php endif; ?>
            </td>
            <td class="dk-actions">
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button type="submit" class="dk-icon-btn" title="<?php echo $r['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>"><?php echo $r['is_active'] ? '◐' : '○'; ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Löschen?');">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button type="submit" class="dk-icon-btn dk-icon-danger" title="Löschen">🗑</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php include __DIR__ . '/partials/footer.php';

/**
 * Regenerate the .htaccess redirect block from the DB.
 * Reads the current .htaccess, replaces the managed redirect block, writes back.
 */
function dk_regenerate_htaccess_redirects(): void
{
    $redirects = dk_db()->query("SELECT * FROM redirects WHERE is_active = 1 ORDER BY source_path ASC")->fetchAll();
    $rules = "";
    foreach ($redirects as $r) {
        $source = preg_quote($r['source_path'], '#');
        $target = str_replace("'", "\\'", $r['target_url']);
        $rules .= "RewriteRule ^{$r['source_path']}(/|\\.html)?$ {$r['target_url']} [L,R=301]\n";
    }

    $htaccess = dk_site_root() . '/.htaccess';
    $content = file_get_contents($htaccess);
    if ($content === false) return;

    $marker = '# ===== DB-managed redirects =====';
    $endMarker = '# ===== END DB redirects =====';

    // Remove existing block.
    $content = preg_replace('#' . preg_quote($marker, '#') . '.*?' . preg_quote($endMarker, '#') . "\s*#s", '', $content);

    // Insert before the trailing-slash section.
    $insertBefore = '# ===== Trailing-slash';
    $block = $marker . "\n" . $rules . $endMarker . "\n\n";
    $content = str_replace($insertBefore, $block . $insertBefore, $content);

    file_put_contents($htaccess, $content, LOCK_EX);
}
