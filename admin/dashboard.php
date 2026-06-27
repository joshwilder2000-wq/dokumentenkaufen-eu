<?php
/**
 * Admin dashboard: product list.
 *
 * Features:
 *   - Horizontal scroll on small screens so all action buttons stay reachable.
 *   - Copy-URL button (copies the full product URL to clipboard).
 *   - Inline quick-edit: edit title + short description without leaving the page,
 *     saves via AJAX, re-renders the product, and pings Google.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

// ---------------------------------------------------------------------------
// AJAX quick-edit endpoint (returns JSON).
// ---------------------------------------------------------------------------
if (($_GET['ajax'] ?? '') === 'quick_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    dk_csrf_check();

    $id    = (int) ($_POST['id'] ?? 0);
    $title = dk_clean((string) ($_POST['title'] ?? ''));
    $short = dk_clean((string) ($_POST['short_description'] ?? ''));

    if ($id <= 0 || $title === '') {
        echo json_encode(['ok' => false, 'error' => 'Ungültige Eingabe.']);
        exit;
    }

    $stmt = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        echo json_encode(['ok' => false, 'error' => 'Produkt nicht gefunden.']);
        exit;
    }

    dk_db()->prepare(
        'UPDATE products SET title = ?, short_description = ?, updated_at = datetime("now") WHERE id = ?'
    )->execute([$title, $short, $id]);

    // Re-render the product page.
    $fresh = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $fresh->execute([$id]);
    $row = $fresh->fetch();
    if ($row['is_published']) {
        dk_render_product($row);
    }

    // Ping Google about the updated URL.
    $pinged = false;
    if ($row['is_published']) {
        $pinged = dk_ping_google(dk_site_url() . '/product/' . rawurlencode($row['slug']) . '.html');
    }

    echo json_encode([
        'ok' => true,
        'title' => $row['title'],
        'short_description' => $row['short_description'],
        'updated_at' => dk_format_date($row['updated_at']),
        'pinged' => $pinged,
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Handle regular (non-AJAX) actions.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') !== 'quick_edit') {
    dk_csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = dk_db()->prepare('SELECT slug FROM products WHERE id = ?');
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) {
                dk_remove_product_file((string) $row['slug']);
            }
            dk_db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            require_once __DIR__ . '/sitemap-builder.php';
            dk_rebuild_all_sitemaps();
            dk_flash('success', 'Produkt gelöscht.');
        }
    } elseif ($action === 'toggle_publish') {
        $id = (int) ($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE products SET is_published = 1 - is_published, updated_at = datetime("now") WHERE id = ?')
            ->execute([$id]);
        require_once __DIR__ . '/sitemap-builder.php';
        dk_rebuild_all_sitemaps();
        dk_flash('success', 'Veröffentlichungsstatus geändert.');
    } elseif ($action === 'rebuild_sitemaps') {
        require_once __DIR__ . '/sitemap-builder.php';
        $count = dk_rebuild_all_sitemaps();
        dk_flash('success', "Sitemaps neu erstellt ({$count} Produkte).");
    }

    header('Location: dashboard.php');
    exit;
}

// ---------------------------------------------------------------------------
// Read products.
// ---------------------------------------------------------------------------
$search = trim((string) ($_GET['q'] ?? ''));
$cat    = trim((string) ($_GET['cat'] ?? ''));

$sql = 'SELECT * FROM products';
$where = [];
$args = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR slug LIKE ?)';
    $args[] = "%{$search}%";
    $args[] = "%{$search}%";
}
if ($cat !== '' && array_key_exists($cat, dk_categories())) {
    $where[] = 'category = ?';
    $args[] = $cat;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY is_published DESC, sort_order ASC, title ASC';

$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();

$counts = dk_db()->query('SELECT COUNT(*) AS n FROM products')->fetch()['n'];
$published = dk_db()->query('SELECT COUNT(*) AS n FROM products WHERE is_published = 1')->fetch()['n'];

$pageTitle = 'Produkte';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Produkte <span class="dk-muted dk-count">(<?php echo (int)$counts; ?> gesamt · <?php echo (int)$published; ?> veröffentlicht)</span></h1>
    <div class="dk-page-actions">
        <form method="post" style="display:inline">
            <?php echo dk_csrf_field(); ?>
            <input type="hidden" name="action" value="rebuild_sitemaps">
            <button type="submit" class="dk-btn dk-btn-ghost">⟳ Sitemaps</button>
        </form>
        <a href="product-edit.php" class="dk-btn dk-btn-primary">+ Neues Produkt</a>
    </div>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Produkt suchen…" class="dk-input">
    <select name="cat" class="dk-input">
        <option value="">Alle Kategorien</option>
        <?php foreach (dk_categories() as $slug => $label): ?>
            <option value="<?php echo e($slug); ?>" <?php echo $cat === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filtern</button>
    <?php if ($search !== '' || $cat !== ''): ?>
        <a href="dashboard.php" class="dk-btn dk-btn-link">Zurücksetzen</a>
    <?php endif; ?>
</form>

<div class="dk-scroll-wrap">
<div class="dk-table-wrap">
<table class="dk-table dk-table-compact">
    <thead>
        <tr>
            <th class="col-status">●</th>
            <th class="col-thumb">Bild</th>
            <th class="col-title">Titel / Kurzbeschreibung</th>
            <th class="col-url">URL</th>
            <th class="col-cat">Kategorie</th>
            <th class="col-date">Geändert</th>
            <th class="col-actions">Aktionen</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$products): ?>
        <tr><td colspan="7" class="dk-empty">Keine Produkte gefunden.</td></tr>
    <?php endif; ?>
    <?php foreach ($products as $p):
        $fullUrl = dk_site_url() . '/product/' . $p['slug'] . '.html';
    ?>
        <tr class="<?php echo $p['is_published'] ? 'dk-row-pub' : 'dk-row-draft'; ?>" data-id="<?php echo (int)$p['id']; ?>">
            <td class="col-status">
                <span class="dk-dot <?php echo $p['is_published'] ? 'dk-dot-on' : 'dk-dot-off'; ?>"
                      title="<?php echo $p['is_published'] ? 'Veröffentlicht' : 'Entwurf'; ?>"></span>
            </td>
            <td class="col-thumb">
                <?php if ($p['og_image']): ?>
                    <img src="../<?php echo e($p['og_image']); ?>" alt="" class="dk-thumb" loading="lazy">
                <?php else: ?>
                    <span class="dk-thumb dk-thumb-empty">—</span>
                <?php endif; ?>
            </td>
            <td class="col-title">
                <div class="dk-quick-target" data-field="title"><strong class="dk-row-title"><?php echo e($p['title']); ?></strong></div>
                <div class="dk-quick-target" data-field="short_description"><span class="dk-row-short"><?php echo e($p['short_description'] ?: '—'); ?></span></div>
            </td>
            <td class="col-url">
                <button type="button" class="dk-icon-btn dk-copy-btn" data-url="<?php echo e($fullUrl); ?>" title="URL kopieren">📋</button>
                <code class="dk-url-mini"><?php echo e($p['slug']); ?>.html</code>
            </td>
            <td class="col-cat"><?php echo e(dk_categories()[$p['category']] ?? $p['category']); ?></td>
            <td class="col-date dk-muted dk-updated"><?php echo e(dk_format_date($p['updated_at'])); ?></td>
            <td class="col-actions dk-actions">
                <a href="product-edit.php?id=<?php echo (int)$p['id']; ?>" class="dk-icon-btn" title="Vollständige Bearbeitung">✎</a>
                <a href="../product/<?php echo e($p['slug']); ?>.html" target="_blank" class="dk-icon-btn" title="Ansehen">↗</a>
                <button type="button" class="dk-icon-btn dk-quickedit-btn" data-id="<?php echo (int)$p['id']; ?>" title="Schnellbearbeitung (Titel + Kurztext)">⚡</button>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_publish">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="dk-icon-btn" title="<?php echo $p['is_published'] ? 'Verstecken' : 'Veröffentlichen'; ?>"><?php echo $p['is_published'] ? '◐' : '○'; ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Produkt wirklich löschen? Die HTML-Datei wird entfernt.');">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="dk-icon-btn dk-icon-danger" title="Löschen">🗑</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<script src="assets/admin.js?v=<?php echo date('Ymd'); ?>" defer></script>
<script>
window.DK_CSRF = '<?php echo dk_csrf_token(); ?>';
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
