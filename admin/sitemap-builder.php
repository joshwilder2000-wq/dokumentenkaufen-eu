<?php
/**
 * Sitemap builder.
 *
 * Regenerates the product sitemap(s) from the published products in the DB so
 * the sitemap always matches what's actually on disk.
 *
 *   dk_rebuild_product_sitemap()  -> writes sitemap-products.xml
 *   dk_rebuild_all_sitemaps()     -> writes sitemap-products.xml and refreshes
 *                                    the product <url> block in sitemap.xml
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Build and write sitemap-products.xml from all published products.
 *
 * @return int Number of product URLs written.
 */
function dk_rebuild_product_sitemap(): int
{
    $siteUrl = dk_site_url();
    $stmt = dk_db()->query(
        "SELECT slug, updated_at FROM products
         WHERE is_published = 1
         ORDER BY sort_order ASC, title ASC"
    );
    $products = $stmt->fetchAll();

    $today = date('Y-m-d');

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($products as $p) {
        $lastmod = $p['updated_at']
            ? substr((string) $p['updated_at'], 0, 10)
            : $today;
        $loc = $siteUrl . '/product/' . rawurlencode($p['slug']) . '.html';
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . $lastmod . "</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.8</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    $dest = dk_site_root() . '/sitemap-products.xml';
    $written = file_put_contents($dest, $xml, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException("Konnte sitemap-products.xml nicht schreiben.");
    }

    // Also refresh the product URLs inside the master sitemap.xml.
    dk_refresh_master_sitemap();

    return count($products);
}

/**
 * Refresh the master sitemap.xml so its product <url> entries match the DB.
 *
 * The master sitemap lists static pages + all product pages. We preserve the
 * static block verbatim and regenerate the product block from the DB.
 */
function dk_refresh_master_sitemap(): void
{
    $siteUrl = dk_site_url();
    $master  = dk_site_root() . '/sitemap.xml';
    $today   = date('Y-m-d');

    // Build the master sitemap from scratch with ALL URL types.
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Homepage.
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($siteUrl . '/', ENT_XML1) . "</loc>\n    <lastmod>{$today}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>1.0</priority>\n  </url>\n";

    // Static pages.
    $staticPages = ['agb','bestellung-verfolgen','bewertungen','datenschutz','faq','impressum','kontakt','muster','preise','rueckgabe','ueber-uns','versandkosten','vertrauen','widerruf','zahlungsarten'];
    foreach ($staticPages as $page) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($siteUrl . '/' . $page . '.html', ENT_XML1) . "</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
    }

    // Categories.
    foreach (dk_categories() as $slug => $label) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($siteUrl . '/category/' . $slug . '.html', ENT_XML1) . "</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
    }

    // Blog index.
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($siteUrl . '/blog/', ENT_XML1) . "</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";

    // Blog posts.
    $posts = dk_db()->query("SELECT slug, updated_at FROM posts WHERE is_published = 1 ORDER BY title ASC")->fetchAll();
    foreach ($posts as $post) {
        $lastmod = $post['updated_at'] ? substr((string)$post['updated_at'], 0, 10) : $today;
        $loc = $siteUrl . '/blog/' . rawurlencode($post['slug']) . '.html';
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
    }

    // Products.
    $products = dk_db()->query("SELECT slug, updated_at FROM products WHERE is_published = 1 ORDER BY title ASC")->fetchAll();
    foreach ($products as $p) {
        $lastmod = $p['updated_at'] ? substr((string) $p['updated_at'], 0, 10) : $today;
        $loc = $siteUrl . '/product/' . rawurlencode($p['slug']) . '.html';
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
    }

    // Tags (if table exists).
    try {
        $tags = dk_db()->query('SELECT slug FROM tags ORDER BY name ASC')->fetchAll();
        foreach ($tags as $t) {
            $loc = $siteUrl . '/tag/' . rawurlencode($t['slug']) . '.html';
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
        }
    } catch (Throwable $e) { /* tags table not ready */ }

    // Form pages.
    $formPages = ['formular-fuer-sprachpruefungen','hwk-zeugnisvorform','ihk-zeugnisvorform','fuhrerscheinantragsformular','ausweisformular','Hochschulabschluss'];
    foreach ($formPages as $fp) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($siteUrl . '/' . $fp . '.html', ENT_XML1) . "</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
    }

    $xml .= "</urlset>\n";

    if (file_put_contents($master, $xml, LOCK_EX) === false) {
        throw new RuntimeException("Could not write sitemap.xml.");
    }
}

/**
 * Convenience: rebuild every dynamic sitemap (products + blog + tags).
 */
function dk_rebuild_all_sitemaps(): int
{
    $n = dk_rebuild_product_sitemap();
    dk_rebuild_blog_sitemap();
    // Tags sitemap (only if the table exists).
    try {
        if (function_exists('dk_rebuild_tags_sitemap')) {
            dk_rebuild_tags_sitemap();
        }
    } catch (Throwable $e) { /* tags not set up yet — skip */ }
    return $n;
}

/**
 * Build and write sitemap-blog.xml from all published posts + the blog index.
 *
 * @return int Number of post URLs written (excluding the index).
 */
function dk_rebuild_blog_sitemap(): int
{
    $siteUrl = dk_site_url();
    $today   = date('Y-m-d');

    $stmt = dk_db()->query(
        "SELECT slug, published_at, updated_at FROM posts
         WHERE is_published = 1
         ORDER BY published_at DESC, title ASC"
    );
    $posts = $stmt->fetchAll();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Blog index.
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($siteUrl . '/blog/', ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    $xml .= '    <lastmod>' . $today . "</lastmod>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n";
    $xml .= "    <priority>0.7</priority>\n";
    $xml .= "  </url>\n";

    foreach ($posts as $p) {
        $lastmod = ($p['updated_at'] ?: $p['published_at'])
            ? substr((string)($p['updated_at'] ?: $p['published_at']), 0, 10)
            : $today;
        $loc = $siteUrl . '/blog/' . rawurlencode($p['slug']) . '.html';
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . $lastmod . "</lastmod>\n";
        $xml .= "    <changefreq>monthly</changefreq>\n";
        $xml .= "    <priority>0.7</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    $dest = dk_site_root() . '/sitemap-blog.xml';
    if (file_put_contents($dest, $xml, LOCK_EX) === false) {
        throw new RuntimeException("Konnte sitemap-blog.xml nicht schreiben.");
    }

    return count($posts);
}
