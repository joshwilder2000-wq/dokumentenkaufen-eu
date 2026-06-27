<?php
/**
 * Review moderation dashboard.
 *
 * Lists all reviews (filterable by status + product), and provides:
 *   - Approve / Reject / Delete
 *   - Edit (author, rating, title, body, image, AND review_date)
 * After any change, the affected product is re-rendered so the public page +
 * JSON-LD reflect the update immediately.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

// --- Handle actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = dk_db()->prepare('SELECT * FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if ($rev) {
            dk_db()->prepare('UPDATE reviews SET status = ?, updated_at = datetime("now") WHERE id = ?')
                ->execute([$newStatus, $id]);
            dk_rerender_product_for_review((int) $rev['product_id']);
            dk_flash('success', 'Bewertung ' . ($action === 'approve' ? 'freigegeben' : 'abgelehnt') . '.');
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = dk_db()->prepare('SELECT product_id FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if ($rev) {
            dk_db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            dk_rerender_product_for_review((int) $rev['product_id']);
            dk_flash('success', 'Bewertung gelöscht.');
        }

    } elseif ($action === 'approve_all') {
        $pending = dk_db()->query("SELECT DISTINCT product_id FROM reviews WHERE status = 'pending'")->fetchAll();
        dk_db()->exec("UPDATE reviews SET status = 'approved', updated_at = datetime('now') WHERE status = 'pending'");
        foreach ($pending as $row) {
            dk_rerender_product_for_review((int) $row['product_id']);
        }
        dk_flash('success', 'Alle wartenden Bewertungen freigegeben (' . count($pending) . ' Produkte aktualisiert).');

    } elseif ($action === 'save_edit') {
        // Detailed edit (incl. date).
        $id         = (int) ($_POST['id'] ?? 0);
        $authorName = dk_clean((string) ($_POST['author_name'] ?? ''));
        $authorEmail = dk_clean((string) ($_POST['author_email'] ?? ''));
        $rating     = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        $revTitle   = dk_clean((string) ($_POST['title'] ?? ''));
        $revBody    = dk_clean((string) ($_POST['body'] ?? ''));
        $reviewDate = dk_clean((string) ($_POST['review_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewDate)) {
            $reviewDate = date('Y-m-d');
        }
        $status     = in_array($_POST['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_POST['status'] : 'pending';

        $stmt = dk_db()->prepare('SELECT * FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if ($rev) {
            $image = (string) $rev['image'];
            // Optional new image upload.
            if (!empty($_FILES['image']['name'])) {
                try {
                    $image = dk_save_review_image($_FILES['image'], $rev['product_slug'] . '-review');
                } catch (Throwable $ex) {
                    dk_flash('success', 'Gespeichert, aber Bild-Fehler: ' . $ex->getMessage());
                }
            }
            dk_db()->prepare(
                'UPDATE reviews SET
                    author_name = ?, author_email = ?, rating = ?,
                    title = ?, body = ?, image = ?, review_date = ?,
                    status = ?, updated_at = datetime("now")
                 WHERE id = ?'
            )->execute([$authorName, $authorEmail, $rating, $revTitle, $revBody, $image, $reviewDate, $status, $id]);
            dk_rerender_product_for_review((int) $rev['product_id']);
            dk_flash('success', 'Bewertung aktualisiert (inkl. Datum).');
        }
    }

    header('Location: reviews.php' . (!empty($_POST['edit_id']) ? '?edit=' . (int) $_POST['edit_id'] : ''));
    exit;
}

/**
 * Re-render the product a review belongs to (so the public page + schema update).
 */
function dk_rerender_product_for_review(int $productId): void
{
    $stmt = dk_db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1');
    $stmt->execute([$productId]);
    if ($row = $stmt->fetch()) {
        dk_render_product($row);
    }
}

// --- Read filter + reviews ---
$statusFilter = (string) ($_GET['status'] ?? 'pending');
$productFilter = (int) ($_GET['product'] ?? 0);
$editing = (int) ($_GET['edit'] ?? 0);

$sql = 'SELECT r.*, p.title AS product_title FROM reviews r
        LEFT JOIN products p ON r.product_id = p.id WHERE 1=1';
