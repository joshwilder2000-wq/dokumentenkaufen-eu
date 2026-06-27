#!/bin/bash
# ============================================================================
# Server-side deploy script for dokumentenkaufen.eu
# Run this from the cPanel Terminal (or via SSH). It:
#   1. Clones/pulls the site from GitHub into a temp dir
#   2. Backs up any existing public_html
#   3. Installs the static site + admin CMS into public_html
#   4. Sets correct permissions
#   5. Seeds the SQLite DB with 67 products (optional)
#
# Usage:   bash deploy-server.sh
# Re-run:  safe — it refreshes from GitHub each time.
# ============================================================================

set -euo pipefail

# ---- Config ----------------------------------------------------------------
REPO_URL="https://github.com/joshwilder2000-wq/dokumentenkaufen-eu.git"
REPO_BRANCH="main"
SITE_URL="https://dokumentenkaufen.eu"

# ---- Detect paths ----------------------------------------------------------
HOME_DIR="${HOME:-/home/dokument}"
WEBROOT="$HOME_DIR/public_html"
TMP_CLONE="$HOME_DIR/.dk-deploy-tmp"
BACKUP_DIR="$HOME_DIR/public_html.backup.$(date +%Y%m%d-%H%M%S)"

echo "=============================================="
echo "  dokumentenkaufen.eu — server deploy"
echo "=============================================="
echo "Webroot:   $WEBROOT"
echo "Home:      $HOME_DIR"
echo "Site URL:  $SITE_URL"
echo ""

# ---- 1. Clone / update from GitHub ----------------------------------------
echo "[1/5] Fetching site from GitHub..."
rm -rf "$TMP_CLONE"
if git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$TMP_CLONE" 2>/dev/null; then
    echo "   ✓ Cloned from GitHub ($REPO_BRANCH)"
else
    echo "   ✗ Could not clone. Is the repo reachable/public? Check REPO_URL."
    exit 1
fi

if [ ! -f "$TMP_CLONE/index.html" ]; then
    echo "   ✗ Clone has no index.html — wrong repo/branch?"
    exit 1
fi

# ---- 2. Back up existing public_html --------------------------------------
echo ""
echo "[2/5] Backing up existing content..."
if [ -d "$WEBROOT" ] && [ "$(ls -A "$WEBROOT" 2>/dev/null)" ]; then
    mv "$WEBROOT" "$BACKUP_DIR"
    mkdir -p "$WEBROOT"
    echo "   ✓ Existing content moved to $BACKUP_DIR"
else
    mkdir -p "$WEBROOT"
    echo "   (public_html was empty — no backup needed)"
fi

# ---- 3. Install files ------------------------------------------------------
echo ""
echo "[3/5] Installing site files..."
# Copy everything except git metadata and deploy scripts
for item in "$TMP_CLONE"/*; do
    [ -e "$item" ] || continue
    name=$(basename "$item")
    case "$name" in
        deploy-server.sh|deploy.sh|.git|.gitignore) continue ;;
    esac
    cp -a "$item" "$WEBROOT/"
done
# Hidden files that belong (e.g. .htaccess, .htaccess)
cp -a "$TMP_CLONE/.htaccess" "$WEBROOT/" 2>/dev/null || true
cp -a "$TMP_CLONE/.gitignore" "$WEBROOT/" 2>/dev/null || true
echo "   ✓ Files installed to $WEBROOT"
echo "   Files: $(find "$WEBROOT" -type f | wc -l)"

# ---- 4. Permissions --------------------------------------------------------
echo ""
echo "[4/5] Setting permissions..."
# Directories: 755, files: 644, PHP executable, data dir writable.
find "$WEBROOT" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$WEBROOT" -type f -exec chmod 644 {} \; 2>/dev/null || true
# PHP files: 644 is fine on most hosts; ensure they're readable.
mkdir -p "$WEBROOT/data" "$WEBROOT/images/products" 2>/dev/null
chmod 755 "$WEBROOT/data" "$WEBROOT/images" "$WEBROOT/images/products"
# The SQLite DB and uploads must be writable by the web server.
chmod 777 "$WEBROOT/data" 2>/dev/null || true
# Remove any stale DB so a fresh one builds on first admin hit.
rm -f "$WEBROOT/data/products.sqlite"*
echo "   ✓ Permissions set (dirs 755, files 644, data/ writable)"

# ---- 5. Seed the database (via PHP) ---------------------------------------
echo ""
echo "[5/5] Setting site URL + seeding 67 products..."
php -d display_errors=1 -r '
$root = "'"$WEBROOT"'";
chdir($root);
session_start();
$_SESSION["dk_admin_id"] = "admin";
$_SESSION["dk_admin_seen"] = time();
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "dokumentenkaufen.eu";
$_SERVER["HTTPS"] = "on";
require "admin/auth.php";
require "admin/sitemap-builder.php";

// Set the canonical site URL.
dk_set_setting("site_url", "'"$SITE_URL"'");
echo "   ✓ Site URL: " . dk_setting("site_url") . "\n";

// Seed products.
require "admin/import.php";
$files = glob("product/*.html");
$added = 0; $updated = 0; $skipped = 0;
foreach ($files as $f) {
    try {
        $row = dk_parse_product_html($f);
        $action = dk_upsert_product($row);
        $$action++;
    } catch (Throwable $e) { $skipped++; }
}
dk_rebuild_all_sitemaps();
echo "   ✓ Products: $added added, $updated updated, $skipped skipped (total files: " . count($files) . ")\n";
echo "   ✓ Sitemaps rebuilt.\n";
' 2>&1

# ---- Cleanup ---------------------------------------------------------------
rm -rf "$TMP_CLONE"

echo ""
echo "=============================================="
echo "  ✓ DEPLOY COMPLETE"
echo "=============================================="
echo "Live site:   $SITE_URL"
echo "Admin panel: $SITE_URL/admin/"
echo "Login:       admin / dk-admin-2026"
echo ""
echo "Existing content (if any) backed up to:"
echo "  $BACKUP_DIR"
echo ""
echo "IMPORTANT: Change the admin password after first login!"
echo "=============================================="
