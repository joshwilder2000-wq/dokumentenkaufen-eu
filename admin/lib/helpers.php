<?php
/**
 * Shared helper functions for the admin backend.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ---------------------------------------------------------------------------
 * Site URL
 * ------------------------------------------------------------------------- */

/**
 * The public site URL, with no trailing slash.
 * Read from the DB if set, otherwise detected from the request, otherwise a
 * sensible default. Change it once from the admin "Settings" if autodetect is
 * wrong behind a proxy.
 */
function dk_site_url(): string
{
    $stored = dk_setting('site_url');
    if ($stored && $stored !== '') {
        return rtrim($stored, '/');
    }

    // Autodetect from the request.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $host = $_SERVER['HTTP_HOST']
        ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    return ($https ? 'https' : 'http') . '://' . preg_replace('/[^a-z0-9.\-:]/i', '', $host);
}

/* ---------------------------------------------------------------------------
 * Output / input safety
 * ------------------------------------------------------------------------- */

/** HTML-escape a value for output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim + basic text sanitisation. */
function dk_clean(string $value): string
{
    return trim($value);
}

/**
 * Convert a German/free-form title into a URL-safe slug (ASCII).
 * e.g. "Hochschule Hamm-Lippstadt Urkunde" -> "hochschule-hamm-lippstadt-urkunde"
 */
function dk_slugify(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // German umlauts / ß to ASCII equivalents (matches the site's URL conventions).
    $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
    ];
    $text = strtr($text, $map);

    // ASCII transliteration for anything else.
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
    } else {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    $text = strtolower((string) $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

/**
 * Ensure a slug is unique among products (excluding a given id).
 */
function dk_unique_slug(string $slug, ?int $excludeId = null): string
{
    $base = $slug ?: 'produkt';
    $candidate = $base;
    $n = 1;
    while (true) {
        $sql = 'SELECT id FROM products WHERE slug = ?';
        $args = [$candidate];
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $args[] = $excludeId;
        }
        $stmt = dk_db()->prepare($sql);
        $stmt->execute($args);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = $base . '-' . (++$n);
    }
}

/* ---------------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------------- */

function dk_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function dk_csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(dk_csrf_token(), (string) $token)) {
        http_response_code(419);
        die('Ungültige Sitzung (CSRF). Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
    }
}

/** Render a hidden CSRF input for forms. */
function dk_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(dk_csrf_token()) . '">';
}

/* ---------------------------------------------------------------------------
 * Flash messages (one-shot, session based)
 * ------------------------------------------------------------------------- */

function dk_flash(string $key, ?string $message = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/* ---------------------------------------------------------------------------
 * Image upload handling
 * ------------------------------------------------------------------------- */

/**
 * Validate + store an uploaded image for a product.
 *
 * Accepts WebP/JPG/PNG (validated by real MIME, not extension).
 * Stores under images/products/<filename>. Converts to WebP when GD is available
 * and the source is JPG/PNG, to keep the site on WebP per the AGENTS.md standard.
 *
 * Returns the web-relative path (e.g. "images/products/foo.webp") or throws.
 */
function dk_save_product_image(array $file, string $preferredName = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Kein Bild hochgeladen.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload-Fehler (Code ' . $file['error'] . ').');
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Ungültige Upload-Datei.');
    }

    // Real MIME detection (fileinfo extension).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = [
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Nur WebP, JPG oder PNG erlaubt (erkannt: ' . $mime . ').');
    }

    $productsDir = dk_site_root() . '/images/products';
    if (!is_dir($productsDir) && !@mkdir($productsDir, 0755, true) && !is_dir($productsDir)) {
        throw new RuntimeException('Zielverzeichnis images/products nicht beschreibbar.');
    }

    $baseName = $preferredName !== '' ? $preferredName : pathinfo($file['name'] ?? 'bild', PATHINFO_FILENAME);
    $baseName = dk_slugify($baseName);
    if ($baseName === '') {
        $baseName = 'produkt-' . time();
    }

    // Prefer delivering WebP. Convert JPG/PNG if GD supports it.
    $useWebp = $mime !== 'image/webp'
        && function_exists('imagecreatetruecolor');

    $ext = $useWebp ? 'webp' : $allowed[$mime];
    $destRelative = 'images/products/' . dk_unique_image_name($baseName, $ext);
    $destAbsolute = dk_site_root() . '/' . $destRelative;

    if ($useWebp) {
        dk_convert_to_webp($tmp, $mime, $destAbsolute);
    } else {
        if (!move_uploaded_file($tmp, $destAbsolute)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
    }

    return $destRelative;
}

/**
 * Ensure the chosen image filename doesn't collide with an existing file.
 */
function dk_unique_image_name(string $baseName, string $ext): string
{
    $candidate = $baseName . '.' . $ext;
    $n = 1;
    while (file_exists(dk_site_root() . '/images/products/' . $candidate)) {
        $candidate = $baseName . '-' . (++$n) . '.' . $ext;
    }
    return $candidate;
}

/**
 * Convert a JPG/PNG source to WebP at the destination path using GD.
 */
function dk_convert_to_webp(string $source, string $sourceMime, string $destination): void
{
    switch ($sourceMime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($source);
            if ($img) {
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
            }
            break;
        default:
            $img = false;
    }

    if (!$img) {
        throw new RuntimeException('Bild konnte nicht gelesen werden.');
    }

    // Constrain large images; keep aspect ratio. Product images are 4:3.
    $maxW = 1200;
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $maxW) {
        $newH = (int) round($h * ($maxW / $w));
        $resized = imagecreatetruecolor($maxW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $ok = imagewebp($img, $destination, 82);
    imagedestroy($img);

    if (!$ok) {
        throw new RuntimeException('WebP-Konvertierung fehlgeschlagen.');
    }
}

/* ---------------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------------- */

/** Human-friendly date from a DB datetime string. */
function dk_format_date(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
}

/** The known product categories (matches the /category/ pages). */
function dk_categories(): array
{
    return [
        'universitaetsdokumente' => 'Universitätsdokumente',
        'ihk-zertifikate'        => 'IHK-Zertifikate',
        'hwk-meisterbriefe'      => 'HWK / Meisterbriefe',
        'sprachzertifikate'      => 'Sprachzertifikate',
        'gewerbeordnung'         => 'Gewerbeordnung',
    ];
}
