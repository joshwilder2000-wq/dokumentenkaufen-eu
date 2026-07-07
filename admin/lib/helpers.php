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

/**
 * Ensure a slug is unique among blog posts (excluding a given id).
 */
function dk_unique_post_slug(string $slug, ?int $excludeId = null): string
{
    $base = $slug ?: 'beitrag';
    $candidate = $base;
    $n = 1;
    while (true) {
        $sql = 'SELECT id FROM posts WHERE slug = ?';
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

/**
 * Validate + store an uploaded image for a blog post.
 * Stores under images/blog/<filename>, WebP-converts JPG/PNG.
 * Returns the web-relative path or throws.
 */
function dk_save_post_image(array $file, string $preferredName = ''): string
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

    $blogDir = dk_site_root() . '/images/blog';
    if (!is_dir($blogDir) && !@mkdir($blogDir, 0755, true) && !is_dir($blogDir)) {
        throw new RuntimeException('Zielverzeichnis images/blog nicht beschreibbar.');
    }

    $baseName = $preferredName !== '' ? $preferredName : pathinfo($file['name'] ?? 'bild', PATHINFO_FILENAME);
    $baseName = dk_slugify($baseName);
    if ($baseName === '') {
        $baseName = 'beitrag-' . time();
    }

    $useWebp = $mime !== 'image/webp' && function_exists('imagecreatetruecolor');
    $ext = $useWebp ? 'webp' : $allowed[$mime];

    // Unique name scoped to images/blog/.
    $candidate = $baseName . '.' . $ext;
    $n = 1;
    while (file_exists($blogDir . '/' . $candidate)) {
        $candidate = $baseName . '-' . (++$n) . '.' . $ext;
    }
    $destRelative = 'images/blog/' . $candidate;
    $destAbsolute = $blogDir . '/' . $candidate;

    if ($useWebp) {
        dk_convert_to_webp($tmp, $mime, $destAbsolute);
    } else {
        if (!move_uploaded_file($tmp, $destAbsolute)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
    }

    return $destRelative;
}

/* ---------------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------------- */

/**
 * Ping Google about a product URL (index request).
 * Uses the google.com/ping endpoint. Best-effort — silently ignores failures.
 */
function dk_ping_google(string $url): bool
{
    $pingUrl = 'https://www.google.com/ping?sitemap=' . rawurlencode($url);
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
    $result = @file_get_contents($pingUrl, false, $ctx);
    return $result !== false;
}

/**
 * Send a message to Telegram via the Bot API.
 *
 * Requires telegram_bot_token + telegram_chat_id to be set in admin_settings.
 * Returns the telegram message_id (string) on success, or '' on failure/no-token.
 */
function dk_send_telegram(string $text): string
{
    $token  = dk_setting('telegram_bot_token', '');
    $chatId = dk_setting('telegram_chat_id', '');
    if (!$token || !$chatId) {
        return '';
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $postData = http_build_query([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]);

    // Try cURL first (more reliable on shared hosting).
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
        ]);
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($result === false) {
            return '';
        }
    } else {
        // Fallback to file_get_contents.
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $postData,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) {
            return '';
        }
    }

    $json = json_decode((string)$result, true);
    if (!empty($json['ok']) && !empty($json['result']['message_id'])) {
        return (string) $json['result']['message_id'];
    }
    return '';
}

/** Human-friendly date from a DB datetime string. */
function dk_format_date(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
}

/** Cache-busting version string for the shared stylesheet. */
function dk_asset_version(): string
{
    return date('Ymd');
}

