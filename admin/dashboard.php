<?php
/**
 * Admin dashboard — card grid layout (English admin UI).
 * Quick-edit popup is responsive and locks background scroll.
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
        echo json_encode(['ok' => false, 'error' => 'Invalid input.']);
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
            dk_flash('success', 'Product deleted.');
        }
    } elseif ($action === 'toggle_publish') {
        $id = (int) ($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE products SET is_published = 1 - is_published, updated_at = datetime("now") WHERE id = ?')
            ->execute([$id]);
        require_once __DIR__ . '/sitemap-builder.php';
        dk_rebuild_all_sitemaps();
        dk_flash('success', 'Publish status changed.');
    } elseif ($action === 'rebuild_sitemaps') {
        require_once __DIR__ . '/sitemap-builder.php';
        $count = dk_rebuild_all_sitemaps();
        dk_flash('success', "Sitemaps rebuilt ({$count} products).");
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
$sql .= ' ORDER BY is_published DESC, (COALESCE(impressions,0) + COALESCE(clicks,0)) DESC, title ASC';

$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();

$counts = dk_db()->query('SELECT COUNT(*) AS n FROM products')->fetch()['n'];
$published = dk_db()->query('SELECT COUNT(*) AS n FROM products WHERE is_published = 1')->fetch()['n'];
$csrfToken = dk_csrf_token();

$pageTitle = 'Products';
include __DIR__ . '/partials/header.php';
?>
<style>
/* Card grid */
.dk-prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-top:20px}
.dk-prod-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:box-shadow .2s}
.dk-prod-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.dk-prod-card-top{display:flex;gap:14px;padding:16px}
.dk-prod-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;background:#f5f5f5;flex-shrink:0;border:1px solid #e0e0e0}
.dk-prod-thumb-empty{width:64px;height:64px;border-radius:8px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:.7rem;flex-shrink:0;border:1px solid #e0e0e0}
.dk-prod-info{flex:1;min-width:0}
.dk-prod-info h3{font-size:.95rem;font-weight:600;color:#000;margin:0 0 4px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dk-prod-info p{font-size:.8rem;color:#888;margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dk-prod-card-meta{display:flex;gap:8px;flex-wrap:wrap;padding:0 16px 12px;font-size:.72rem;color:#999}
.dk-prod-badge-sm{padding:2px 8px;border-radius:4px;font-weight:600;font-size:.68rem}
.dk-prod-badge-sm.pub{background:#dcfce7;color:#15803d}
.dk-prod-badge-sm.draft{background:#f3f4f6;color:#666}
.dk-prod-url-copy{cursor:pointer;transition:background .12s;border-radius:4px;padding:2px 6px}
.dk-prod-url-copy:hover{background:#e0e0e0;color:#333}
.dk-prod-url-copy.copied{background:#dcfce7;color:#15803d;font-weight:600}
.dk-prod-card-actions{display:flex;gap:4px;padding:10px 16px;border-top:1px solid #f0f0f0;background:#fafafa;flex-wrap:wrap}
.dk-prod-card-actions a,.dk-prod-card-actions button{padding:8px 14px;border:1px solid #e0e0e0;border-radius:6px;font-size:.82rem;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:background .12s;font-family:inherit;background:#fff}
.dk-prod-card-actions a:hover,.dk-prod-card-actions button:hover{background:#f0f0f0}
.dk-prod-card-actions .dk-act-edit{background:#000;color:#fff;border-color:#000}
.dk-prod-card-actions .dk-act-edit:hover{background:#333}
.dk-prod-card-actions .dk-act-del:hover{background:#fee2e2;border-color:#fecaca;color:#b91c1c}

/* Responsive modal */
#qeOverlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);display:none;align-items:flex-start;justify-content:center;padding:5vh 20px;overflow-y:auto;-webkit-overflow-scrolling:touch}
#qeOverlay.show{display:flex}
#qeBox{background:#fff;border-radius:14px;max-width:560px;width:100%;box-shadow:0 16px 48px rgba(0,0,0,.3);margin-bottom:5vh}
#qeHead{background:#000;color:#fff;padding:18px 22px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10}
#qeHead strong{font-size:1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:calc(100% - 40px)}
#qeCloseBtn{background:none;border:none;color:rgba(255,255,255,.6);font-size:28px;cursor:pointer;line-height:1;flex-shrink:0;padding:0 4px}
#qeCloseBtn:hover{color:#fff}
#qeBody{padding:24px}
#qeBody .qe-field{margin-bottom:16px}
#qeBody .qe-field label{display:block;font-weight:600;font-size:.85rem;color:#333;margin-bottom:6px}
#qeBody .qe-field input,#qeBody .qe-field select,#qeBody .qe-field textarea{width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit;font-size:.95rem;background:#fafafa;box-sizing:border-box}
#qeBody .qe-field input:focus,#qeBody .qe-field select:focus,#qeBody .qe-field textarea:focus{outline:none;border-color:#000;background:#fff}
.qe-actions{display:flex;gap:10px;margin-top:24px}
.qe-save{flex:1;padding:14px;background:#000;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font:inherit}
.qe-save:hover{opacity:.88}
.qe-save:disabled{opacity:.4;cursor:default}
.qe-full{padding:14px 20px;background:#f5f5f5;color:#000;border:1px solid #e0e0e0;border-radius:10px;text-decoration:none;font-weight:600}
@media(max-width:600px){.dk-prod-grid{grid-template-columns:1fr} #qeOverlay{padding:0} #qeBox{border-radius:0;min-height:100vh;margin:0}}
</style>

<div class="dk-page-head">
    <h1>Products <span class="dk-muted dk-count">(<?php echo (int)$counts; ?> total · <?php echo (int)$published; ?> published)</span></h1>
    <div class="dk-page-actions">
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="rebuild_sitemaps">
            <button type="submit" class="dk-btn dk-btn-ghost">⟳ Sitemaps</button>
        </form>
        <a href="product-edit.php" class="dk-btn dk-btn-primary">+ New Product</a>
    </div>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search products…" class="dk-input">
    <select name="cat" class="dk-input">
        <option value="">All Categories</option>
        <?php foreach (dk_categories() as $slug => $label): ?>
            <option value="<?php echo e($slug); ?>" <?php echo $cat === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filter</button>
    <?php if ($search !== '' || $cat !== ''): ?>
        <a href="dashboard.php" class="dk-btn dk-btn-link">Reset</a>
    <?php endif; ?>
</form>

<div class="dk-prod-grid">
<?php if (!$products): ?>
    <div class="dk-card dk-empty">No products found.</div>
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
                <div class="dk-prod-thumb-empty">No Image</div>
            <?php endif; ?>
            <div class="dk-prod-info">
                <h3><?php echo e($p['title']); ?></h3>
                <p><?php echo e($p['short_description'] ?: '—'); ?></p>
            </div>
        </div>
        <div class="dk-prod-card-meta">
            <span class="dk-prod-badge-sm <?php echo $p['is_published'] ? 'pub' : 'draft'; ?>"><?php echo $p['is_published'] ? '✓ Live' : 'Draft'; ?></span>
            <span><?php echo e(dk_categories()[$p['category']] ?? $p['category']); ?></span>
            <span class="dk-prod-url-copy" data-url="<?php echo e($fullUrl); ?>" title="Click to copy full URL">📍 <?php echo e($p['slug']); ?>.html</span>
            <span>👁 <?php echo (int)($p['impressions'] ?? 0); ?> views</span>
            <span>🖱 <?php echo (int)($p['clicks'] ?? 0); ?> clicks</span>
            <span>🕒 <?php echo e(dk_format_date($p['updated_at'])); ?></span>
        </div>
        <div class="dk-prod-card-actions">
            <button type="button" class="dk-quickedit-btn dk-act-edit" data-pid="<?php echo (int)$p['id']; ?>">✎ Edit</button>
            <a href="../product/<?php echo e($p['slug']); ?>.html" target="_blank">↗ View</a>
            <a href="product-edit.php?id=<?php echo (int)$p['id']; ?>">⚙ Full Edit</a>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" class="dk-act-del" title="Delete" onclick="return confirm('Delete product?')">🗑</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ===== QUICK-EDIT MODAL (responsive, scroll-locked) ===== -->
<div id="qeOverlay">
  <div id="qeBox">
    <div id="qeHead">
      <strong id="qeTitle">Edit Product</strong>
      <button id="qeCloseBtn" type="button">&times;</button>
    </div>
    <div id="qeBody">
      <form id="qeForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="id" id="qeId" value="0">
        <div id="qeImgBox" style="text-align:center;margin-bottom:16px"></div>
        <div class="qe-field">
          <label>Title</label>
          <input type="text" id="qeInputTitle" required>
        </div>
        <div class="qe-field">
          <label>Short Description</label>
          <textarea id="qeInputShort" rows="2" style="resize:vertical"></textarea>
        </div>
        <div class="qe-field">
          <label>Meta Description (SEO)</label>
          <textarea id="qeInputMeta" rows="2" style="resize:vertical"></textarea>
        </div>
        <div class="qe-field">
          <label>Category</label>
          <select id="qeInputCat">
            <?php foreach (dk_categories() as $slug => $label): ?>
              <option value="<?php echo e($slug); ?>"><?php echo e($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="qe-actions">
          <button type="submit" id="qeSaveBtn" class="qe-save">💾 Save + Ping Google</button>
          <a id="qeFullEdit" href="#" class="qe-full" target="_blank">⚙ Full Edit</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function() {
    var overlay = document.getElementById('qeOverlay');
    var csrf = '<?php echo e($csrfToken); ?>';
    var base = location.pathname.replace(/\/[^\/]+$/, '/');
    var savedScroll = 0;

    // ===== Click URL slug to copy full product URL =====
    document.addEventListener('click', function(ev) {
        var el = ev.target.closest('.dk-prod-url-copy');
        if (!el) return;
        var url = el.getAttribute('data-url') || '';
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        var orig = el.innerHTML;
        el.classList.add('copied');
        el.innerHTML = '✅ Copied!';
        setTimeout(function() {
            el.classList.remove('copied');
            el.innerHTML = orig;
        }, 1500);
    });

    function openModal(d) {
        document.getElementById('qeId').value = d.id;
        document.getElementById('qeTitle').textContent = d.title || 'Edit Product';
        document.getElementById('qeInputTitle').value = d.title || '';
        document.getElementById('qeInputShort').value = d.short_description || '';
        document.getElementById('qeInputMeta').value = d.meta_description || '';
        if (d.category) document.getElementById('qeInputCat').value = d.category;

        var imgBox = document.getElementById('qeImgBox');
        imgBox.innerHTML = d.og_image
            ? '<img src="../' + d.og_image + '" alt="" style="max-width:180px;max-height:130px;border-radius:8px;border:1px solid #e0e0e0">'
            : '<span style="color:#999;font-size:.85rem">No image</span>';

        document.getElementById('qeFullEdit').href = base + 'product-edit.php?id=' + d.id;
        savedScroll = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = '-' + savedScroll + 'px';
        document.body.style.width = '100%';
        overlay.classList.add('show');

        var sb = document.getElementById('qeSaveBtn');
        sb.disabled = false;
        sb.textContent = '💾 Save + Ping Google';
    }

    function closeModal() {
        overlay.classList.remove('show');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, savedScroll);
    }

    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest('.dk-quickedit-btn');
        if (!btn) return;
        var card = btn.closest('.dk-prod-card');
        if (!card) return;
        var raw = card.getAttribute('data-product');
        if (!raw) return;
        var d;
        try { d = JSON.parse(raw); } catch(e) { return; }
        openModal(d);
    });

    document.getElementById('qeCloseBtn').addEventListener('click', closeModal);

    overlay.addEventListener('click', function(ev) {
        if (ev.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape' && overlay.classList.contains('show')) closeModal();
    });

    document.getElementById('qeForm').addEventListener('submit', function(ev) {
        ev.preventDefault();
        var sb = document.getElementById('qeSaveBtn');
        sb.disabled = true;
        sb.textContent = '⏳ Saving...';

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
                closeModal();
                alert(d.pinged ? 'Saved + Google pinged!' : 'Saved!');
                location.reload();
            } else {
                sb.disabled = false;
                sb.textContent = '💾 Save + Ping Google';
                alert(d.error || 'Error.');
            }
        })
        .catch(function() {
            sb.disabled = false;
            sb.textContent = '💾 Save + Ping Google';
            alert('Network error.');
        });
    });
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
