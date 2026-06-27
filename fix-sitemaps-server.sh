#!/bin/bash
# ============================================================================
# fix-sitemaps-server.sh
# Run from the cPanel Terminal. Fixes three problems:
#   1. Imports blog posts into the DB (so the blog sitemap is DB-driven)
#   2. Rebuilds sitemap-blog.xml from the DB
#   3. Creates the missing sitemap-index.xml (Google was getting a 404 HTML page)
#   4. Updates robots.txt to reference the sitemap-index as the single entry point
# ============================================================================

set -euo pipefail

HOME_DIR="${HOME:-/home/dokument}"
WEBROOT="$HOME_DIR/public_html"
SITE_URL="https://dokumentenkaufen.eu"

echo "=============================================="
echo "  Sitemap fix — dokumentenkaufen.eu"
echo "=============================================="

# Use public_html if it exists (server), else the current dir (local test).
WEBROOT="$HOME/public_html"
if [ ! -d "$WEBROOT" ]; then
    WEBROOT="$(pwd)"
fi
cd "$WEBROOT"
echo "Working in: $WEBROOT"

# ---- 1. Import blog posts into DB + rebuild blog sitemap ----
echo "[1/4] Importing blog posts + rebuilding blog sitemap..."
php -d display_errors=1 -r '
session_start();
$_SESSION["dk_admin_id"] = "admin";
$_SESSION["dk_admin_seen"] = time();
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
$_SERVER["REQUEST_METHOD"] = "GET";
require "admin/auth.php";
require "admin/blog-renderer.php";
require "admin/sitemap-builder.php";

// Ensure site URL is set.
dk_set_setting("site_url", "'"$SITE_URL"'");

// Import blog posts from disk into the DB.
require "admin/post-import.php";
$files = glob("blog/*.html");
$added = 0; $updated = 0; $skipped = 0;
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, ["index.html", "TEMPLATE.html"], true)) continue;
    try {
        $row = dk_parse_post_html($f);
        $action = dk_upsert_post($row);
        $$action++;
    } catch (Throwable $e) { $skipped++; }
}
echo "   Posts imported: $added neu, $updated aktualisiert, $skipped übersprungen\n";

// Rebuild the blog index + blog sitemap from DB.
dk_render_blog_index();
$n = dk_rebuild_blog_sitemap();
echo "   Blog-Index + sitemap-blog.xml neu erstellt ($n Posts)\n";
' 2>&1

# ---- 2. Create sitemap-index.xml ----
echo ""
echo "[2/4] Creating sitemap-index.xml..."
php -r '
require "admin/lib/helpers.php";
$siteUrl = "'"$SITE_URL"'";
$sitemaps = ["sitemap-products.xml", "sitemap-static.xml", "sitemap-categories.xml", "sitemap-blog.xml"];
$today = date("Y-m-d");
$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($sitemaps as $sf) {
    $path = dk_site_root() . "/" . $sf;
    $lastmod = file_exists($path) ? date("Y-m-d", filemtime($path)) : $today;
    $loc = $siteUrl . "/" . $sf;
    $xml .= "  <sitemap>\n";
    $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, "UTF-8") . "</loc>\n";
    $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
    $xml .= "  </sitemap>\n";
}
$xml .= "</sitemapindex>\n";
file_put_contents(dk_site_root() . "/sitemap-index.xml", $xml, LOCK_EX);
echo "   sitemap-index.xml erstellt (4 Teil-Sitemaps)\n";
' 2>&1

# ---- 3. Update robots.txt to reference the index as the main sitemap ----
echo ""
echo "[3/4] Updating robots.txt..."
php -r '
require "admin/lib/helpers.php";
$siteUrl = "'"$SITE_URL"'";
$txt  = "User-agent: *\n";
$txt .= "Allow: /\n";
$txt .= "Allow: /product/\n";
$txt .= "Allow: /category/\n";
$txt .= "Allow: /blog/\n";
$txt .= "Allow: /images/\n";
$txt .= "Allow: /css/\n";
$txt .= "Allow: /js/\n";
$txt .= "\n";
$txt .= "Disallow: /admin/\n";
$txt .= "Disallow: /data/\n";
$txt .= "Disallow: /private/\n";
$txt .= "Disallow: /tmp/\n";
$txt .= "Disallow: /cgi-bin/\n";
$txt .= "Disallow: /api/\n";
$txt .= "Disallow: /danke.html\n";
$txt .= "Disallow: /404.html\n";
$txt .= "Disallow: /blog/TEMPLATE.html\n";
$txt .= "\n";
// Reference the sitemap-index as the single entry point (Google prefers this).
$txt .= "Sitemap: " . $siteUrl . "/sitemap-index.xml\n";
file_put_contents(dk_site_root() . "/robots.txt", $txt, LOCK_EX);
echo "   robots.txt aktualisiert (referenziert sitemap-index.xml)\n";
' 2>&1

# ---- 4. Verify ----
echo ""
echo "[4/4] Verification..."
for sm in sitemap-index.xml sitemap-products.xml sitemap-blog.xml sitemap-static.xml sitemap-categories.xml; do
    if [ -f "$sm" ]; then
        head -c 40 "$sm" | grep -q "<?xml" && echo "   ✓ $sm — valid XML" || echo "   ✗ $sm — NOT valid XML!"
    else
        echo "   ✗ $sm — MISSING"
    fi
done
echo ""
echo "   robots.txt Sitemap line:"
grep "Sitemap:" robots.txt

echo ""
echo "=============================================="
echo "  ✓ FIX COMPLETE"
echo "=============================================="
echo "Submit this single URL to Google Search Console:"
echo "  $SITE_URL/sitemap-index.xml"
echo ""
echo "It will discover all sub-sitemaps automatically."
echo "=============================================="
