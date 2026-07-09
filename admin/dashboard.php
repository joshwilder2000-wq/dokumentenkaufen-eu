<?php
/**
 * Admin dashboard: product list with quick-edit popup.
 *
 * The quick-edit popup reads product data from HTML data-* attributes on each row.
 * No AJAX needed for opening — the data is already in the page.
 * Saving uses a simple form POST (no JS framework required).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

// --- AJAX quick-edit save ---
if (($_GET['ajax'] ?? '') === 'quick_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    dk_csrf_check();
    $id    = (int) ($_POST['id'] ?? 0);
    $title = dk_clean((string) ($_POST['title'] ?? ''));
    $short = dk_clean((string) ($_POST['short_description'] ?? ''));
    $metaDesc = dk_clean((string)($_POST['meta_description'] ?? ''));
    $category = dk_clean((string)($_POST['category'] ?? ''));

    if ($id <= 0 || $title === '') {
        echo json_encode(['ok' => false, 'error' => 'Ungültige Eingabe.']);
        exit;
    }

    $updateFields = 'title = ?, short_description = ?, meta_description = ?, updated_at = datetime("now")';
    $updateArgs = [$title, $short, $metaDesc];
    if ($category !== '' && array_key_exists($category, dk_categories())) {
        $updateFields = 'title = ?, short_description = ?, meta_description = ?, category = ?, updated_at = datetime("now")';
        $updateArgs[] = $category;
    }
    $updateArgs[] = $id;
    dk_db()->prepare("UPDATE products SET {$updateFields} WHERE id = ?")->execute($updateArgs);

    $fresh = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $fresh->execute([$id]);
    $row = $fresh->fetch();
    if ($row['is_published']) {
        dk_render_product($row);
    }

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

// --- Regular actions ---
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

// --- Read products ---
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
$csrfToken = dk_csrf_token();

$pageTitle = 'Produkte';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Produkte <span class="dk-muted dk-count">(<?php echo (int)$counts; ?> gesamt · <?php echo (int)$published; ?> veröffentlicht)</span></h1>
    <div class="dk-page-actions">
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
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
        // JSON-encode product data for the popup (safe embedding).
        $pData = json_encode([
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'short_description' => $p['short_description'],
            'meta_description' => $p['meta_description'],
            'category' => $p['category'],
            'og_image' => $p['og_image'],
            'slug' => $p['slug'],
        ], JSON_HEX_APOS | JSON_HEX_QUOT | ENT_QUOTES);
    ?>
        <tr class="<?php echo $p['is_published'] ? 'dk-row-pub' : 'dk-row-draft'; ?>"
            data-pid="<?php echo (int)$p['id']; ?>"
            data-product='<?php echo htmlspecialchars($pData, ENT_QUOTES, 'UTF-8'); ?>'>
            <td class="col-status">
                <span class="dk-dot <?php echo $p['is_published'] ? 'dk-dot-on' : 'dk-dot-off'; ?>"></span>
            </td>
            <td class="col-thumb">
                <?php if ($p['og_image']): ?>
                    <img src="../<?php echo e($p['og_image']); ?>" alt="" class="dk-thumb" loading="lazy">
                <?php else: ?>
                    <span class="dk-thumb dk-thumb-empty">—</span>
                <?php endif; ?>
            </td>
            <td class="col-title">
                <strong class="dk-row-title"><?php echo e($p['title']); ?></strong>
                <span class="dk-row-short"><?php echo e($p['short_description'] ?: '—'); ?></span>
            </td>
            <td class="col-url">
                <button type="button" class="dk-icon-btn dk-copy-btn" data-url="<?php echo e($fullUrl); ?>" title="URL kopieren">📋</button>
                <code class="dk-url-mini"><?php echo e($p['slug']); ?>.html</code>
            </td>
            <td class="col-cat"><?php echo e(dk_categories()[$p['category']] ?? $p['category']); ?></td>
            <td class="col-date dk-muted dk-updated"><?php echo e(dk_format_date($p['updated_at'])); ?></td>
            <td class="col-actions dk-actions">
                <button type="button" class="dk-icon-btn dk-quickedit-btn" title="Bearbeiten">✎</button>
                <a href="../product/<?php echo e($p['slug']); ?>.html" target="_blank" class="dk-icon-btn" title="Ansehen">↗</a>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle_publish">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="dk-icon-btn" title="Veröffentlichen/Verstecken"><?php echo $p['is_published'] ? '◐' : '○'; ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Produkt wirklich löschen?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
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

<!-- ===== QUICK-EDIT POPUP (self-contained, reads from table row data) ===== -->
<div id="qeModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5)">
  <div style="background:#fff;border-radius:12px;max-width:560px;width:calc(100% - 40px);max-height:90vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.3);margin:auto;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)">
    <div style="background:#000;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center">
      <strong id="qeTitle">Bearbeiten</strong>
      <button onclick="document.getElementById('qeModal').style.display='none'" style="background:none;border:none;color:#999;font-size:26px;cursor:pointer;line-height:1">&times;</button>
    </div>
    <form id="qeForm" method="post" style="padding:24px">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
      <input type="hidden" name="id" id="qeId" value="0">
      <div id="qeImgBox" style="text-align:center;margin-bottom:16px"></div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:5px;color:#333">Titel</label>
        <input type="text" id="qeInputTitle" style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit" required>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:5px;color:#333">Kurzbeschreibung</label>
        <textarea id="qeInputShort" rows="2" style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit;resize:vertical"></textarea>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:5px;color:#333">Meta-Beschreibung</label>
        <textarea id="qeInputMeta" rows="2" style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit;resize:vertical"></textarea>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:5px;color:#333">Kategorie</label>
        <select id="qeInputCat" style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit">
          <?php foreach (dk_categories() as $slug => $label): ?>
            <option value="<?php echo e($slug); ?>"><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" id="qeSaveBtn" style="flex:1;padding:14px;background:#000;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font:inherit">💾 Speichern + Google anpingen</button>
        <a id="qeFullEdit" href="#" style="padding:14px 20px;background:#f5f5f5;color:#000;border:1px solid #e0e0e0;border-radius:10px;text-decoration:none;font-weight:600" target="_blank">Vollständige Bearbeitung</a>
      </div>
    </form>
  </div>
</div>

<script>
// ===== Self-contained quick-edit (no external dependencies) =====
(function() {
    var modal = document.getElementById('qeModal');
    var csrf = '<?php echo e($csrfToken); ?>';
    var base = location.pathname.replace(/\/[^\/]+$/, '/');

    // Click any ✎ edit button → open popup with that product's data.
    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest('.dk-quickedit-btn');
        if (!btn) return;

        // Find the parent <tr> row and read embedded product data.
        var row = btn.closest('tr');
        if (!row) return;
        var raw = row.getAttribute('data-product');
        if (!raw) return;

        var d;
        try { d = JSON.parse(raw); } catch(e) { return; }

        // Populate the popup.
        document.getElementById('qeId').value = d.id;
        document.getElementById('qeTitle').textContent = d.title || 'Bearbeiten';
        document.getElementById('qeInputTitle').value = d.title || '';
        document.getElementById('qeInputShort').value = d.short_description || '';
        document.getElementById('qeInputMeta').value = d.meta_description || '';
        if (d.category) document.getElementById('qeInputCat').value = d.category;

        var imgBox = document.getElementById('qeImgBox');
        imgBox.innerHTML = d.og_image
            ? '<img src="../' + d.og_image + '" alt="" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #e0e0e0">'
            : '';

        document.getElementById('qeFullEdit').href = base + 'product-edit.php?id=' + d.id;

        // Show modal.
        modal.style.display = 'block';

        // Reset save button.
        var sb = document.getElementById('qeSaveBtn');
        sb.disabled = false;
        sb.textContent = '💾 Speichern + Google anpingen';
    });

    // Copy URL buttons.
    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest('.dk-copy-btn');
        if (!btn) return;
        var url = btn.getAttribute('data-url') || '';
        var ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        btn.textContent = '✓';
        setTimeout(function() { btn.textContent = '📋'; }, 1500);
    });

    // Form submit → AJAX save.
    document.getElementById('qeForm').addEventListener('submit', function(ev) {
        ev.preventDefault();
        var sb = document.getElementById('qeSaveBtn');
        sb.disabled = true;
        sb.textContent = '⏳ Speichern...';

        var body = new URLSearchParams();
        body.append('csrf_token', csrf);
        body.append('id', document.getElementById('qeId').value);
        body.append('title', document.getElementById('qeInputTitle').value.trim());
        body.append('short_description', document.getElementById('qeInputShort').value.trim());
        body.append('meta_description', document.getElementById('qeInputMeta').value.trim());
        body.append('category', document.getElementById('qeInputCat').value);

        fetch(base + 'dashboard.php?ajax=quick_edit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                modal.style.display = 'none';
                alert(d.pinged ? 'Gespeichert + Google angepingt!' : 'Gespeichert!');
                location.reload();
            } else {
                sb.disabled = false;
                sb.textContent = '💾 Speichern + Google anpingen';
                alert(d.error || 'Fehler beim Speichern.');
            }
        })
        .catch(function() {
            sb.disabled = false;
            sb.textContent = '💾 Speichern + Google anpingen';
            alert('Netzwerkfehler.');
        });
    });

    // Close on backdrop click.
    modal.addEventListener('click', function(ev) {
        if (ev.target === modal) modal.style.display = 'none';
    });
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
