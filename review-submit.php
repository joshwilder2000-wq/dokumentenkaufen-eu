<?php
/**
 * Public review submission endpoint.
 *
 * Receives a review from the product-page review box, validates it, stores it
 * as 'pending' (for admin moderation), and redirects back to the product page
 * with a status message. No admin login required — this is the public-facing
 * form target.
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// --- Honeypot: if the hidden field is filled, silently succeed (bot bait). ---
if (!empty($_POST['website']) || !empty($_POST['company_url'])) {
    header('Location: ' . ($_POST['return_url'] ?? 'index.html') . '?review=thanks#review-form');
    exit;
}

// --- Gather input ---
$productId = (int) ($_POST['product_id'] ?? 0);
$productSlug = dk_clean((string) ($_POST['product_slug'] ?? ''));
$name       = dk_clean((string) ($_POST['review_name'] ?? ''));
$email      = dk_clean((string) ($_POST['review_email'] ?? ''));
$rating     = (int) ($_POST['rating'] ?? 0);
$title      = dk_clean((string) ($_POST['review_title'] ?? ''));
$body       = dk_clean((string) ($_POST['review_body'] ?? ''));
$returnUrl  = dk_clean((string) ($_POST['return_url'] ?? ''));

// Build a safe return URL (same-origin only).
if ($returnUrl === '' || !preg_match('#^/|[a-z0-9_-]+\.html#i', $returnUrl)) {
    $returnUrl = 'index.html';
}
$anchor = '#review-form';
$statusParam = '?review=error';

$errors = [];

// --- Validate ---
if ($productId <= 0) {
    $errors[] = 'Produkt nicht erkannt.';
} else {
    // Confirm the product exists + is published.
    $stmt = dk_db()->prepare('SELECT id, slug, title FROM products WHERE id = ? AND is_published = 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        $errors[] = 'Produkt nicht gefunden.';
    } else {
        $productSlug = $product['slug'];
        $returnUrl = 'product/' . $product['slug'] . '.html';
    }
}

if ($name === '') {
    $errors[] = 'Bitte Ihren Namen angeben.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
}
if ($rating < 1 || $rating > 5) {
    $errors[] = 'Bitte eine Bewertung von 1 bis 5 Sternen wählen.';
}
if (mb_strlen($body) < 10) {
    $errors[] = 'Bitte schreiben Sie mindestens einen kurzen Bewertungstext (10+ Zeichen).';
}
if (mb_strlen($body) > 3000) {
    $errors[] = 'Der Bewertungstext ist zu lang (max. 3000 Zeichen).';
}

// --- Rate limit: max 3 reviews per IP per hour. ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($ip !== 'unknown' && empty($errors)) {
    $stmt = dk_db()->prepare(
        "SELECT COUNT(*) FROM reviews WHERE reviewer_ip = ? AND created_at > datetime('now', '-1 hour')"
    );
    $stmt->execute([$ip]);
    if ((int) $stmt->fetchColumn() >= 3) {
        $errors[] = 'Zu viele Bewertungen in kurzer Zeit. Bitte später erneut versuchen.';
    }
}

// --- Image upload (optional) ---
$imagePath = '';
if (empty($errors) && !empty($_FILES['review_image']['name'])) {
    try {
        $imagePath = dk_save_review_image($_FILES['review_image'], $productSlug . '-review');
    } catch (Throwable $ex) {
        $errors[] = 'Bild: ' . $ex->getMessage();
    }
}

// --- Insert or fail ---
if (empty($errors)) {
    dk_db()->prepare(
        'INSERT INTO reviews
            (product_id, product_slug, author_name, author_email, rating,
             title, body, image, status, review_date, reviewer_ip)
         VALUES (?,?,?,?,?,?,?,?,"pending",date("now"),?)'
    )->execute([
        $productId, $productSlug, $name, $email, $rating,
        $title, $body, $imagePath, $ip,
    ]);

    $statusParam = '?review=thanks';
} else {
    // Pass errors back via the query string (short, joined).
    $statusParam = '?review=error&msg=' . rawurlencode(implode(' ', $errors));
}

header('Location: ' . $returnUrl . $statusParam . $anchor);
exit;