$args = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $args[] = $statusFilter;
}
if ($productFilter > 0) {
    $sql .= ' AND r.product_id = ?';
    $args[] = $productFilter;
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$reviews = $stmt->fetchAll();

$counts = [
    'pending'  => (int) dk_db()->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(),
    'approved' => (int) dk_db()->query("SELECT COUNT(*) FROM reviews WHERE status='approved'")->fetchColumn(),
    'rejected' => (int) dk_db()->query("SELECT COUNT(*) FROM reviews WHERE status='rejected'")->fetchColumn(),
];

$products = dk_db()->query('SELECT id, title FROM products ORDER BY title ASC')->fetchAll();

$pageTitle = 'Bewertungen';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Bewertungen <span class="dk-muted dk-count">(<?php echo $counts['pending']; ?> wartend · <?php echo $counts['approved']; ?> freigegeben)</span></h1>
    <?php if ($counts['pending'] > 0): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Alle wartenden Bewertungen freigeben?');">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="action" value="approve_all">
        <button type="submit" class="dk-btn dk-btn-primary">Alle wartenden freigeben</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <select name="status" class="dk-input">
        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Wartend (<?php echo $counts['pending']; ?>)</option>
        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Freigegeben (<?php echo $counts['approved']; ?>)</option>
        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Abgelehnt (<?php echo $counts['rejected']; ?>)</option>
        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Alle</option>
    </select>
    <select name="product" class="dk-input">
        <option value="0">Alle Produkte</option>
        <?php foreach ($products as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo $productFilter === (int) $p['id'] ? 'selected' : ''; ?>><?php echo e($p['title']); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filtern</button>
</form>

<div class="dk-review-list">
<?php if (!$reviews): ?>
    <div class="dk-card dk-empty">Keine Bewertungen in dieser Ansicht.</div>
<?php endif; ?>
<?php foreach ($reviews as $r):
    $isEditing = $editing === (int) $r['id'];
?>
    <div class="dk-card dk-review-card <?php echo $isEditing ? 'dk-review-editing' : ''; ?>">
        <?php if ($isEditing): ?>
            <!-- EDIT MODE -->
            <form method="post" enctype="multipart/form-data">
                <?php echo dk_csrf_field(); ?>
                <input type="hidden" name="action" value="save_edit">
                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <input type="hidden" name="edit_id" value="<?php echo (int) $r['id']; ?>">
                <h3>Bewertung bearbeiten <small class="dk-muted">#<?php echo (int) $r['id']; ?></small></h3>
                <p class="dk-muted">Produkt: <?php echo e($r['product_title'] ?? $r['product_slug']); ?></p>
                <div class="dk-cards">
                    <div>
                        <div class="dk-field"><label>Name</label><input type="text" name="author_name" value="<?php echo e($r['author_name']); ?>"></div>
                        <div class="dk-field"><label>E-Mail</label><input type="email" name="author_email" value="<?php echo e($r['author_email']); ?>"></div>
                        <div class="dk-field"><label>Sterne (1–5)</label><input type="number" name="rating" value="<?php echo (int) $r['rating']; ?>" min="1" max="5"></div>
                        <div class="dk-field"><label>Datum (YYYY-MM-DD)</label><input type="date" name="review_date" value="<?php echo e($r['review_date']); ?>"></div>
                        <div class="dk-field"><label>Status</label>
                            <select name="status" class="dk-input">
                                <option value="pending" <?php echo $r['status'] === 'pending' ? 'selected' : ''; ?>>Wartend</option>
                                <option value="approved" <?php echo $r['status'] === 'approved' ? 'selected' : ''; ?>>Freigegeben</option>
                                <option value="rejected" <?php echo $r['status'] === 'rejected' ? 'selected' : ''; ?>>Abgelehnt</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="dk-field"><label>Titel</label><input type="text" name="title" value="<?php echo e($r['title']); ?>"></div>
                        <div class="dk-field"><label>Bewertungstext</label><textarea name="body" rows="6"><?php echo e($r['body']); ?></textarea></div>
                        <div class="dk-field">
                            <label>Bild</label>
                            <?php if ($r['image']): ?><img src="../<?php echo e($r['image']); ?>" class="dk-thumb dk-thumb-lg" alt=""><br><?php endif; ?>
                            <input type="file" name="image" accept="image/webp,image/jpeg,image/png">
                        </div>
                    </div>
                </div>
                <button type="submit" class="dk-btn dk-btn-primary">Speichern</button>
                <a href="reviews.php" class="dk-btn dk-btn-link">Abbrechen</a>
            </form>
        <?php else: ?>
            <!-- VIEW MODE -->
            <div class="dk-review-head">
                <span class="dk-stars-small"><?php echo dk_stars((int) $r['rating']); ?></span>
                <strong><?php echo e($r['author_name']); ?></strong>
                <span class="dk-muted"><?php echo e($r['author_email']); ?></span>
                <?php
                    $badgeClass = ['pending' => 'dk-badge-draft', 'approved' => 'dk-badge-ok', 'rejected' => 'dk-badge-rej'];
                    $badgeLabel = ['pending' => 'Wartend', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt'];
                ?>
                <span class="dk-badge <?php echo $badgeClass[$r['status']] ?? ''; ?>"><?php echo e($badgeLabel[$r['status']] ?? $r['status']); ?></span>
            </div>
            <div class="dk-review-meta dk-muted">
                Produkt: <a href="../product/<?php echo e($r['product_slug']); ?>.html" target="_blank"><?php echo e($r['product_title'] ?? $r['product_slug']); ?></a>
                · Datum: <?php echo e($r['review_date']); ?>
                · Eingereicht: <?php echo e(dk_format_date($r['created_at'])); ?>
            </div>
            <?php if ($r['title']): ?><p class="dk-review-title"><?php echo e($r['title']); ?></p><?php endif; ?>
            <p class="dk-review-body"><?php echo nl2br(e($r['body'])); ?></p>
            <?php if ($r['image']): ?><img src="../<?php echo e($r['image']); ?>" class="dk-thumb dk-thumb-lg" alt=""><?php endif; ?>
            <div class="dk-review-actions">
                <?php if ($r['status'] !== 'approved'): ?>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-primary">✓ Freigeben</button>
                </form>
                <?php endif; ?>
                <a href="reviews.php?edit=<?php echo (int) $r['id']; ?>" class="dk-btn dk-btn-sm">✎ Bearbeiten</a>
                <?php if ($r['status'] !== 'rejected'): ?>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-ghost">✕ Ablehnen</button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Bewertung endgültig löschen?');">
                    <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button type="submit" class="dk-btn dk-btn-sm dk-btn-danger">🗑 Löschen</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