/** Decode a JSON list field into an array; tolerant of old/empty values. */
function dk_json_list(?string $value): array
{
    if (!$value) {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }
    return $decoded;
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

/** The known blog/post categories. */
function dk_post_categories(): array
{
    return [
        'karriere-studium'       => 'Karriere & Studium',
        'pruefungsvorbereitung'  => 'Prüfungsvorbereitung',
        'anerkennung'            => 'Anerkennung & Beratung',
        'ratgeber'               => 'Ratgeber',
    ];
}

/* ---------------------------------------------------------------------------
 * Reviews
 * ------------------------------------------------------------------------- */

/**
 * Validate + store an uploaded review image.
 * Stores under images/reviews/<filename>, WebP-converts JPG/PNG (max 800px).
 * Returns the web-relative path or throws.
 */
function dk_save_review_image(array $file, string $preferredName = ''): string
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

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = ['image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Nur WebP, JPG oder PNG erlaubt (erkannt: ' . $mime . ').');
    }

    $dir = dk_site_root() . '/images/reviews';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Zielverzeichnis images/reviews nicht beschreibbar.');
    }

    $baseName = dk_slugify($preferredName !== '' ? $preferredName : pathinfo($file['name'] ?? 'bewertung', PATHINFO_FILENAME));
    if ($baseName === '') {
        $baseName = 'bewertung-' . time();
    }

    $useWebp = $mime !== 'image/webp' && function_exists('imagecreatetruecolor');
    $ext = $useWebp ? 'webp' : $allowed[$mime];

    $candidate = $baseName . '.' . $ext;
    $n = 1;
    while (file_exists($dir . '/' . $candidate)) {
        $candidate = $baseName . '-' . (++$n) . '.' . $ext;
    }
    $destRelative = 'images/reviews/' . $candidate;
    $destAbsolute = $dir . '/' . $candidate;

    if ($useWebp) {
        dk_convert_review_to_webp($tmp, $mime, $destAbsolute);
    } elseif (!move_uploaded_file($tmp, $destAbsolute)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }
    return $destRelative;
}

/** Convert a review image to WebP, constrained to 800px wide. */
function dk_convert_review_to_webp(string $source, string $sourceMime, string $destination): void
{
    switch ($sourceMime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
        case 'image/png':
            $img = @imagecreatefrompng($source);
            if ($img) { imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); }
            break;
        default: $img = false;
    }
    if (!$img) {
        throw new RuntimeException('Bild konnte nicht gelesen werden.');
    }
    $maxW = 800;
    $w = imagesx($img); $h = imagesy($img);
    if ($w > $maxW) {
        $newH = (int) round($h * ($maxW / $w));
        $resized = imagecreatetruecolor($maxW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }
    $ok = imagewebp($img, $destination, 80);
    imagedestroy($img);
    if (!$ok) {
        throw new RuntimeException('WebP-Konvertierung fehlgeschlagen.');
    }
}

/**
 * Fetch reviews for a product (optionally filtered by status).
 *
 * @return array<int,array>
 */
function dk_product_reviews(int $productId, string $status = 'approved'): array
{
    $sql = 'SELECT * FROM reviews WHERE product_id = ?';
    $args = [$productId];
    if ($status !== 'all') {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY review_date DESC, id DESC';
    $stmt = dk_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/**
 * Return aggregate rating for a product's approved reviews, or null if none.
 *
 * @return array{ratingValue:float,reviewCount:int}|null
 */
function dk_aggregate_rating(int $productId): ?array
{
    $stmt = dk_db()->prepare(
        "SELECT COUNT(*) AS n, AVG(rating) AS avg FROM reviews WHERE product_id = ? AND status = 'approved'"
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['n'] === 0) {
        return null;
    }
    return [
        'ratingValue' => round((float) $row['avg'], 1),
        'reviewCount' => (int) $row['n'],
    ];
}

/** Format a rating as ★ characters (1–5). */
function dk_stars(int $rating): string
{
    $rating = max(1, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/** Sanitize HTML from the WYSIWYG editor to a safe subset. */
function dk_sanitize_html(string $html): string
{
    // Allow common content tags; strip scripts, styles, iframes, event handlers.
    $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><h4><h5><h6><ul><ol><li>'
             . '<a><img><figure><figcaption><blockquote><table><thead><tbody><tr><td><th>'
             . '<hr><span><div>';
    $html = strip_tags($html, $allowed);
    // Remove on* event handler attributes and javascript: URLs.
    $html = preg_replace('#\s+on[a-z]+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace('#\s+on[a-z]+\s*=\s*\'[^\']*\'#i', '', $html);
    $html = preg_replace('#href\s*=\s*("|\')\s*javascript:#i', 'href=$1#', $html);
    $html = preg_replace('#src\s*=\s*("|\')\s*javascript:#i', 'src=$1#', $html);
    return trim($html);
}
