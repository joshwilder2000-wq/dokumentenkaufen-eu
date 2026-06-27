<?php
/**
 * Create / edit a blog post.
 *
 * Uses a TinyMCE WYSIWYG editor for the body content. On save: validates input,
 * upserts the DB row, regenerates the static .html via the blog renderer, and
 * rebuilds the blog sitemap + blog index.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/blog-renderer.php';
require_once __DIR__ . '/sitemap-builder.php';

$id = (int) ($_GET['id'] ?? 0);
$post = null;

if ($id > 0) {
    $stmt = dk_db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        http_response_code(404);
        die('Beitrag nicht gefunden.');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();

    $title     = dk_clean((string) ($_POST['title'] ?? ''));
    $slugRaw   = dk_clean((string) ($_POST['slug'] ?? ''));
    $slug      = dk_unique_post_slug($slugRaw !== '' ? dk_slugify($slugRaw) : dk_slugify($title), $id ?: null);
    $metaDesc  = dk_clean((string) ($_POST['meta_description'] ?? ''));
    $keywords  = dk_clean((string) ($_POST['meta_keywords'] ?? ''));
    $category  = dk_clean((string) ($_POST['category'] ?? 'karriere-studium'));
    $excerpt   = dk_sanitize_html((string) ($_POST['excerpt'] ?? ''));
    $content   = dk_sanitize_html((string) ($_POST['content'] ?? ''));
    $author    = dk_clean((string) ($_POST['author'] ?? 'Dokuments Hub'));
    $pubDate   = dk_clean((string) ($_POST['published_at'] ?? ''));
    $published = isset($_POST['is_published']) ? 1 : 0;

    // Validate date format (YYYY-MM-DD) or empty.
    if ($pubDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pubDate)) {
        $pubDate = date('Y-m-d');
    } elseif ($pubDate === '') {
        $pubDate = date('Y-m-d');
    }

    if ($title === '') {
        $errors[] = 'Ein Titel ist erforderlich.';
    }
    if (!array_key_exists($category, dk_post_categories())) {
        $category = 'karriere-studium';
    }

    // Image upload (optional).
    $ogImage = (string) ($post['og_image'] ?? '');
    if (!empty($_FILES['image']['name'])) {
        try {
            $ogImage = dk_save_post_image($_FILES['image'], $slug);
        } catch (Throwable $ex) {
            $errors[] = 'Bild: ' . $ex->getMessage();
        }
    }
    if (!$ogImage && !empty($_POST['existing_image'])) {
        $ogImage = dk_clean((string) $_POST['existing_image']);
    }

    if (!$errors) {
        if ($id) {
            if ($post['slug'] !== $slug) {
                dk_remove_blog_file((string) $post['slug']);
            }
            dk_db()->prepare(
                'UPDATE posts SET
                    slug = ?, title = ?, meta_description = ?, meta_keywords = ?,
                    category = ?, og_image = ?, excerpt = ?, content = ?,
                    author = ?, published_at = ?, is_published = ?,
                    updated_at = datetime("now")
                 WHERE id = ?'
            )->execute([
                $slug, $title, $metaDesc, $keywords,
                $category, $ogImage, $excerpt, $content,
                $author, $pubDate, $published, $id,
            ]);
        } else {
            dk_db()->prepare(
                'INSERT INTO posts
                    (slug, title, meta_description, meta_keywords, category, og_image,
                     excerpt, content, author, published_at, is_published, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,0)'
            )->execute([
                $slug, $title, $metaDesc, $keywords,
                $category, $ogImage, $excerpt, $content,
                $author, $pubDate, $published,
            ]);
            $id = (int) dk_db()->lastInsertId();
        }

        // Regenerate the static file + index + sitemaps.
        $fresh = dk_db()->prepare('SELECT * FROM posts WHERE id = ?');
        $fresh->execute([$id]);
        $row = $fresh->fetch();
        if ($row['is_published']) {
            dk_render_blog_post($row);
        } else {
            dk_remove_blog_file($row['slug']);
        }
        dk_render_blog_index();
        dk_rebuild_all_sitemaps();

        dk_flash('success', 'Beitrag gespeichert.');
        header('Location: post-edit.php?id=' . $id . '&saved=1');
        exit;
    }

    // Repopulate from submitted data on error.
    $post = [
        'id' => $id ?: null,
        'slug' => $slug, 'title' => $title, 'meta_description' => $metaDesc,
        'meta_keywords' => $keywords, 'category' => $category, 'og_image' => $ogImage,
        'excerpt' => $excerpt, 'content' => $content, 'author' => $author,
        'published_at' => $pubDate, 'is_published' => $published,
    ];
}

$pageTitle = ($post && !empty($post['id']) ? 'Beitrag bearbeiten' : 'Neuer Beitrag');
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head">
    <h1><?php echo e($pageTitle); ?></h1>
    <a href="blog-dashboard.php" class="dk-btn dk-btn-link">← Zurück</a>
</div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="dk-form-grid">
    <?php echo dk_csrf_field(); ?>

    <div class="dk-form-main">
        <div class="dk-card">
            <div class="dk-field">
                <label for="title">Titel <span class="dk-req">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo e($post['title'] ?? ''); ?>" required autofocus
                       oninput="document.getElementById('slug-preview').textContent = dkSlug(document.getElementById('title').value);">
            </div>

            <div class="dk-field">
                <label for="slug">URL-Slug</label>
                <div class="dk-slug-row">
                    <code>/blog/</code>
                    <input type="text" id="slug" name="slug" value="<?php echo e($post['slug'] ?? ''); ?>" placeholder="wird aus Titel generiert">
                    <code>.html</code>
                </div>
                <small class="dk-muted">Vorschau: <span id="slug-preview" class="dk-slug-preview"><?php echo e($post['slug'] ?? ''); ?></span></small>
            </div>

            <div class="dk-field">
                <label for="excerpt">Einleitung / Kurztext (erscheint über dem Artikel)</label>
                <textarea id="excerpt" name="excerpt" rows="3" placeholder="<p>Kurze Einleitung zum Beitrag…</p>"><?php echo e($post['excerpt'] ?? ''); ?></textarea>
                <small class="dk-muted">HTML erlaubt. Wird im Artikel oben und als Auszug auf der Blog-Übersicht gezeigt.</small>
            </div>

            <div class="dk-field">
                <label for="content">Beitragsinhalt <span class="dk-req">*</span></label>
                <textarea id="content" name="content" rows="18"><?php echo e($post['content'] ?? ''); ?></textarea>
                <small class="dk-muted">WYSIWYG-Editor (TinyMCE). Schreibt sauberes HTML. Wenn der Editor nicht lädt, ist das Feld eine normale Textarea.</small>
            </div>
        </div>

        <div class="dk-card">
            <h3>Suchmaschinen-Optimierung (SEO)</h3>
            <div class="dk-field">
                <label for="meta_description">Meta-Beschreibung <small>(150–160 Zeichen)</small></label>
                <textarea id="meta_description" name="meta_description" rows="2" maxlength="170"><?php echo e($post['meta_description'] ?? ''); ?></textarea>
                <small class="dk-muted"><span id="meta-count"><?php echo mb_strlen($post['meta_description'] ?? ''); ?></span>/170 Zeichen.</small>
            </div>
            <div class="dk-field">
                <label for="meta_keywords">Meta-Keywords</label>
                <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo e($post['meta_keywords'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <div class="dk-form-side">
        <div class="dk-card">
            <h3>Veröffentlichung</h3>
            <div class="dk-field dk-check">
                <label>
                    <input type="checkbox" name="is_published" value="1" <?php echo (($post['is_published'] ?? 1) ? 'checked' : ''); ?>>
                    Veröffentlicht (Seite ist live + im Blog-Index + in der Sitemap)
                </label>
            </div>
            <div class="dk-field">
                <label for="published_at">Veröffentlichungsdatum</label>
                <input type="date" id="published_at" name="published_at" value="<?php echo e($post['published_at'] ?? date('Y-m-d')); ?>">
            </div>
            <div class="dk-field">
                <label for="author">Autor</label>
                <input type="text" id="author" name="author" value="<?php echo e($post['author'] ?? 'Dokuments Hub'); ?>">
            </div>
            <div class="dk-field">
                <label for="category">Kategorie</label>
                <select id="category" name="category" class="dk-input">
                    <?php foreach (dk_post_categories() as $slug => $label): ?>
                        <option value="<?php echo e($slug); ?>" <?php echo (($post['category'] ?? '') === $slug ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="dk-card">
            <h3>Beitragsbild</h3>
            <?php if (!empty($post['og_image'])): ?>
                <img src="../<?php echo e($post['og_image']); ?>" alt="" class="dk-preview-img" loading="lazy">
            <?php endif; ?>
            <div class="dk-field">
                <label for="image">Neues Bild hochladen</label>
                <input type="file" id="image" name="image" accept="image/webp,image/jpeg,image/png">
                <small class="dk-muted">WebP/JPG/PNG. Wird automatisch auf WebP konvertiert.</small>
            </div>
            <div class="dk-field">
                <label for="existing_image">…oder bestehenden Bildpfad</label>
                <input type="text" id="existing_image" name="existing_image" value="<?php echo e($post['og_image'] ?? ''); ?>" placeholder="images/blog/datei.webp">
            </div>
        </div>

        <div class="dk-form-actions">
            <button type="submit" class="dk-btn dk-btn-primary dk-btn-block">Speichern</button>
            <?php if (!empty($post['id'])): ?>
                <a href="../blog/<?php echo e($post['slug']); ?>.html" target="_blank" class="dk-btn dk-btn-ghost dk-btn-block">Beitrag ansehen</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- TinyMCE WYSIWYG editor -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
<script>
// Slug helper (mirrors PHP dk_slugify)
function dkSlug(s){
    s = s.replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss');
    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    return s;
}
// Meta description char counter
document.getElementById('meta_description')?.addEventListener('input', function(){
    document.getElementById('meta-count').textContent = this.value.length;
});

// Initialize TinyMCE on the content textarea; graceful fallback if CDN fails.
if (window.tinymce) {
    tinymce.init({
        selector: '#content',
        language: 'de',
        plugins: 'lists link image code table',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | code',
        menubar: false,
        branding: false,
        promotion: false,
        height: 480,
        content_style: 'body { font-family: Inter, -apple-system, sans-serif; font-size: 16px; line-height: 1.7; color: #333; max-width: 720px; padding: 16px; } h2 { color:#000; margin-top:28px; } h3 { color:#1a1a1a; margin-top:24px; } img { max-width:100%; height:auto; border-radius:8px; } blockquote { border-left:4px solid #000; padding-left:16px; font-style:italic; color:#666; }',
        // Whitelist: strip scripts/iframes, keep semantic content.
        valid_elements: 'p[style|class],br,strong/b,em/i,u,s,h2,h3,h4,h5,h6,ul,ol,li[style],a[href|title|target|rel],img[src|alt|width|height|style],figure,figcaption,blockquote,table[style],thead,tbody,tr,td[style],th[style],hr,span[style|class],div[style|class]',
        extended_valid_elements: 'a[href|title|target|rel]',
        relative_urls: false,
        remove_script_host: false,
        link_default_target: '_blank',
        link_rel_list: [{ title: 'nofollow noopener', value: 'nofollow noopener', allow: 'all' }]
    });
} else {
    console.warn('TinyMCE failed to load — content field stays a plain textarea.');
}

// Also init the excerpt as a minimal editor (optional).
if (window.tinymce) {
    tinymce.init({
        selector: '#excerpt',
        language: 'de',
        plugins: 'lists link',
        toolbar: 'bold italic | bullist | link',
        menubar: false,
        branding: false,
        promotion: false,
        height: 140
    });
}
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
