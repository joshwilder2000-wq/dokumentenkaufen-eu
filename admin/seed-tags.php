<?php
/**
 * Seed product tags + generate tag pages + tag sitemap.
 *
 * Auto-generates tags from product titles + meta_keywords, links them to
 * products, renders tag pages (/tag/<slug>.html), and builds sitemap-tags.xml.
 *
 * Run via: php admin/seed-tags.php
 * Idempotent: clears + rebuilds tags on each run.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tag-renderer.php';
require_once __DIR__ . '/sitemap-builder.php';

dk_set_setting('site_url', 'https://dokumentenkaufen.eu');

/**
 * Build a set of tags from a product's title + keywords.
 */
function dk_extract_tags_from_product(array $product): array
{
    $tags = [];
    $text = $product['title'] . ' ' . $product['meta_keywords'];

    // Known tag patterns to extract.
    $patterns = [
        '/\bIHK\b/i'           => 'IHK',
        '/\bHWK\b/i'           => 'HWK',
        '/\bBachelor\b/i'      => 'Bachelor',
        '/\bMaster\b/i'        => 'Master',
        '/\bMBA\b/i'           => 'MBA',
        '/\bDoktor\b|Doktortitel/i' => 'Doktortitel',
        '/\bDeutsch\b|Sprach/i' => 'Deutsch Zertifikat',
        '/\btelc\b/i'          => 'telc',
        '/\bGoethe\b/i'        => 'Goethe',
        '/\bMeisterbrief\b|meister/i' => 'Meisterbrief',
        '/\bFachwirt\b/i'      => 'Fachwirt',
        '/\bBetriebswirt\b/i'  => 'Betriebswirt',
        '/\bAusbildung\b|AEVO/i' => 'Ausbildung',
        '/\bFernstudium\b|Fernuniversit/i' => 'Fernstudium',
        '/\bUrkunde\b/i'       => 'Urkunde',
        '/\bZeugnis\b/i'       => 'Zeugnis',
        '/\bGewerbe\b|34[fi]/i' => 'Gewerbeordnung',
        '/\bFührerschein\b|Fahrerlaubnis/i' => 'Führerschein',
        '/\bAbitur\b|Realschule|Matura/i'  => 'Schulabschluss',
        '/\bTranskript\b/i'    => 'Transkript',
        '/\bWaffenbesitz\b|WBK/i' => 'Waffenbesitzkarte',
        '/\bUniversity\b|Universit/i' => 'Universität',
        '/\bHochschule\b/i'    => 'Hochschule',
        '/\bAnerkennung\b/i'   => 'Anerkennung',
    ];

    foreach ($patterns as $regex => $tagName) {
        if (preg_match($regex, $text)) {
            $tags[$tagName] = true;
        }
    }

    // Also extract from keywords directly.
    if (!empty($product['meta_keywords'])) {
        $kws = explode(',', $product['meta_keywords']);
        foreach ($kws as $kw) {
            $kw = trim($kw);
            if (mb_strlen($kw) >= 4 && mb_strlen($kw) <= 30) {
                $tags[$kw] = true;
            }
        }
    }

    return array_keys($tags);
}

// --- Clear existing tags ---
dk_db()->exec('DELETE FROM product_tags');
dk_db()->exec('DELETE FROM tags');
echo "Cleared existing tags.\n";

// --- Build tag map: tagName → [productIds] ---
$tagMap = []; // tagName => [product_id, ...]
$products = dk_db()->query("SELECT * FROM products WHERE is_published = 1")->fetchAll();

foreach ($products as $p) {
    $productTags = dk_extract_tags_from_product($p);
    foreach ($productTags as $tagName) {
        if (!isset($tagMap[$tagName])) {
            $tagMap[$tagName] = [];
        }
        $tagMap[$tagName][] = (int)$p['id'];
    }
}

// Filter: only tags with 2+ products (single-product tags aren't useful as tag pages).
$tagMap = array_filter($tagMap, function ($ids) { return count($ids) >= 2; });

echo "Tags with 2+ products: " . count($tagMap) . "\n";

// --- Insert tags into DB ---
$tagSlugCache = [];
foreach ($tagMap as $tagName => $productIds) {
    $slug = dk_slugify($tagName);
    if ($slug === '') continue;

    // Ensure unique slug.
    $candidate = $slug;
    $n = 1;
    while (isset($tagSlugCache[$candidate])) {
        $candidate = $slug . '-' . (++$n);
    }
    $slug = $candidate;
    $tagSlugCache[$slug] = true;

    dk_db()->prepare('INSERT INTO tags (slug, name) VALUES (?,?)')->execute([$slug, $tagName]);
    $tagId = (int)dk_db()->lastInsertId();

    foreach (array_unique($productIds) as $pid) {
        dk_db()->prepare('INSERT OR IGNORE INTO product_tags (product_id, tag_id) VALUES (?,?)')
            ->execute([$pid, $tagId]);
    }
}

$totalTags = (int)dk_db()->query('SELECT COUNT(*) FROM tags')->fetchColumn();
$totalLinks = (int)dk_db()->query('SELECT COUNT(*) FROM product_tags')->fetchColumn();
echo "Created: $totalTags tags, $totalLinks product-tag links\n";

// --- Render all tag pages ---
$tags = dk_db()->query('SELECT * FROM tags ORDER BY name ASC')->fetchAll();
$rendered = 0;
foreach ($tags as $tag) {
    dk_render_tag_page($tag);
    $rendered++;
}
echo "Rendered: $rendered tag pages\n";

// --- Build sitemap-tags.xml ---
$smCount = dk_rebuild_tags_sitemap();
echo "sitemap-tags.xml: $smCount URLs\n";

echo "\n==============================================\n";
echo "  Tag Seeding — DONE\n";
echo "==============================================\n";
echo "Tags:      $totalTags\n";
echo "Links:     $totalLinks\n";
echo "Tag pages: $rendered\n";
echo "Sitemap:   $smCount URLs\n";
echo "==============================================\n";
