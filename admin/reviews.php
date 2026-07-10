<?php
/**
 * Review moderation dashboard — card-based layout (English UI).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

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
            dk_flash('success', 'Review ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = dk_db()->prepare('SELECT product_id FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $rev = $stmt->fetch();
        if ($rev) {
            dk_db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            dk_rerender_product_for_review((int) $rev['product_id']);
            dk_flash('success', 'Review deleted.');
        }
    } elseif ($action === 'approve_all') {
        $pending = dk_db()->query("SELECT DISTINCT product_id FROM reviews WHERE status = 'pending'")->fetchAll();
        dk_db()->exec("UPDATE reviews SET status = 'approved', updated_at = datetime('now') WHERE status = 'pending'");
        foreach ($pending as $row) {
            dk_rerender_product_for_review((int) $row['product_id']);
        }
        dk_flash('success', 'All pending reviews approved.');
    } elseif ($action === 'save_edit') {
        $id         = (int) ($_POST['id'] ?? 0);
        $authorName = dk_clean((string) ($_POST['author_name'] ?? ''));
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
            dk_db()->prepare(
                'UPDATE reviews SET author_name = ?, rating = ?, title = ?, body = ?, review_date = ?, status = ?, updated_at = datetime("now") WHERE id = ?'
            )->execute([$authorName, $rating, $revTitle, $revBody, $reviewDate, $status, $id]);
            dk_rerender_product_for_review((int) $rev['product_id']);
            dk_flash('success', 'Review updated.');
        }
    }

    header('Location: reviews.php');
    exit;
}

function dk_rerender_product_for_review(int $productId): void
{
    $stmt = dk_db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1');
    $stmt->execute([$productId]);
    if ($row = $stmt->fetch()) {
        dk_render_product($row);
    }
}

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

$pageTitle = 'Reviews';
include __DIR__ . '/partials/header.php';
?>
<style>
.dk-rev-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;margin-top:20px}
.dk-rev-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.dk-rev-card-top{padding:16px;display:flex;gap:14px;align-items:flex-start}
.dk-rev-img{width:80px;height:80px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid #e0e0e0;background:#f5f5f5}
.dk-rev-img-empty{width:80px;height:80px;border-radius:8px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:.7rem;flex-shrink:0;border:1px solid #e0e0e0}
.dk-rev-info{flex:1;min-width:0}
.dk-rev-stars{color:#f59e0b;font-size:.9rem;letter-spacing:1px;margin-bottom:2px}
.dk-rev-author{font-weight:600;font-size:.95rem;color:#000}
.dk-rev-title{font-size:.88rem;font-weight:500;color:#333;margin:4px 0}
.dk-rev-body{font-size:.82rem;color:#777;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.dk-rev-meta{padding:0 16px 8px;font-size:.72rem;color:#999;display:flex;gap:8px;flex-wrap:wrap}
.dk-rev-actions{display:flex;gap:4px;padding:10px 16px;border-top:1px solid #f0f0f0;background:#fafafa;flex-wrap:wrap}
.dk-rev-actions a,.dk-rev-actions button{padding:7px 12px;border:1px solid #e0e0e0;border-radius:6px;font-size:.78rem;font-weight:500;cursor:pointer;text-decoration:none;background:#fff;transition:background .12s;font-family:inherit;display:inline-flex;align-items:center;gap:4px}
.dk-rev-actions a:hover,.dk-rev-actions button:hover{background:#f0f0f0}
.dk-rev-actions .act-approve{background:#15803d;color:#fff;border-color:#15803d}
.dk-rev-actions .act-del:hover{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
@media(max-width:600px){.dk-rev-grid{grid-template-columns:1fr}}
</style>

<div class="dk-page-head">
    <h1>Reviews <span class="dk-muted dk-count">(<?php echo $counts['pending']; ?> pending · <?php echo $counts['approved']; ?> approved)</span></h1>
    <?php if ($counts['pending'] > 0): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Approve all pending?');">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="action" value="approve_all">
        <button type="submit" class="dk-btn dk-btn-primary">Approve All</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <select name="status" class="dk-input">
        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $counts['pending']; ?>)</option>
        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved (<?php echo $counts['approved']; ?>)</option>
        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $counts['rejected']; ?>)</option>
        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All</option>
    </select>
    <select name="product" class="dk-input">
        <option value="0">All Products</option>
        <?php foreach ($products as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo $productFilter === (int) $p['id'] ? 'selected' : ''; ?>><?php echo e($p['title']); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filter</button>
</form>

<div class="dk-rev-grid">
<?php if (!$reviews): ?>
    <div class="dk-card dk-empty">No reviews found.</div>
<?php endif; ?>
<?php foreach ($reviews as $r):
    if ($editing === (int)$r['id']) { continue; }
?>
    <div class="dk-rev-card">
        <div class="dk-rev-card-top">
            <?php if ($r['image']): ?>
                <img src="../<?php echo e($r['image']); ?>" alt="" class="dk-rev-img" loading="lazy">
            <?php else: ?>
                <div class="dk-rev-img-empty">No Photo</div>
            <?php endif; ?>
            <div class="dk-rev-info">
                <div class="dk-rev-stars"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></div>
                <div class="dk-rev-author"><?php echo e($r['author_name']); ?></div>
                <?php if ($r['title']): ?>
                    <div class="dk-rev-title"><?php echo e($r['title']); ?></div>
                <?php endif; ?>
                <div class="dk-rev-body"><?php echo e($r['body']); ?></div>
            </div>
        </div>
        <div class="dk-rev-meta">
            <?php
                $bc = ['pending' => 'dk-badge-draft', 'approved' => 'dk-badge-ok', 'rejected' => 'dk-badge-rej'];
                $bl = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
            ?>
            <span class="dk-badge <?php echo $bc[$r['status']] ?? ''; ?>"><?php echo e($bl[$r['status']] ?? $r['status']); ?></span>
            <span>📦 <?php echo e($r['product_title'] ?? $r['product_slug']); ?></span>
            <span>📅 <?php echo e($r['review_date']); ?></span>
        </div>
        <div class="dk-rev-actions">
            <?php if ($r['status'] !== 'approved'): ?>
            <form method="post" style="display:inline">
                <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <button type="submit" class="act-approve">✓ Approve</button>
            </form>
            <?php endif; ?>
            <a href="reviews.php?edit=<?php echo (int) $r['id']; ?>">✎ Edit</a>
            <a href="../product/<?php echo e($r['product_slug']); ?>.html" target="_blank">↗ View</a>
            <?php if ($r['status'] !== 'rejected'): ?>
            <form method="post" style="display:inline">
                <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <button type="submit">✕ Reject</button>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete review?');">
                <?php echo dk_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <button type="submit" class="act-del">🗑 Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php
if ($editing > 0):
    $stmt = dk_db()->prepare('SELECT * FROM reviews WHERE id = ?');
    $stmt->execute([$editing]);
    $rev = $stmt->fetch();
    if ($rev):
?>
<div class="dk-card" style="margin-top:24px;border:2px solid #000">
    <h3>Edit Review #<?php echo (int)$rev['id']; ?></h3>
    <form method="post" enctype="multipart/form-data">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="action" value="save_edit">
        <input type="hidden" name="id" value="<?php echo (int)$rev['id']; ?>">
        <div class="dk-cards">
            <div>
                <div class="dk-field"><label>Author Name</label><input type="text" name="author_name" value="<?php echo e($rev['author_name']); ?>"></div>
                <div class="dk-field"><label>Rating (1-5)</label><input type="number" name="rating" value="<?php echo (int)$rev['rating']; ?>" min="1" max="5"></div>
                <div class="dk-field"><label>Date</label><input type="date" name="review_date" value="<?php echo e($rev['review_date']); ?>"></div>
                <div class="dk-field"><label>Status</label>
                    <select name="status" class="dk-input">
                        <option value="pending" <?php echo $rev['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $rev['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $rev['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
            </div>
            <div>
                <div class="dk-field"><label>Title</label><input type="text" name="title" value="<?php echo e($rev['title']); ?>"></div>
                <div class="dk-field"><label>Body</label><textarea name="body" rows="5"><?php echo e($rev['body']); ?></textarea></div>
                <?php if ($rev['image']): ?>
                    <img src="../<?php echo e($rev['image']); ?>" style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid #e0e0e0;margin-bottom:8px">
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="dk-btn dk-btn-primary">Save Changes</button>
        <a href="reviews.php" class="dk-btn dk-btn-link">Cancel</a>
    </form>
</div>
<?php endif; endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
