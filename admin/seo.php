<?php
/**
 * SEO Portal.
 *
 * Three sections:
 *   1. robots.txt manager (structured form: toggle rules + sitemap URLs)
 *   2. Sitemap overview (all sitemaps, URL counts, rebuild buttons)
 *   3. Schema settings (product + post JSON-LD defaults, re-render buttons)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/blog-renderer.php';
require_once __DIR__ . '/sitemap-builder.php';

$siteUrl = dk_site_url();

// ---------------------------------------------------------------------------
// Define the known robots.txt rules (path => label).
// ---------------------------------------------------------------------------
function dk_robots_rules(): array
{
    return [
        '/admin/'            => 'Admin-Bereich',
        '/private/'          => 'Private Dateien',
        '/tmp/'              => 'Temporäre Dateien',
        '/cgi-bin/'          => 'CGI-Skripte',
        '/api/'              => 'API-Endpunkte',
        '/data/'             => 'Datenbank / CMS-Daten',
        '/danke.html'        => 'Danke-Seite',
        '/404.html'          => '404-Fehlerseite',
        '/blog/TEMPLATE.html'=> 'Blog-Vorlage',
    ];
}

// ---------------------------------------------------------------------------
// Handle form submissions.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $section = (string) ($_POST['section'] ?? '');

    if ($section === 'robots') {
        $enabledRules = $_POST['rules'] ?? [];
        $crawlDelay   = (int) ($_POST['crawl_delay'] ?? 0);
        $sitemapsRaw  = (string) ($_POST['sitemaps'] ?? '');

        // Build sitemap URLs.
        $sitemapLines = [];
        foreach (preg_split('/\r?\n/', trim($sitemapsRaw)) as $line) {
            $line = trim($line);
            if ($line !== '') {
                // If it's a bare filename, prefix with the site URL.
                if (!preg_match('#^https?://#i', $line)) {
                    $line = $siteUrl . '/' . ltrim($line, '/');
                }
                $sitemapLines[] = $line;
            }
        }

        // Assemble robots.txt.
        $txt  = "User-agent: *\n";
        $txt .= "Allow: /\n";
        $txt .= "Allow: /product/\n";
        $txt .= "Allow: /category/\n";
        $txt .= "Allow: /blog/\n";
        $txt .= "Allow: /images/\n";
        $txt .= "Allow: /css/\n";
        $txt .= "Allow: /js/\n";
        $txt .= "\n";
        foreach (dk_robots_rules() as $path => $label) {
            if (in_array($path, $enabledRules, true)) {
                $txt .= "Disallow: {$path}\n";
            }
        }
        if ($crawlDelay > 0) {
            $txt .= "Crawl-delay: {$crawlDelay}\n";
        }
        $txt .= "\n";
        foreach ($sitemapLines as $sm) {
            $txt .= "Sitemap: {$sm}\n";
        }

        file_put_contents(dk_site_root() . '/robots.txt', $txt, LOCK_EX);
        dk_flash('success', 'robots.txt aktualisiert.');

    } elseif ($section === 'schema') {
        dk_set_setting('schema_product_service_type', dk_clean((string)($_POST['product_service_type'] ?? '')));
        dk_set_setting('schema_product_area_served', dk_clean((string)($_POST['product_area_served'] ?? '')));
        dk_set_setting('schema_product_price', dk_clean((string)($_POST['product_price'] ?? '0.00')));
        dk_set_setting('schema_post_author', dk_clean((string)($_POST['post_author'] ?? 'Dokuments Hub')));
        dk_flash('success', 'Schema-Einstellungen gespeichert. Klicke „Alle Seiten neu rendern”, um die Änderungen auf die HTML-Dateien anzuwenden.');

    } elseif ($section === 'rebuild_sitemap') {
        $which = (string) ($_POST['which'] ?? 'all');
        if ($which === 'products') {
            $n = dk_rebuild_product_sitemap();
            dk_flash('success', "sitemap-products.xml neu erstellt ({$n} Produkte).");
        } elseif ($which === 'blog') {
            $n = dk_rebuild_blog_sitemap();
            dk_flash('success', "sitemap-blog.xml neu erstellt ({$n} Beiträge).");
        } else {
            $n = dk_rebuild_all_sitemaps();
            dk_flash('success', "Alle Sitemaps neu erstellt ({$n} Produkte im Produkt-Sitemap).");
        }

    } elseif ($section === 'rerender') {
        $which = (string) ($_POST['which'] ?? '');
        $count = 0;
        if ($which === 'products') {
            $rows = dk_db()->query("SELECT * FROM products WHERE is_published = 1")->fetchAll();
            foreach ($rows as $r) {
                dk_render_product($r);
                $count++;
            }
            dk_flash('success', "{$count} Produkseiten neu gerendert.");
        } elseif ($which === 'posts') {
            $rows = dk_db()->query("SELECT * FROM posts WHERE is_published = 1")->fetchAll();
            foreach ($rows as $r) {
                dk_render_blog_post($r);
                $count++;
            }
            dk_render_blog_index();
            dk_flash('success', "{$count} Blogbeiträge + Blog-Index neu gerendert.");
        }
    }

    header('Location: seo.php');
    exit;
}

// ---------------------------------------------------------------------------
// Read current state for the form.
// ---------------------------------------------------------------------------
$robotsPath = dk_site_root() . '/robots.txt';
$robotsContent = file_exists($robotsPath) ? file_get_contents($robotsPath) : '';
$currentRules = [];
foreach (dk_robots_rules() as $path => $label) {
    if (preg_match('#Disallow:\s*' . preg_quote($path, '#') . '#i', (string)$robotsContent)) {
        $currentRules[$path] = true;
    }
}
$currentDelay = 0;
if (preg_match('/Crawl-delay:\s*(\d+)/i', (string)$robotsContent, $m)) {
    $currentDelay = (int) $m[1];
}
$currentSitemaps = [];
if (preg_match_all('/Sitemap:\s*(.+)/i', (string)$robotsContent, $m)) {
    $currentSitemaps = array_map('trim', $m[1]);
}

// Sitemap overview data.
$sitemaps = [
    ['file' => 'sitemap.xml',          'label' => 'Master-Sitemap',         'rebuildable' => false],
    ['file' => 'sitemap-products.xml', 'label' => 'Produkt-Sitemap',        'rebuildable' => true,  'which' => 'products'],
    ['file' => 'sitemap-blog.xml',     'label' => 'Blog-Sitemap',           'rebuildable' => true,  'which' => 'blog'],
    ['file' => 'sitemap-static.xml',   'label' => 'Statische Seiten',       'rebuildable' => false],
    ['file' => 'sitemap-categories.xml','label'=> 'Kategorie-Sitemap',      'rebuildable' => false],
];

$pageTitle = 'SEO-Portal';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head"><h1>SEO-Portal</h1></div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<!-- ===================== robots.txt ===================== -->
<form method="post" class="dk-card">
    <?php echo dk_csrf_field(); ?>
    <input type="hidden" name="section" value="robots">
    <h3>robots.txt</h3>
    <p class="dk-muted">Steuert, was Suchmaschinen crawlen dürfen.</p>

    <div class="dk-check-grid">
        <?php foreach (dk_robots_rules() as $path => $label): ?>
            <label class="dk-check-item">
                <input type="checkbox" name="rules[]" value="<?php echo e($path); ?>" <?php echo !empty($currentRules[$path]) ? 'checked' : ''; ?>>
                <span><code><?php echo e($path); ?></code><br><small class="dk-muted"><?php echo e($label); ?></small></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="dk-field" style="max-width:200px;margin-top:16px">
        <label for="crawl_delay">Crawl-delay (Sekunden, 0 = aus)</label>
        <input type="number" id="crawl_delay" name="crawl_delay" value="<?php echo (int)$currentDelay; ?>" min="0" max="30">
    </div>

    <div class="dk-field">
        <label for="sitemaps">Sitemap-URLs (eine pro Zeile)</label>
        <textarea id="sitemaps" name="sitemaps" rows="6"><?php
            $list = $currentSitemaps ?: [
                $siteUrl . '/sitemap.xml',
                $siteUrl . '/sitemap-products.xml',
                $siteUrl . '/sitemap-blog.xml',
                $siteUrl . '/sitemap-static.xml',
                $siteUrl . '/sitemap-categories.xml',
            ];
            echo e(implode("\n", $list));
        ?></textarea>
        <small class="dk-muted">Vollständige URLs oder nur Dateinamen (z.B. <code>sitemap.xml</code>) — wird automatisch mit der Site-URL ergänzt.</small>
    </div>

    <button type="submit" class="dk-btn dk-btn-primary">robots.txt speichern</button>
</form>

<!-- ===================== Sitemap overview ===================== -->
<div class="dk-card">
    <h3>Sitemaps</h3>
    <div class="dk-table-wrap">
    <table class="dk-table dk-table-compact">
        <thead>
            <tr>
                <th>Sitemap</th>
                <th class="col-date">URLs</th>
                <th class="col-date">Zuletzt aktualisiert</th>
                <th class="col-actions">Aktion</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sitemaps as $sm):
            $fullPath = dk_site_root() . '/' . $sm['file'];
            $exists = file_exists($fullPath);
            $urlCount = 0;
            $mtime = '—';
            if ($exists) {
                $urlCount = preg_match_all('#<loc>#', (string)file_get_contents($fullPath));
                $mtime = date('d.m.Y H:i', filemtime($fullPath));
            }
        ?>
            <tr>
                <td class="col-title">
                    <a href="<?php echo e($siteUrl . '/' . $sm['file']); ?>" target="_blank" class="dk-row-title"><?php echo e($sm['label']); ?></a>
                    <code class="dk-row-slug"><?php echo e($sm['file']); ?></code>
                </td>
                <td class="col-date"><?php echo $exists ? (int)$urlCount : '—'; ?></td>
                <td class="col-date dk-muted"><?php echo e($mtime); ?></td>
                <td class="col-actions">
                    <?php if ($sm['rebuildable']): ?>
                        <form method="post" style="display:inline">
                            <?php echo dk_csrf_field(); ?>
                            <input type="hidden" name="section" value="rebuild_sitemap">
                            <input type="hidden" name="which" value="<?php echo e($sm['which']); ?>">
                            <button type="submit" class="dk-btn dk-btn-sm">Neu erstellen</button>
                        </form>
                    <?php else: ?>
                        <span class="dk-muted">statisch</span>
                    <?php endif; ?>
                    <a href="<?php echo e($siteUrl . '/' . $sm['file']); ?>" target="_blank" class="dk-icon-btn" title="Ansehen">↗</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <form method="post" style="margin-top:12px">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="section" value="rebuild_sitemap">
        <input type="hidden" name="which" value="all">
        <button type="submit" class="dk-btn dk-btn-ghost">⟳ Alle Sitemaps neu erstellen</button>
    </form>
</div>

<!-- ===================== Schema settings ===================== -->
<form method="post" class="dk-card">
    <?php echo dk_csrf_field(); ?>
    <input type="hidden" name="section" value="schema">
    <h3>Schema-Einstellungen (JSON-LD)</h3>
    <p class="dk-muted">Globale Standardwerte für strukturierte Daten. Nach dem Speichern „Alle Seiten neu rendern” klicken, um die HTML-Dateien zu aktualisieren.</p>

    <div class="dk-cards">
        <div>
            <h4>Produkt-Schema</h4>
            <div class="dk-field">
                <label for="product_service_type">Service-Typ</label>
                <input type="text" id="product_service_type" name="product_service_type"
                       value="<?php echo e(dk_setting('schema_product_service_type', 'Rechtmäßige Beratung, Prüfungsvorbereitung, Antragshilfe, Anerkennungsberatung und Agentenvermittlung')); ?>">
            </div>
            <div class="dk-field">
                <label for="product_area_served">Gebiet (areaServed)</label>
                <input type="text" id="product_area_served" name="product_area_served"
                       value="<?php echo e(dk_setting('schema_product_area_served', 'Germany')); ?>">
            </div>
            <div class="dk-field">
                <label for="product_price">Angebotspreis (EUR)</label>
                <input type="text" id="product_price" name="product_price"
                       value="<?php echo e(dk_setting('schema_product_price', '0.00')); ?>">
            </div>
        </div>
        <div>
            <h4>Beitrag-Schema</h4>
            <div class="dk-field">
                <label for="post_author">Standard-Autor</label>
                <input type="text" id="post_author" name="post_author"
                       value="<?php echo e(dk_setting('schema_post_author', 'Dokuments Hub')); ?>">
            </div>
            <p class="dk-muted" style="margin-top:8px">
                Beiträge verwenden den <code>BlogPosting</code>-Typ mit headline, datePublished,
                author, publisher und BreadcrumbList.
            </p>
        </div>
    </div>

    <button type="submit" class="dk-btn dk-btn-primary">Schema-Einstellungen speichern</button>
</form>

<!-- ===================== Re-render all ===================== -->
<div class="dk-card">
    <h3>Alle Seiten neu rendern</h3>
    <p class="dk-muted">Wendet die aktuellen Schema-Einstellungen + Site-URL auf alle bereits erstellten HTML-Dateien an.</p>
    <div class="dk-page-actions">
        <form method="post" style="display:inline">
            <?php echo dk_csrf_field(); ?>
            <input type="hidden" name="section" value="rerender">
            <input type="hidden" name="which" value="products">
            <button type="submit" class="dk-btn dk-btn-ghost">Produkte neu rendern</button>
        </form>
        <form method="post" style="display:inline">
            <?php echo dk_csrf_field(); ?>
            <input type="hidden" name="section" value="rerender">
            <input type="hidden" name="which" value="posts">
            <button type="submit" class="dk-btn dk-btn-ghost">Blog neu rendern</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
