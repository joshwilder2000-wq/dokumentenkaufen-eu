<?php
/**
 * Lightweight product view/click tracker.
 *
 * Called via GET /track.php?slug=xxx&type=view|click
 * Increments the impressions or clicks counter for the product.
 * Returns a 1x1 transparent pixel.
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'view'));

if ($slug === '' || !in_array($type, ['view', 'click'], true)) {
    http_response_code(204);
    exit;
}

$column = $type === 'click' ? 'clicks' : 'impressions';

dk_db()->prepare("UPDATE products SET {$column} = COALESCE({$column},0) + 1 WHERE slug = ?")
    ->execute([$slug]);

// Return transparent 1x1 GIF.
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
