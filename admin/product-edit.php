<?php
/**
 * Create / edit a product.
 *
 * On save: validates input, upserts the DB row, regenerates the static .html
 * file from the renderer, and rebuilds the product sitemap.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/sitemap-builder.php';

$id = (int) ($_GET['id'] ?? 0);
$product = null;

if ($id > 0) {
    $stmt = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404);
        die('Produkt nicht gefunden.');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();

    $title    = dk_clean((string) ($_POST['title'] ?? ''));
    $slugRaw  = dk_clean((string) ($_POST['slug'] ?? ''));
    $slug     = dk_unique_slug($slugRaw !== '' ? dk_slugify($slugRaw) : dk_slugify($title), $id ?: null);
    $metaDesc = dk_clean((string) ($_POST['meta_description'] ?? ''));
    $keywords = dk_clean((string) ($_POST['meta_keywords'] ?? ''));
    $category = dk_clean((string) ($_POST['category'] ?? 'universitaetsdokumente'));
    $short    = dk_clean((string) ($_POST['short_description'] ?? ''));
    $mainDesc = (string) ($_POST['main_description'] ?? ''); // trusted HTML
    $published = isset($_POST['is_published']) ? 1 : 0;

    // Features + process steps as newline lists.
    $features = array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['features'] ?? ''))), 'strlen'));
    $steps = [];
    $stepLines = array_filter(array_map('trim', explode("\n", (string) ($_POST['process_steps'] ?? ''))), 'strlen');
    foreach ($stepLines as $line) {
        // Format: "Title — description text"  (em dash —, en dash –, colon :, or pipe |)
        if (preg_match('/^(.+?)\s*[—–:|]\s*(.+)$/u', $line, $m)) {
            $steps[] = ['title' => trim($m[1]), 'text' => trim($m[2])];
        } else {
            $steps[] = ['title' => trim($line), 'text' => ''];
        }
    }

    if ($title === '') {
        $errors[] = 'Ein Titel ist erforderlich.';
    }
    if (!array_key_exists($category, dk_categories())) {
        $category = 'universitaetsdokumente';
    }

    // Image upload (optional).
    $ogImage = (string) ($product['og_image'] ?? '');
    if (!empty($_FILES['image']['name'])) {
        try {
            $ogImage = dk_save_product_image($_FILES['image'], $slug);
        } catch (Throwable $ex) {
            $errors[] = 'Bild: ' . $ex->getMessage();
        }
    }
    // Or pick an existing image path.
    if (!$ogImage && !empty($_POST['existing_image'])) {
        $ogImage = dk_clean((string) $_POST['existing_image']);
    }

    if (!$errors) {
        if ($id) {
            // If the slug changed, remove the old static file.
            if ($product['slug'] !== $slug) {
                dk_remove_product_file((string) $product['slug']);
            }
            dk_db()->prepare(
                'UPDATE products SET
                    slug = ?, title = ?, meta_description = ?, meta_keywords = ?,
                    category = ?, og_image = ?, short_description = ?,
                    main_description = ?, features = ?, process_steps = ?,
                    is_published = ?, updated_at = datetime("now")
                 WHERE id = ?'
            )->execute([
                $slug, $title, $metaDesc, $keywords,
                $category, $ogImage, $short,
                $mainDesc,
                json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $published, $id,
            ]);
        } else {
            dk_db()->prepare(
                'INSERT INTO products
                    (slug, title, meta_description, meta_keywords, category, og_image,
                     short_description, main_description, features, process_steps,
                     is_published, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,0)'
            )->execute([
                $slug, $title, $metaDesc, $keywords,
                $category, $ogImage, $short,
                $mainDesc,
                json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $published,
            ]);
            $id = (int) dk_db()->lastInsertId();
        }

        // Regenerate the static file + sitemaps.
        $fresh = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
        $fresh->execute([$id]);
        $row = $fresh->fetch();
        if ($row['is_published']) {
            dk_render_product($row);
        } else {
            dk_remove_product_file($row['slug']);
        }
        dk_rebuild_all_sitemaps();

        dk_flash('success', 'Produkt gespeichert.');
        header('Location: product-edit.php?id=' . $id . '&saved=1');
        exit;
    }

    // Repopulate from submitted data on error.
    $product = [
        'id' => $id ?: null,
        'slug' => $slug, 'title' => $title, 'meta_description' => $metaDesc,
        'meta_keywords' => $keywords, 'category' => $category, 'og_image' => $ogImage,
        'short_description' => $short, 'main_description' => $mainDesc,
        'features' => json_encode($features, JSON_UNESCAPED_UNICODE),
        'process_steps' => json_encode($steps, JSON_UNESCAPED_UNICODE),
        'is_published' => $published,
    ];
}

// Prep field values for the form.
$featuresText = '';
$stepsText = '';
if ($product) {
    foreach (dk_json_list($product['features']) as $f) {
        $featuresText .= $f . "\n";
    }
    foreach (dk_json_list($product['process_steps']) as $s) {
        $stepsText .= ($s['title'] ?? '') . ' — ' . ($s['text'] ?? '') . "\n";
    }
}

$pageTitle = ($product && !empty($product['id']) ? 'Produkt bearbeiten' : 'Neues Produkt');
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1><?php echo e($pageTitle); ?></h1>
    <a href="dashboard.php" class="dk-btn dk-btn-link">← Zurück</a>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="dk-form-grid">
    <?php echo dk_csrf_field(); ?>

    <div class="dk-form-main">
        <div class="dk-card">
            <div class="dk-field">
                <label for="title">Titel <span class="dk-req">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo e($product['title'] ?? ''); ?>" required autofocus
                       oninput="document.getElementById('slug-preview').textContent = dkSlug(document.getElementById('title').value);">
                <small class="dk-muted">Wird als &lt;h1&gt; und im &lt;title&gt; verwendet.</small>
            </div>

            <div class="dk-field">
                <label for="slug">URL-Slug</label>
                <div class="dk-slug-row">
                    <code>/product/</code>
                    <input type="text" id="slug" name="slug" value="<?php echo e($product['slug'] ?? ''); ?>" placeholder="wird aus Titel generiert">
                    <code>.html</code>
                </div>
                <small class="dk-muted">Vorschau: <span id="slug-preview" class="dk-slug-preview"><?php echo e($product['slug'] ?? ''); ?></span></small>
            </div>

            <div class="dk-field">
                <label for="short_description">Kurzbeschreibung</label>
                <input type="text" id="short_description" name="short_description" value="<?php echo e($product['short_description'] ?? ''); ?>">
                <small class="dk-muted">Einleitungssatz direkt unter dem Titel.</small>
            </div>

            <div class="dk-field">
                <label for="main_description">Beschreibung (HTML)</label>
                <textarea id="main_description" name="main_description" rows="8" placeholder="<p>…</p>"><?php echo e($product['main_description'] ?? ''); ?></textarea>
                <small class="dk-muted">Erlaubt HTML (Absätze &lt;p&gt;, Listen …). Erscheint im Abschnitt „Wobei wir helfen”.</small>
            </div>

            <div class="dk-field">
                <label for="features">Vorteile / Stichpunkte <small>(einer pro Zeile)</small></label>
                <textarea id="features" name="features" rows="5" placeholder="Studien- und Karriereorientierung&#10;Prüfungs- und Zertifikatsvorbereitung"><?php echo e($featuresText); ?></textarea>
            </div>

            <div class="dk-field">
                <label for="process_steps">Ablaufschritte <small>(Format: <code>Titel — Beschreibung</code>, einer pro Zeile)</small></label>
                <textarea id="process_steps" name="process_steps" rows="5" placeholder="Ziel klären — Sie beschreiben …&#10;Unterlagen prüfen — Ein Berater ordnet …"><?php echo e($stepsText); ?></textarea>
            </div>
        </div>

        <div class="dk-card">
            <h3>Suchmaschinen-Optimierung (SEO)</h3>
            <div class="dk-field">
                <label for="meta_description">Meta-Beschreibung <small>(150–160 Zeichen)</small></label>
                <textarea id="meta_description" name="meta_description" rows="2" maxlength="170"><?php echo e($product['meta_description'] ?? ''); ?></textarea>
                <small class="dk-muted"><span id="meta-count"><?php echo mb_strlen($product['meta_description'] ?? ''); ?></span>/170 Zeichen.</small>
            </div>
            <div class="dk-field">
                <label for="meta_keywords">Meta-Keywords</label>
                <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo e($product['meta_keywords'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <div class="dk-form-side">
        <div class="dk-card">
            <h3>Veröffentlichung</h3>
            <div class="dk-field dk-check">
                <label>
                    <input type="checkbox" name="is_published" value="1" <?php echo (($product['is_published'] ?? 1) ? 'checked' : ''); ?>>
                    Veröffentlicht (Seite ist live + in der Sitemap)
                </label>
            </div>
            <div class="dk-field">
                <label for="category">Kategorie</label>
                <select id="category" name="category" class="dk-input">
                    <?php foreach (dk_categories() as $slug => $label): ?>
                        <option value="<?php echo e($slug); ?>" <?php echo (($product['category'] ?? '') === $slug ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="dk-card">
            <h3>Produktbild</h3>
            <?php if (!empty($product['og_image'])): ?>
                <img src="../<?php echo e($product['og_image']); ?>" alt="" class="dk-preview-img" loading="lazy">
            <?php endif; ?>
            <div class="dk-field">
                <label for="image">Neues Bild hochladen</label>
                <input type="file" id="image" name="image" accept="image/webp,image/jpeg,image/png">
                <small class="dk-muted">WebP/JPG/PNG. Wird automatisch auf WebP konvertiert. Seitenverhältnis 4:3 empfohlen.</small>
            </div>
            <div class="dk-field">
                <label for="existing_image">…oder bestehenden Bildpfad</label>
                <input type="text" id="existing_image" name="existing_image" value="<?php echo e($product['og_image'] ?? ''); ?>" placeholder="images/products/datei.webp">
            </div>
        </div>

        <div class="dk-form-actions">
            <button type="submit" class="dk-btn dk-btn-primary dk-btn-block">Speichern</button>
            <?php if (!empty($product['id'])): ?>
                <a href="../product/<?php echo e($product['slug']); ?>.html" target="_blank" class="dk-btn dk-btn-ghost dk-btn-block">Seite ansehen</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
function dkSlug(s){
    s = s.replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss');
    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    return s;
}
document.getElementById('meta_description')?.addEventListener('input', function(){
    document.getElementById('meta-count').textContent = this.value.length;
});
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
