<?php
/**
 * Tag page renderer + tag sitemap builder.
 *
 * Renders /tag/<slug>.html pages (listing all products with that tag)
 * and builds sitemap-tags.xml.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Render a tag page to /tag/<slug>.html.
 */
function dk_render_tag_page(array $tag): string
{
    $siteUrl   = dk_site_url();
    $slug      = $tag['slug'];
    $tagName   = $tag['name'];
    $pageUrl   = $siteUrl . '/tag/' . rawurlencode($slug) . '.html';
    $desc      = $tag['description'] ?: 'Produkte mit dem Tag "' . $tagName . '" bei Dokuments Hub.';
    $css       = file_get_contents(__DIR__ . '/lib/critical-blog.css') ?: '';

    // Fetch products linked to this tag.
    $stmt = dk_db()->prepare(
        "SELECT p.* FROM products p
         INNER JOIN product_tags pt ON pt.product_id = p.id
         WHERE pt.tag_id = ? AND p.is_published = 1
         ORDER BY p.title ASC"
    );
    $stmt->execute([$tag['id']]);
    $products = $stmt->fetchAll();

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="' . e($desc) . '">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="' . e($tagName) . ' | Dokuments Hub">
  <meta property="og:description" content="' . e($desc) . '">
  <meta property="og:type" content="website">
  <meta property="og:url" content="' . e($pageUrl) . '">
  <meta property="og:image" content="' . e($siteUrl) . '/images/logo-new.png">

  <title>' . e($tagName) . ' | Dokuments Hub</title>
  <link rel="canonical" href="' . e($pageUrl) . '">

  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="../images/apple-touch-icon.png">
  <meta name="theme-color" content="#000000">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"></noscript>

  <style>
' . $css . '
  </style>
  <link rel="stylesheet" href="../css/style.min.css?v=' . dk_asset_version() . '">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      { "@type": "Organization", "@id": "' . e($siteUrl) . '/#organization", "name": "Dokuments Hub", "url": "' . e($siteUrl) . '/", "logo": { "@type": "ImageObject", "url": "' . e($siteUrl) . '/images/logo-new.png" } },
      { "@type": "WebSite", "@id": "' . e($siteUrl) . '/#website", "url": "' . e($siteUrl) . '/", "name": "Dokuments Hub", "publisher": { "@id": "' . e($siteUrl) . '/#organization" }, "inLanguage": "de" },
      {
        "@type": "CollectionPage",
        "@id": "' . e($pageUrl) . '#collectionpage",
        "url": "' . e($pageUrl) . '",
        "name": "' . e($tagName) . '",
        "description": "' . e($desc) . '",
        "inLanguage": "de",
        "isPartOf": { "@id": "' . e($siteUrl) . '/#website" },
        "about": {
          "@type": "ItemList",
          "@id": "' . e($pageUrl) . '#itemlist",
          "name": "' . e($tagName) . '",
          "numberOfItems": ' . count($products) . ',
          "itemListElement": [';

    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $pUrl = $siteUrl . '/product/' . rawurlencode($p['slug']) . '.html';
        $items[] = "\n            { \"@type\": \"ListItem\", \"position\": $pos, \"name\": \"" . e($p['title']) . "\", \"url\": \"" . e($pUrl) . "\" }";
        $pos++;
    }
    $html .= implode(',', $items) . "\n          ]\n        }\n      }\n    ]\n  }\n  </script>\n</head>\n<body>";

    $html .= '
  <a href="#content" class="skip-link">Zum Inhalt springen</a>
  <header class="header">
    <div class="header-content">
      <a href="../index.html" class="logo" aria-label="Dokuments Hub Startseite">
        <img src="../images/logo-new.png" width="240" height="80" decoding="async" alt="Dokuments Hub Logo">
      </a>
      <p>Rechtmäßige Studienberatung, Prüfungsvorbereitung, Anerkennungshilfe und Agentenvermittlung</p>
      <button class="menu-toggle" aria-label="Menü öffnen" aria-expanded="false">☰</button>
    </div>
  </header>
  <nav class="nav" aria-label="Hauptnavigation">
    <div class="nav-container">
      <a href="../index.html">Startseite</a>
      <a href="../bewertungen.html">Bewertungen</a>
    </div>
  </nav>
  <div class="breadcrumb"><a href="../index.html">Startseite</a><span>/</span>Tag: ' . e($tagName) . '</div>

  <main id="content">
    <article class="section-card">
      <p class="section-kicker">Produkt-Tag</p>
      <h1>' . e($tagName) . '</h1>
      <div class="content-card">
        <p>' . e($desc) . ' — ' . count($products) . ' Produkt(e) gefunden.</p>
      </div>
      <section class="category-section product-directory">
        <h2>Produkte</h2>
        <div class="directory-grid">';

    foreach ($products as $p) {
        $html .= "\n          " . '<a class="directory-card" href="../product/' . e($p['slug']) . '.html">' . e($p['title']) . '</a>';
    }

    $html .= "\n        </div>\n      </section>\n    </article>\n";

    // Consultation form sidebar.
    $html .= '    <aside class="product-sidebar dk-form-sidebar" style="margin:40px auto;max-width:900px">
      <div class="dk-form-card" style="max-width:680px;margin:0 auto">
        <h3>Beratungsanfrage</h3>
        <p class="dk-form-intro">Interessiert an einem dieser Produkte? Senden Sie uns Ihre Anfrage.</p>
        <form class="dk-form" action="../mailer.php" method="POST">
          <input type="hidden" name="form_type" value="inquiry">
          <input type="hidden" name="service_area" value="Tag: ' . e($tagName) . '">
          <div class="honeypot" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
            <input type="text" name="company_url" tabindex="-1" autocomplete="off">
          </div>
          <div class="dk-form-group">
            <label>Ihr Name <span class="required">*</span></label>
            <input type="text" name="student_name" placeholder="Vor- und Nachname" required>
          </div>
          <div class="dk-form-group">
            <label>Details zu Ihrem Anliegen <span class="required">*</span></label>
            <textarea name="program_details" rows="3" placeholder="Welches Produkt interessiert Sie? Beschreiben Sie Ihr Ziel." required></textarea>
          </div>
          <div class="dk-form-group">
            <label>Kontaktkanäle <span class="required">*</span> <small>(mindestens zwei)</small></label>
            <div class="dk-channel-grid">
              <label class="dk-channel"><input type="checkbox" name="communication_channels[]" value="whatsapp"> <span>💬 WhatsApp</span></label>
              <label class="dk-channel"><input type="checkbox" name="communication_channels[]" value="telegram"> <span>✈️ Telegram</span></label>
              <label class="dk-channel"><input type="checkbox" name="communication_channels[]" value="email"> <span>📧 E-Mail</span></label>
            </div>
          </div>
          <div class="dk-channel-fields" data-channel-field="whatsapp" hidden>
            <div class="dk-wa-row">
              <input type="text" name="whatsapp_country_code" placeholder="+49" data-required-when-visible="true" style="width:70px;flex-shrink:0;text-align:center">
              <input type="tel" name="whatsapp_number" placeholder="170 1234567" data-required-when-visible="true" style="flex:1">
            </div>
          </div>
          <div class="dk-form-group" data-channel-field="telegram" hidden>
            <input type="text" name="telegram_username" placeholder="@username" data-required-when-visible="true">
          </div>
          <div class="dk-form-group" data-channel-field="email" hidden>
            <input type="email" name="contact_email" placeholder="name@example.com" data-required-when-visible="true">
          </div>
          <button type="submit" class="dk-form-submit">Anfrage senden →</button>
        </form>
      </div>
    </aside>
  </main>
';

    // Footer.
    $html .= dk_tag_footer();

    $html .= '
  <script src="../js/main.min.js" defer></script>
  <script src="../js/chat-widget.js" defer></script>
  <script src="../js/session-timer.js" defer></script>
</body>
</html>
';

    $dir = dk_site_root() . '/tag';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $dest = $dir . '/' . $slug . '.html';
    file_put_contents($dest, $html, LOCK_EX);
    return $dest;
}

/** Shared footer for tag pages. */
function dk_tag_footer(): string
{
    return '  <footer class="footer">
    <div class="footer-content">
      <div class="footer-contact">
        <h3>Beratung anfragen</h3>
        <p>Beschreiben Sie Ihr Studien-, Prüfungs- oder Anerkennungsziel.</p>
        <div class="footer-buttons">
          <a href="../kontakt.html" class="footer-btn">Anfrageformular</a>
          <a href="mailto:leitung@akademischergrad.de" class="footer-btn">E-Mail</a>
          <a href="https://wa.me/+491791530217/" class="footer-btn footer-btn-small" target="_blank" rel="noopener noreferrer">WhatsApp</a>
          <a href="https://t.me/mikibucherbox" class="footer-btn footer-btn-small" target="_blank" rel="noopener noreferrer">Telegram</a>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; ' . date('Y') . ' Dokuments Hub. Rechtmäßige Beratung und Agentenvermittlung.</p>
      </div>
    </div>
  </footer>';
}

/**
 * Build sitemap-tags.xml from all tags.
 *
 * @return int Number of tag URLs.
 */
function dk_rebuild_tags_sitemap(): int
{
    $siteUrl = dk_site_url();
    $today   = date('Y-m-d');

    $tags = dk_db()->query('SELECT slug FROM tags ORDER BY name ASC')->fetchAll();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($tags as $t) {
        $loc = $siteUrl . '/tag/' . rawurlencode($t['slug']) . '.html';
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . $today . "</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.6</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    file_put_contents(dk_site_root() . '/sitemap-tags.xml', $xml, LOCK_EX);
    return count($tags);
}
