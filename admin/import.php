<?php
/**
 * One-time importer.
 *
 * Parses every existing /product/*.html file in the site and inserts it as a
 * row in the SQLite database, so all current content becomes editable from the
 * admin UI without re-typing.
 *
 * Run once via the "Import bestehender Produkte" button on the dashboard, or by
 * visiting /admin/import.php while logged in.
 *
 * It is idempotent: re-running it updates existing rows (matched by slug) and
 * adds any new files. It does NOT delete products that are missing from disk.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$results = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

if (($_POST['run_import'] ?? null) === '1') {
    dk_csrf_check();

    $productsDir = dk_site_root() . '/product';
    if (!is_dir($productsDir)) {
        $results['errors'][] = 'Kein /product Verzeichnis gefunden.';
    } else {
        $files = glob($productsDir . '/*.html');
        if ($files === false) {
            $files = [];
        }
        foreach ($files as $file) {
            try {
                $row = dk_parse_product_html($file);
                $action = dk_upsert_product($row);
                $results[$action]++;
            } catch (Throwable $ex) {
                $results['errors'][] = basename($file) . ': ' . $ex->getMessage();
                $results['skipped']++;
            }
        }

        // Rebuild sitemaps from the freshly imported data.
        require_once __DIR__ . '/sitemap-builder.php';
        dk_rebuild_all_sitemaps();

        dk_flash('success', sprintf(
            'Import abgeschlossen: %d neu, %d aktualisiert, %d übersprungen.',
            $results['added'], $results['updated'], $results['skipped']
        ));
    }
}

/**
 * Parse a static product HTML file into a product row.
 */
