<?php
/**
 * Google Merchant Center XML feed generator.
 *
 * Produces /merchant-feed.xml in RSS 2.0 + g: namespace format, ready to submit
 * to Google Merchant Center as a scheduled fetch.
 *
 * @see https://support.google.com/merchants/answer/7052112
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Build and write the Merchant Center feed from all published products.
 *
 * @return int Number of items in the feed.
 */
function dk_build_merchant_feed(): int
{
    $siteUrl = dk_site_url();
    $today   = date('Y-m-d');

    $stmt = dk_db()->query(
        "SELECT * FROM products
         WHERE is_published = 1
         ORDER BY sort_order ASC, title ASC"
    );
    $products = $stmt->fetchAll();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
    $xml .= "  <channel>\n";
    $xml .= "    <title>Dokuments Hub — Produktfeed</title>\n";
    $xml .= "    <link>" . htmlspecialchars($siteUrl, ENT_XML1) . "</link>\n";
    $xml .= "    <description>Beratungs- und Vermittlungsangebote für akademische und berufliche Nachweise.</description>\n";

    foreach ($products as $p) {
        $url     = $siteUrl . '/product/' . rawurlencode($p['slug']) . '.html';
        $title   = (string) $p['title'];
        $desc    = (string) ($p['meta_description'] ?: $p['short_description']);
        $image   = $p['og_image'] ? ($siteUrl . '/' . ltrim($p['og_image'], '/')) : ($siteUrl . '/images/logo-new.png');
        $sku     = (string) ($p['sku'] ?: 'DK-' . $p['slug']);
        $mpn     = (string) ($p['mpn'] ?: $sku);
        $gpc     = (string) ($p['google_product_category'] ?: '1001');
        $catLabel = dk_categories()[$p['category']] ?? 'Bildung';

        $xml .= "    <item>\n";
        $xml .= '      <g:id>' . htmlspecialchars($sku, ENT_XML1) . "</g:id>\n";
        $xml .= '      <title>' . htmlspecialchars($title, ENT_XML1) . "</title>\n";
        $xml .= '      <description>' . htmlspecialchars($desc, ENT_XML1) . "</description>\n";
        $xml .= '      <link>' . htmlspecialchars($url, ENT_XML1) . "</link>\n";
        $xml .= '      <g:image_link>' . htmlspecialchars($image, ENT_XML1) . "</g:image_link>\n";
        $xml .= '      <g:additional_image_link>' . htmlspecialchars($siteUrl . '/images/logo-new.png', ENT_XML1) . "</g:additional_image_link>\n";
        $xml .= '      <g:availability>in_stock</g:availability>' . "\n";
        $xml .= '      <g:price>0.00 EUR</g:price>' . "\n";
        $xml .= '      <g:brand>Dokuments Hub</g:brand>' . "\n";
        $xml .= '      <g:gtin>' . htmlspecialchars((string)$p['gtin'], ENT_XML1) . "</g:gtin>\n";
        $xml .= '      <g:mpn>' . htmlspecialchars($mpn, ENT_XML1) . "</g:mpn>\n";
        $xml .= '      <g:identifier_exists>' . ($p['gtin'] ? 'yes' : 'no') . "</g:identifier_exists>\n";
        $xml .= '      <g:condition>new</g:condition>' . "\n";
        $xml .= '      <g:google_product_category>' . htmlspecialchars($gpc, ENT_XML1) . "</g:google_product_category>\n";
        $xml .= '      <g:product_type>' . htmlspecialchars($catLabel, ENT_XML1) . "</g:product_type>\n";
        $xml .= '      <g:shipping>' . "\n";
        $xml .= '        <g:country>DE</g:country>' . "\n";
        $xml .= '        <g:service>Standard</g:service>' . "\n";
        $xml .= '        <g:price>0.00 EUR</g:price>' . "\n";
        $xml .= '      </g:shipping>' . "\n";
        // Age group + target audience defaults.
        $xml .= '      <g:adult>false</g:adult>' . "\n";
        $xml .= '      <g:age_group>adult</g:age_group>' . "\n";
        // Aggregate rating if reviews exist.
        $agg = dk_aggregate_rating((int) $p['id']);
        if ($agg) {
            $xml .= '      <g:product_rating>' . $agg['ratingValue'] . "</g:product_rating>\n";
            $xml .= '      <g:product_review_count>' . $agg['reviewCount'] . "</g:product_review_count>\n";
        }
        $xml .= '      <g:custom_label_0>' . htmlspecialchars($catLabel, ENT_XML1) . "</g:custom_label_0>\n";
        $xml .= "    </item>\n";
    }

    $xml .= "  </channel>\n</rss>\n";

    $dest = dk_site_root() . '/merchant-feed.xml';
    if (file_put_contents($dest, $xml, LOCK_EX) === false) {
        throw new RuntimeException("Konnte merchant-feed.xml nicht schreiben.");
    }

    return count($products);
}
