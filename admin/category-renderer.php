<?php
/**
 * Category schema injector.
 *
 * Category pages (/category/*.html) are hand-edited static files. Rather than
 * re-render them from scratch, this function injects (or refreshes) a
 * CollectionPage + ItemList JSON-LD block into their <head>, listing the
 * category's published products. Existing markup is preserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Build the CollectionPage + ItemList JSON-LD for a category.
 */
function dk_category_jsonld(string $categorySlug): string
{
    $siteUrl = dk_site_url();
    $catUrl  = $siteUrl . '/category/' . $categorySlug . '.html';
    $label   = dk_categories()[$categorySlug] ?? ucfirst(str_replace('-', ' ', $categorySlug));

    $stmt = dk_db()->prepare(
        "SELECT slug, title, og_image FROM products
         WHERE category = ? AND is_published = 1
         ORDER BY sort_order ASC, title ASC"
    );
    $stmt->execute([$categorySlug]);
    $products = $stmt->fetchAll();

    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $url = $siteUrl . '/product/' . rawurlencode($p['slug']) . '.html';
        $img = $p['og_image'] ? ($siteUrl . '/' . ltrim($p['og_image'], '/')) : '';
        $items[] = '        {
          "@type": "ListItem",
          "position": ' . $pos . ',
          "name": "' . e((string) $p['title']) . '",
          "url": "' . e($url) . '"' .
          ($img ? ', "image": "' . e($img) . '"' : '') . '
        }';
        $pos++;
    }

    $json = '{
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "CollectionPage",
          "@id": "' . e($catUrl) . '#collectionpage",
          "url": "' . e($catUrl) . '",
          "name": "' . e($label) . '",
          "inLanguage": "de",
          "isPartOf": { "@id": "' . e($siteUrl) . '/#website" },
          "about": {
            "@type": "ItemList",
            "@id": "' . e($catUrl) . '#itemlist",
            "name": "' . e($label) . '",
            "numberOfItems": ' . count($products) . ',
            "itemListElement": [
' . implode(",\n", $items) . '
            ]
          }
        },
        {
          "@type": "WebSite",
          "@id": "' . e($siteUrl) . '/#website",
          "url": "' . e($siteUrl) . '/",
          "name": "Dokuments Hub",
          "inLanguage": "de"
        }
      ]
    }';

    return '<!-- CATEGORY-SCHEMA --><script type="application/ld+json">' . "\n" . $json . "\n" . '</script><!-- /CATEGORY-SCHEMA -->';
}

/**
 * Inject or refresh the category JSON-LD into a category HTML file.
 */
function dk_inject_category_schema(string $categorySlug): void
{
    $file = dk_site_root() . '/category/' . $categorySlug . '.html';
    if (!file_exists($file)) {
        return;
    }
    $html = file_get_contents($file);
    if ($html === false) {
        return;
    }

    // Remove any existing injected block.
    $html = preg_replace(
        '#<!-- CATEGORY-SCHEMA -->.*?<!-- /CATEGORY-SCHEMA -->\s*#s',
        '',
        $html
    );

    $block = dk_category_jsonld($categorySlug);

    // Insert before </head> if present, else before </body>.
    if (strpos($html, '</head>') !== false) {
        $html = str_replace('</head>', $block . "\n</head>", $html);
    } elseif (strpos($html, '</body>') !== false) {
        $html = str_replace('</body>', $block . "\n</body>", $html);
    }

    file_put_contents($file, $html, LOCK_EX);
}

/**
 * Refresh the JSON-LD on all category pages.
 *
 * @return int Number of category pages updated.
 */
function dk_refresh_all_category_schemas(): int
{
    $n = 0;
    foreach (dk_categories() as $slug => $label) {
        dk_inject_category_schema($slug);
        $n++;
    }
    return $n;
}
