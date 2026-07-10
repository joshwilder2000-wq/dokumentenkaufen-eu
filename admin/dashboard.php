<?php
/**
 * Admin dashboard — card-based layout.
 *
 * Each product is a clean card showing image, title, description,
 * URL, category, and action buttons. Quick-edit popup embedded inline.
 * No table — fully responsive card grid.
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
$sql .= ' ORDER BY is_published DESC, title ASC';

$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();

$counts = dk_db()->query('SELECT COUNT(*) AS n FROM products')->fetch()['n'];
$published = dk_db()->query('SELECT COUNT(*) AS n FROM products WHERE is_published = 1')->fetch()['n'];
$csrfToken = dk_csrf_token();

$pageTitle = 'Produkte';
include __DIR__ . '/partials/header.php';
?>
<style>
/* ===== Card-based product grid ===== */
.dk-prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-top:20px}
.dk-prod-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:box-shadow .2s}
.dk-prod-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.dk-prod-card-top{display:flex;gap:14px;padding:16px}
.dk-prod-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;background:#f5f5f5;flex-shrink:0;border:1px solid #e0e0e0}
.dk-prod-thumb-empty{width:64px;height:64px;border-radius:8px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:.7rem;flex-shrink:0;border:1px solid #e0e0e0}
.dk-prod-info{flex:1;min-width:0}
.dk-prod-info h3{font-size:.95rem;font-weight:600;color:#000;margin:0 0 4px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.3}
.dk-prod-info p{font-size:.8rem;color:#888;margin:0;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.4}
.dk-prod-card-meta{display:flex;gap:8px;flex-wrap:wrap;padding:0 16px 12px;font-size:.72rem;color:#999}
.dk-prod-badge-sm{padding:2px 8px;border-radius:4px;font-weight:600;font-size:.68rem}
.dk-prod-badge-sm.pub{background:#dcfce7;color:#15803d}
.dk-prod-badge-sm.draft{background:#f3f4f6;color:#666}
.dk-prod-card-actions{display:flex;gap:4px;padding:10px 16px;border-top:1px solid #f0f0f0;background:#fafafa}
.dk-prod-card-actions a,.dk-prod-card-actions button{padding:8px 14px;border:1px solid #e0e0e0;border-radius:6px;font-size:.82rem;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:background .12s;font-family:inherit}
.dk-prod-card-actions a:hover,.dk-prod-card-actions button:hover{background:#f0f0f0}
.dk-prod-card-actions .dk-act-edit{background:#000;color:#fff;border-color:#000}
.dk-prod-card-actions .dk-act-edit:hover{background:#333}
.dk-prod-card-actions .dk-act-del:hover{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
.dk-prod-card-actions form{display:inline}
@media(max-width:600px){.dk-prod-grid{grid-template-columns:1fr}}
</style>

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

<div class="dk-prod-grid">
<?php if (!$products): ?>
    <div class="dk-card dk-empty">Keine Produkte gefunden.</div>
<?php endif; ?>

<?php foreach ($products as $p):
    $fullUrl = dk_site_url() . '/product/' . $p['slug'] . '.html';
    $pData = json_encode([
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'short_description' => $p['short_description'],
        'meta_description' => $p['meta_description'],
        'category' => $p['category'],
        'og_image' => $p['og_image'],
        'slug' => $p['slug'],
    ], JSON_HEX_APOS | JSON_HEX_QUOT);
?>
    <div class="dk-prod-card" data-pid="<?php echo (int)$p['id']; ?>" data-product='<?php echo htmlspecialchars($pData, ENT_QUOTES, 'UTF-8'); ?>'>
        <div class="dk-prod-card-top">
            <?php if ($p['og_image']): ?>
                <img src="../<?php echo e($p['og_image']); ?>" alt="" class="dk-prod-thumb" loading="lazy">
            <?php else: ?>
                <div class="dk-prod-thumb-empty">Kein Bild</div>
            <?php endif; ?>
            <div class="dk-prod-info">
                <h3><?php echo e($p['title']); ?></h3>
                <p><?php echo e($p['short_description'] ?: '—'); ?></p>
            </div>
        </div>
        <div class="dk-prod-card-meta">
            <span class="dk-prod-badge-sm <?php echo $p['is_published'] ? 'pub' : 'draft'; ?>"><?php echo $p['is_published'] ? '✓ Live' : 'Entwurf'; ?></span>
            <span><?php echo e(dk_categories()[$p['category']] ?? $p['category']); ?></span>
            <span>📍 <?php echo e($p['slug']); ?>.html</span>
            <span>🕒 <?php echo e(dk_format_date($p['updated_at'])); ?></span>
        </div>
        <div class="dk-prod-card-actions">
            <button type="button" class="dk-quickedit-btn dk-act-edit" data-pid="<?php echo (int)$p['id']; ?>">✎ Bearbeiten</button>
            <a href="../product/<?php echo e($p['slug']); ?>.html" target="_blank">↗ Ansehen</a>
            <a href="product-edit.php?id=<?php echo (int)$p['id']; ?>">⚙ Vollständig</a>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" class="dk-act-del" title="Löschen" onclick="return confirm('Produkt löschen?')">🗑</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ===== QUICK-EDIT MODAL ===== -->
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
        <button type="submit" id="qeSaveBtn" style="flex:1;padding:14px;background:#000;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font:inherit">💾 Speichern + Google</button>
        <a id="qeFullEdit" href="#" style="padding:14px 20px;background:#f5f5f5;color:#000;border:1px solid #e0e0e0;border-radius:10px;text-decoration:none;font-weight:600" target="_blank">⚙ Voll</a>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
    var modal = document.getElementById('qeModal');
    var csrf = '<?php echo e($csrfToken); ?>';
    var base = location.pathname.replace(/\/[^\/]+$/, '/');

    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest('.dk-quickedit-btn');
        if (!btn) return;
        var card = btn.closest('.dk-prod-card');
        if (!card) return;
        var raw = card.getAttribute('data-product');
        if (!raw) return;
        var d;
        try { d = JSON.parse(raw); } catch(e) { return; }

        document.getElementById('qeId').value = d.id;
        document.getElementById('qeTitle').textContent = d.title || 'Bearbeiten';
        document.getElementById('qeInputTitle').value = d.title || '';
        document.getElementById('qeInputShort').value = d.short_description || '';
        document.getElementById('qeInputMeta').value = d.meta_description || '';
        if (d.category) document.getElementById('qeInputCat').value = d.category;

        var imgBox = document.getElementById('qeImgBox');
        imgBox.innerHTML = d.og_image
            ? '<img src="../' + d.og_image + '" alt="" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #e0e0e0">'
            : '<span style="color:#999;font-size:.85rem">Kein Bild</span>';

        document.getElementById('qeFullEdit').href = base + 'product-edit.php?id=' + d.id;
        modal.style.display = 'block';

        var sb = document.getElementById('qeSaveBtn');
        sb.disabled = false;
        sb.textContent = '💾 Speichern + Google';
    });

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
                sb.textContent = '💾 Speichern + Google';
                alert(d.error || 'Fehler.');
            }
        })
        .catch(function() {
            sb.disabled = false;
            sb.textContent = '💾 Speichern + Google';
            alert('Netzwerkfehler.');
        });
    });

    modal.addEventListener('click', function(ev) {
        if (ev.target === modal) modal.style.display = 'none';
    });
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
