<?php
/**
 * Blog dashboard: post list with edit/delete/publish controls.
 * Compact layout matching the product dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/blog-renderer.php';

// --- Handle actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = dk_db()->prepare('SELECT slug FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) {
                dk_remove_blog_file((string) $row['slug']);
            }
            dk_db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            dk_render_blog_index();
            require_once __DIR__ . '/sitemap-builder.php';
            dk_rebuild_blog_sitemap();
            dk_flash('success', 'Beitrag gelöscht.');
        }
    } elseif ($action === 'toggle_publish') {
        $id = (int) ($_POST['id'] ?? 0);
        dk_db()->prepare('UPDATE posts SET is_published = 1 - is_published, updated_at = datetime("now") WHERE id = ?')
            ->execute([$id]);
        // Re-render or remove the affected post + index.
        $stmt = dk_db()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            if ($row['is_published']) {
                dk_render_blog_post($row);
            } else {
                dk_remove_blog_file($row['slug']);
            }
        }
        dk_render_blog_index();
        require_once __DIR__ . '/sitemap-builder.php';
        dk_rebuild_blog_sitemap();
        dk_flash('success', 'Veröffentlichungsstatus geändert.');
    }

    header('Location: blog-dashboard.php');
    exit;
}

// --- Read posts ---
$search = trim((string) ($_GET['q'] ?? ''));
$cat    = trim((string) ($_GET['cat'] ?? ''));

$sql = 'SELECT * FROM posts';
$where = [];
$args = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR slug LIKE ?)';
    $args[] = "%{$search}%";
    $args[] = "%{$search}%";
}
if ($cat !== '' && array_key_exists($cat, dk_post_categories())) {
    $where[] = 'category = ?';
    $args[] = $cat;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY is_published DESC, published_at DESC, title ASC';

$stmt = dk_db()->prepare($sql);
$stmt->execute($args);
$posts = $stmt->fetchAll();

$counts = dk_db()->query('SELECT COUNT(*) AS n FROM posts')->fetch()['n'];
$published = dk_db()->query('SELECT COUNT(*) AS n FROM posts WHERE is_published = 1')->fetch()['n'];

$pageTitle = 'Blog';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1>Blog <span class="dk-muted dk-count">(<?php echo (int)$counts; ?> Beiträge · <?php echo (int)$published; ?> veröffentlicht)</span></h1>
    <div class="dk-page-actions">
        <a href="post-edit.php" class="dk-btn dk-btn-primary">+ Neuer Beitrag</a>
    </div>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<form method="get" class="dk-filters">
    <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Beitrag suchen…" class="dk-input">
    <select name="cat" class="dk-input">
        <option value="">Alle Kategorien</option>
        <?php foreach (dk_post_categories() as $slug => $label): ?>
            <option value="<?php echo e($slug); ?>" <?php echo $cat === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="dk-btn dk-btn-ghost">Filtern</button>
    <?php if ($search !== '' || $cat !== ''): ?>
        <a href="blog-dashboard.php" class="dk-btn dk-btn-link">Zurücksetzen</a>
    <?php endif; ?>
</form>

<div class="dk-table-wrap">
<table class="dk-table dk-table-compact">
    <thead>
        <tr>
            <th class="col-status" title="Veröffentlichungsstatus">●</th>
            <th>Titel / Slug</th>
            <th class="col-cat">Kategorie</th>
            <th class="col-date">Veröffentlicht</th>
            <th class="col-date">Geändert</th>
            <th class="col-actions">Aktionen</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$posts): ?>
        <tr><td colspan="6" class="dk-empty">Keine Beiträge gefunden.
            <a href="post-edit.php">Ersten Beitrag erstellen →</a>
        </td></tr>
    <?php endif; ?>
    <?php foreach ($posts as $p): ?>
        <tr class="<?php echo $p['is_published'] ? 'dk-row-pub' : 'dk-row-draft'; ?>">
            <td class="col-status">
                <span class="dk-dot <?php echo $p['is_published'] ? 'dk-dot-on' : 'dk-dot-off'; ?>"
                      title="<?php echo $p['is_published'] ? 'Veröffentlicht' : 'Entwurf'; ?>"></span>
            </td>
            <td class="col-title">
                <a href="post-edit.php?id=<?php echo (int)$p['id']; ?>" class="dk-row-title"><?php echo e($p['title']); ?></a>
                <code class="dk-row-slug">/blog/<?php echo e($p['slug']); ?>.html</code>
            </td>
            <td class="col-cat"><?php echo e(dk_post_categories()[$p['category']] ?? $p['category']); ?></td>
            <td class="col-date dk-muted"><?php echo e($p['published_at'] ? date('d.m.Y', strtotime($p['published_at'])) : '—'); ?></td>
            <td class="col-date dk-muted"><?php echo e(dk_format_date($p['updated_at'])); ?></td>
            <td class="col-actions dk-actions">
                <a href="post-edit.php?id=<?php echo (int)$p['id']; ?>" class="dk-icon-btn" title="Bearbeiten">✎</a>
                <a href="../blog/<?php echo e($p['slug']); ?>.html" target="_blank" class="dk-icon-btn" title="Ansehen">↗</a>
                <form method="post" style="display:inline">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_publish">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="dk-icon-btn" title="<?php echo $p['is_published'] ? 'Verstecken (auf Entwurf)' : 'Veröffentlichen'; ?>"><?php echo $p['is_published'] ? '◐' : '○'; ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Beitrag wirklich löschen? Die HTML-Datei wird entfernt.');">
                    <?php echo dk_csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <button type="submit" class="dk-icon-btn dk-icon-danger" title="Löschen">🗑</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
