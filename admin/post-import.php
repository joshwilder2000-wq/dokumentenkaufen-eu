<?php
/**
 * One-time importer for existing blog posts.
 *
 * Parses every /blog/*.html (except index.html and TEMPLATE.html) and inserts
 * it into the posts table so existing content becomes editable.
 *
 * Idempotent: re-running updates existing rows (matched by slug), never deletes.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$results = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

if (($_POST['run_import'] ?? null) === '1') {
    dk_csrf_check();

    $blogDir = dk_site_root() . '/blog';
    if (!is_dir($blogDir)) {
        $results['errors'][] = 'Kein /blog Verzeichnis gefunden.';
    } else {
        foreach (glob($blogDir . '/*.html') as $file) {
            $name = basename($file);
            // Skip the index and the template.
            if (in_array($name, ['index.html', 'TEMPLATE.html'], true)) {
                continue;
            }
            try {
                $row = dk_parse_post_html($file);
                $action = dk_upsert_post($row);
                $results[$action]++;
            } catch (Throwable $ex) {
                $results['errors'][] = $name . ': ' . $ex->getMessage();
                $results['skipped']++;
            }
        }

        require_once __DIR__ . '/blog-renderer.php';
        require_once __DIR__ . '/sitemap-builder.php';
        dk_render_blog_index();
        dk_rebuild_blog_sitemap();

        dk_flash('success', sprintf(
            'Import abgeschlossen: %d neu, %d aktualisiert, %d übersprungen.',
            $results['added'], $results['updated'], $results['skipped']
        ));
    }
}

/**
 * Parse a static blog post HTML file into a post row.
 */
function dk_parse_post_html(string $file): array
{
    $html = file_get_contents($file);
    if ($html === false || $html === '') {
        throw new RuntimeException('Datei leer oder nicht lesbar.');
    }

    $slug = pathinfo($file, PATHINFO_FILENAME);

    // Title.
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

    // og:image.
    $ogImage = '';
    if (preg_match('#<meta\s+property="og:image"\s+content="([^"]*)"#i', $html, $m)) {
        $ogImage = preg_replace('#^https?://[^/]+/#', '', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }

    // Category from the section-kicker.
    $category = 'karriere-studium';
    if (preg_match('#class="section-kicker">([^<]+)<#i', $html, $m)) {
        $kicker = trim($m[1]);
        foreach (dk_post_categories() as $cslug => $clabel) {
            if (stripos($kicker, $clabel) !== false || stripos($clabel, $kicker) !== false) {
                $category = $cslug;
                break;
            }
        }
    }

    // Excerpt: content inside .content-card.
    $excerpt = '';
    if (preg_match('#<div class="content-card">(.*?)</div>\s*<div#is', $html, $m)) {
        $excerpt = trim($m[1]);
    } elseif (preg_match('#<div class="content-card">(.*?)</div>#is', $html, $m)) {
        $excerpt = trim($m[1]);
    }

    // Main content: everything in nested-cards combined.
    $content = '';
    if (preg_match_all('#<article class="nested-card">(.*?)</article>#is', $html, $cards, PREG_SET_ORDER)) {
        foreach ($cards as $c) {
            $content .= trim($c[1]) . "\n";
        }
    }

    return [
        'slug'             => $slug,
        'title'            => $title,
        'meta_description' => $metaDesc,
        'meta_keywords'    => $metaKeywords,
        'category'         => $category,
        'og_image'         => $ogImage,
        'excerpt'          => $excerpt,
        'content'          => $content,
        'author'           => 'Dokuments Hub',
        'published_at'     => '2026-05-19',
    ];
}

/**
 * Insert or update a post row. Returns 'added' or 'updated'.
 */
function dk_upsert_post(array $row): string
{
    $pdo = dk_db();
    $stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
    $stmt->execute([$row['slug']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare(
            'UPDATE posts SET
                title = ?, meta_description = ?, meta_keywords = ?,
                category = ?, og_image = ?, excerpt = ?, content = ?,
                author = ?, published_at = ?, updated_at = datetime("now")
             WHERE slug = ?'
        )->execute([
            $row['title'], $row['meta_description'], $row['meta_keywords'],
            $row['category'], $row['og_image'], $row['excerpt'], $row['content'],
            $row['author'], $row['published_at'], $row['slug'],
        ]);
        return 'updated';
    }

    $pdo->prepare(
        'INSERT INTO posts
            (slug, title, meta_description, meta_keywords, category, og_image,
             excerpt, content, author, published_at, is_published, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?, 1, 0)'
    )->execute([
        $row['slug'], $row['title'], $row['meta_description'], $row['meta_keywords'],
        $row['category'], $row['og_image'], $row['excerpt'], $row['content'],
        $row['author'], $row['published_at'],
    ]);
    return 'added';
}

// --- View ---
$pageTitle = 'Blog importieren';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-card">
    <h2>Bestehende Blog-Beiträge importieren</h2>
    <p>
        Dieses Skript liest alle <code>/blog/*.html</code> Dateien (außer <code>index.html</code>
        und <code>TEMPLATE.html</code>) ein und legt sie in der Datenbank ab, sodass sie
        über den Admin-Bereich bearbeitet werden können.
    </p>
    <p class="dk-muted">Gefundene Dateien:
        <strong><?php
            $files = glob(dk_site_root() . '/blog/*.html') ?: [];
            $files = array_filter($files, fn($f) => !in_array(basename($f), ['index.html', 'TEMPLATE.html'], true));
            echo count($files);
        ?></strong>
    </p>

    <?php if ($err = dk_flash('success')): ?>
        <div class="dk-alert dk-alert-success"><?php echo e($err); ?></div>
    <?php endif; ?>
    <?php foreach (($results['errors'] ?? []) as $err): ?>
        <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" onsubmit="return confirm('Import jetzt ausführen? Vorhandene Beiträge werden aktualisiert.');">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="run_import" value="1">
        <button type="submit" class="dk-btn dk-btn-primary">Import ausführen</button>
        <a href="blog-dashboard.php" class="dk-btn dk-btn-link">Zurück zum Blog-Dashboard</a>
    </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