function dk_parse_product_html(string $file): array
{
    $html = file_get_contents($file);
    if ($html === false || $html === '') {
        throw new RuntimeException('Datei leer oder nicht lesbar.');
    }

    $slug = pathinfo($file, PATHINFO_FILENAME);

    // Title: prefer <title> (strip " | Dokuments Hub"), fallback to <h1>.
    $title = $slug;
    if (preg_match('#<title>(.*?)</title>#is', $html, $m)) {
        $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        $title = preg_replace('/\s*\|\s*Dokuments Hub\s*$/i', '', $title) ?? $title;
    }
    if (($title === '' || $title === $slug) && preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) {
        $title = trim(strip_tags($m[1]));
    }

    // Meta description.
    $metaDesc = '';
    if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $html, $m)) {
        $metaDesc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    // Meta keywords.
    $metaKeywords = '';
    if (preg_match('#<meta\s+name="keywords"\s+content="([^"]*)"#i', $html, $m)) {
        $metaKeywords = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    // og:image (the product image).
    $ogImage = '';
    if (preg_match('#<meta\s+property="og:image"\s+content="([^"]*)"#i', $html, $m)) {
        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        // Strip the host; keep the path relative to site root.
        $ogImage = preg_replace('#^https?://[^/]+/#', '', $url);
    }
    // Fallback to the main <img src="../images/...">.
    if (!$ogImage && preg_match('#class="product-main-image"[^>]*src="([^"]+)"#i', $html, $m)) {
        $ogImage = preg_replace('#^\.\./#', '', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }

    // Category: derive from the JSON-LD breadcrumb or from the file's own list.
    $category = dk_guess_category_from_html($html, $title);

    // Short description: the first <p> inside .product-header (after the h1).
    $shortDesc = '';
    if (preg_match('#<header class="product-header">.*?</h1>\s*<p>(.*?)</p>#is', $html, $m)) {
        $shortDesc = trim(strip_tags($m[1]));
    }

    // Process steps: <li><strong>Title</strong>text</li> within .process-steps.
    $processSteps = [];
    if (preg_match('#<ol class="process-steps">(.*?)</ol>#is', $html, $m)) {
        if (preg_match_all('#<li>\s*<strong>(.*?)</strong>(.*?)</li>#is', $m[1], $items, PREG_SET_ORDER)) {
            foreach ($items as $item) {
                $processSteps[] = [
                    'title' => trim(strip_tags($item[1])),
                    'text'  => trim(strip_tags($item[2])),
                ];
            }
        }
    }

    // Features: <li> items within .feature-list.
    $features = [];
    if (preg_match('#<ul class="feature-list">(.*?)</ul>#is', $html, $m)) {
        if (preg_match_all('#<li>(.*?)</li>#is', $m[1], $items, PREG_SET_ORDER)) {
            foreach ($items as $item) {
                $features[] = trim(strip_tags($item[1]));
            }
        }
    }

    // main_description: anything inside .product-description that isn't the <h2>/<ul>.
    $mainDesc = '';
    if (preg_match('#<section class="product-description">(.*?)</section>#is', $html, $m)) {
        $inner = $m[1];
        // Remove the heading and the feature list; what remains is body HTML.
        $inner = preg_replace('#<h2>.*?</h2>#is', '', $inner);
        $inner = preg_replace('#<ul class="feature-list">.*?</ul>#is', '', $inner);
        $mainDesc = trim($inner);
        if ($mainDesc === '') {
            $mainDesc = '';
        }
    }

    return [
        'slug'             => $slug,
        'title'            => $title,
        'meta_description' => $metaDesc,
        'meta_keywords'    => $metaKeywords,
        'category'         => $category,
        'og_image'         => $ogImage,
        'short_description'=> $shortDesc,
        'main_description' => $mainDesc,
        'features'         => json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'process_steps'    => json_encode($processSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

/**
 * Guess the category slug from a product's JSON-LD breadcrumb or its title.
 */
function dk_guess_category_from_html(string $html, string $title): string
{
    // JSON-LD breadcrumb position 2 usually holds the category name.
    if (preg_match_all('#"name"\s*:\s*"([^"]+)"#', $html, $names)) {
        foreach ($names[1] as $name) {
            $slug = dk_slugify($name);
            if (array_key_exists($slug, dk_categories())) {
                return $slug;
            }
        }
    }

    // Keyword heuristics.
    $t = strtolower($title);
    if (preg_match('/meister|hwk/', $t)) {
        return 'hwk-meisterbriefe';
    }
    if (preg_match('/ihk|fachwirt|betriebswirt/', $t)) {
        return 'ihk-zertifikate';
    }
    if (preg_match('/deutsch|telc|goethe|sprach/', $t)) {
        return 'sprachzertifikate';
    }
    if (preg_match('/gewerbe/', $t)) {
        return 'gewerbeordnung';
    }
    return 'universitaetsdokumente';
}

/**
 * Insert or update a product row. Returns 'added' or 'updated'.
 */
function dk_upsert_product(array $row): string
{
    $pdo = dk_db();
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
    $stmt->execute([$row['slug']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare(
            'UPDATE products SET
                title = ?, meta_description = ?, meta_keywords = ?,
                category = ?, og_image = ?, short_description = ?,
                main_description = ?, features = ?, process_steps = ?,
                updated_at = datetime("now")
             WHERE slug = ?'
        )->execute([
            $row['title'], $row['meta_description'], $row['meta_keywords'],
            $row['category'], $row['og_image'], $row['short_description'],
            $row['main_description'], $row['features'], $row['process_steps'],
            $row['slug'],
        ]);
        return 'updated';
    }

    $pdo->prepare(
        'INSERT INTO products
            (slug, title, meta_description, meta_keywords, category, og_image,
             short_description, main_description, features, process_steps,
             is_published, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?, 1, 0)'
    )->execute([
        $row['slug'], $row['title'], $row['meta_description'], $row['meta_keywords'],
        $row['category'], $row['og_image'], $row['short_description'],
        $row['main_description'], $row['features'], $row['process_steps'],
    ]);
    return 'added';
}

// --- View ---
$pageTitle = 'Produkte importieren';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-card">
    <h2>Bestehende Produkte importieren</h2>
    <p>
        Dieses Skript liest alle <code>/product/*.html</code> Dateien ein und legt sie in der
        Datenbank ab, sodass sie über den Admin-Bereich bearbeitet werden können.
        Vorhandene Einträge (gleicher Slug) werden aktualisiert, nichts wird gelöscht.
    </p>
    <p class="dk-muted">Gefundene Dateien: <strong><?php echo count(glob(dk_site_root() . '/product/*.html') ?: []); ?></strong></p>

    <?php if ($err = dk_flash('success')): ?>
        <div class="dk-alert dk-alert-success"><?php echo e($err); ?></div>
    <?php endif; ?>
    <?php foreach (($results['errors'] ?? []) as $err): ?>
        <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" onsubmit="return confirm('Import jetzt ausführen? Vorhandene Produkte werden aktualisiert.');">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="run_import" value="1">
        <button type="submit" class="dk-btn dk-btn-primary">Import ausführen</button>
        <a href="dashboard.php" class="dk-btn dk-btn-link">Zurück zum Dashboard</a>
    </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
