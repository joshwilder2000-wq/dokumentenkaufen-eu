<?php
/**
 * Product page renderer.
 *
 * Takes a product row (associative array from the DB) and produces the complete
 * /product/{slug}.html file on disk, byte-for-byte compatible with the existing
 * hand-built product pages (same <head>, critical CSS, JSON-LD @graph, breadcrumb,
 * image panel, consultation form, and footer).
 *
 * The public site therefore stays 100% static HTML while the database remains
 * the source of truth for editing.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Render a single product to its static HTML file.
 *
 * @param array $product Product DB row (associative).
 * @return string Absolute path of the written file.
 */
function dk_render_product(array $product): string
{
    $siteUrl   = dk_site_url();
    $slug      = $product['slug'];
    $pageUrl   = $siteUrl . '/product/' . rawurlencode($slug) . '.html';
    $title     = (string) $product['title'];
    $desc      = (string) ($product['meta_description'] ?: $product['short_description']);
    $keywords  = (string) $product['meta_keywords'];
    $ogImage   = (string) $product['og_image'];
    $ogImageUrl = $ogImage ? ($siteUrl . '/' . ltrim($ogImage, '/')) : ($siteUrl . '/images/logo-new.png');

    $category      = (string) $product['category'];
    $categoryLabel = dk_categories()[$category] ?? ucfirst(str_replace('-', ' ', $category));
    $categoryUrl   = $siteUrl . '/category/' . $category . '.html';

    $features      = dk_json_list($product['features']);
    $processSteps  = dk_json_list($product['process_steps']);

    // The OG image src in the <img> uses the ../ path (product pages are one level deep).
    $imgSrc = $ogImage ? ('../' . ltrim($ogImage, '/')) : '../images/logo-new.png';

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="' . e($desc) . '">
  <meta name="keywords" content="' . e($keywords) . '">
  <meta name="author" content="Dokuments Hub">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="' . e($title) . ' | Dokuments Hub">
  <meta property="og:description" content="' . e($desc) . '">
  <meta property="og:type" content="website">
  <meta property="og:url" content="' . e($pageUrl) . '">
  <meta property="og:image" content="' . e($ogImageUrl) . '">

  <title>' . e($title) . ' | Dokuments Hub</title>

  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="' . e($title) . ' | Dokuments Hub">
  <meta name="twitter:description" content="' . e($desc) . '">
  <meta name="twitter:image" content="' . e($ogImageUrl) . '">
  <link rel="canonical" href="' . e($pageUrl) . '">

  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="../images/apple-touch-icon.png">
  <link rel="manifest" href="../images/site.webmanifest">
  <meta name="theme-color" content="#000000">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"></noscript>

' . dk_critical_css() . '

  <link rel="stylesheet" href="../css/style.min.css?v=' . dk_asset_version() . '">
  <noscript><style>.nav-container{display:flex!important;flex-direction:column}.menu-toggle{display:none!important}</style></noscript>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "' . e($siteUrl) . '/#organization",
        "name": "Dokuments Hub",
        "url": "' . e($siteUrl) . '/",
        "logo": { "@type": "ImageObject", "url": "' . e($siteUrl) . '/images/logo-new.png" },
        "contactPoint": {
          "@type": "ContactPoint",
          "email": "leitung@akademischergrad.de",
          "contactType": "Beratung",
          "availableLanguage": ["de", "en", "es", "nl", "sv"]
        }
      },
      {
        "@type": "WebSite",
        "@id": "' . e($siteUrl) . '/#website",
        "url": "' . e($siteUrl) . '/",
        "name": "Dokuments Hub",
        "publisher": { "@id": "' . e($siteUrl) . '/#organization" },
        "inLanguage": "de"
      },
      {
        "@type": "WebPage",
        "@id": "' . e($pageUrl) . '#webpage",
        "url": "' . e($pageUrl) . '",
        "name": "' . e($title) . '",
        "description": "' . e($desc) . '",
        "inLanguage": "de",
        "isPartOf": { "@id": "' . e($siteUrl) . '/#website" },
        "about": { "@id": "' . e($pageUrl) . '#product" },
        "breadcrumb": { "@id": "' . e($pageUrl) . '#breadcrumb" }
      },
      {
        "@type": "Product",
        "additionalType": "https://schema.org/Service",
        "@id": "' . e($pageUrl) . '#product",
        "name": "' . e($title) . '",
        "description": "' . e($desc) . '",
        "image": "' . e($ogImageUrl) . '",
        "brand": { "@id": "' . e($siteUrl) . '/#organization" },
        "provider": { "@id": "' . e($siteUrl) . '/#organization" },
        "serviceType": "Rechtmäßige Beratung, Prüfungsvorbereitung, Antragshilfe, Anerkennungsberatung und Agentenvermittlung",
        "category": "' . e($categoryLabel) . '",
        "url": "' . e($pageUrl) . '",
        "areaServed": { "@type": "Country", "name": "Germany" },
        "offers": {
          "@type": "Offer",
          "url": "' . e($pageUrl) . '",
          "price": "0.00",
          "priceCurrency": "EUR",
          "availability": "https://schema.org/InStock",
          "itemCondition": "https://schema.org/NewCondition",
          "seller": { "@id": "' . e($siteUrl) . '/#organization" },
          "description": "Kostenfreie Anfrage zur rechtmäßigen Beratung und Agentenvermittlung."
        }
      },
      {
        "@type": "BreadcrumbList",
        "@id": "' . e($pageUrl) . '#breadcrumb",
        "itemListElement": [
          { "@type": "ListItem", "position": 1, "name": "Startseite", "item": "' . e($siteUrl) . '/" },
          { "@type": "ListItem", "position": 2, "name": "' . e($categoryLabel) . '", "item": "' . e($categoryUrl) . '" },
          { "@type": "ListItem", "position": 3, "name": "' . e($title) . '", "item": "' . e($pageUrl) . '" }
        ]
      }
    ]
  }
  </script>

</head>
<body>
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
      <a href="../category/universitaetsdokumente.html">Studienberatung</a>
      <a href="../category/ihk-zertifikate.html">Prüfungsvorbereitung</a>
      <a href="../category/sprachzertifikate.html">Sprachzertifikate</a>
      <a href="../preise.html">Preise</a>
      <a href="../kontakt.html">Beratung anfragen</a>
    </div>
  </nav>
  <div class="breadcrumb"><a href="../index.html">Startseite</a><span>/</span><span>' . e($title) . '</span></div>

  <main id="content">
    <article class="product-detail">
      <header class="product-header">
        <p class="section-kicker">Legitime Beratungslandingpage</p>
        <h1>' . e($title) . '</h1>
        ' . ($product['short_description'] !== '' ? '<p>' . e($product['short_description']) . '</p>' : '') . '
      </header>
      <section class="product-image-panel" aria-label="Produktbild">
        <div class="product-image-container">
          <img src="' . e($imgSrc) . '" loading="lazy" decoding="async" alt="' . e($title) . '" class="product-main-image" width="600" height="450">
        </div>
      </section>
      <div class="product-content">
        <div class="product-left">
';

    // Process steps section.
    if (!empty($processSteps)) {
        $html .= '          <section class="process-section">
            <h2>So funktioniert die Beratung</h2>
            <ol class="process-steps">
';
        foreach ($processSteps as $step) {
            $html .= '              <li><strong>' . e($step['title'] ?? '') . '</strong>' . e($step['text'] ?? '') . "</li>\n";
        }
        $html .= "            </ol>\n          </section>\n";
    }

    // Description + features section.
    $html .= '          <section class="product-description">
            <h2>Wobei wir helfen</h2>
';
    if ($product['main_description'] !== '') {
        // main_description is treated as trusted HTML authored in the admin (escaped on input via CKEditor-like field).
        $html .= '            ' . $product['main_description'] . "\n";
    }
    if (!empty($features)) {
        $html .= '            <ul class="feature-list">' . "\n";
        foreach ($features as $f) {
            $html .= '              <li>' . e($f) . "</li>\n";
        }
        $html .= "            </ul>\n";
    }
    $html .= "          </section>\n        </div>\n";

    // Sidebar consultation form.
    $html .= dk_consultation_form($title);

    $html .= "      </div>\n    </article>\n  </main>\n";

    $html .= dk_footer();

    $html .= '
  <script src="../js/main.min.js" defer></script>
  <script src="../js/product-forms.min.js" defer></script>
</body>
</html>
';

    $dest = dk_site_root() . '/product/' . $slug . '.html';
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    $written = file_put_contents($dest, $html, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException("Konnte {$dest} nicht schreiben.");
    }
    return $dest;
}

/**
 * Delete the static file for a product (used on delete/unpublish).
 */
function dk_remove_product_file(string $slug): void
{
    $file = dk_site_root() . '/product/' . $slug . '.html';
    if (file_exists($file)) {
        @unlink($file);
    }
}

/* ---------------------------------------------------------------------------
 * Template fragments
 * ------------------------------------------------------------------------- */

/** The consultation form in the sidebar (mirrors the live template). */
function dk_consultation_form(string $title): string
{
    return '        <aside class="product-sidebar">
          <form class="consultation-form inquiry-form" action="../mailer.php" method="POST">
          <input type="hidden" name="form_type" value="inquiry">
          <input type="hidden" name="service_area" value="' . e($title) . '">
          <div class="honeypot" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
            <input type="text" name="company_url" tabindex="-1" autocomplete="off">
          </div>
          <h3>Beratungsanfrage</h3>
          <div class="form-group">
            <label>Name der Studentin / des Studenten <span class="required">*</span></label>
            <input type="text" name="student_name" placeholder="Name der Studentin / des Studenten" required>
          </div>
          <div class="form-group">
            <label>Studiengang, Abschluss oder Zertifikat <span class="required">*</span></label>
            <input type="text" name="program_details_title" value="' . e($title) . '" readonly>
          </div>
          <div class="form-group">
            <label>Details zum Ziel <span class="required">*</span></label>
            <textarea name="program_details" placeholder="Beschreiben Sie Studiengang, Zertifikat, Prüfungsziel, aktuelle Situation, Fristen und vorhandene Unterlagen." required></textarea>
          </div>
          <div class="form-group">
            <label>Mindestens zwei Kontaktkanäle auswählen <span class="required">*</span></label>
            <div class="channel-grid">
              <label class="channel-option"><input type="checkbox" name="communication_channels[]" value="whatsapp"> WhatsApp</label>
              <label class="channel-option"><input type="checkbox" name="communication_channels[]" value="telegram"> Telegram</label>
              <label class="channel-option"><input type="checkbox" name="communication_channels[]" value="email"> E-Mail</label>
            </div>
          </div>
          <div class="channel-fields" data-channel-field="whatsapp" hidden>
            <div class="whatsapp-row">
              <div class="form-group">
                <label>Ländervorwahl</label>
                <input type="text" name="whatsapp_country_code" placeholder="+49" data-required-when-visible="true">
              </div>
              <div class="form-group">
                <label>WhatsApp-Nummer</label>
                <input type="tel" name="whatsapp_number" placeholder="170 0000000" data-required-when-visible="true">
              </div>
            </div>
          </div>
          <div class="form-group" data-channel-field="telegram" hidden>
            <label>Telegram-Benutzername</label>
            <input type="text" name="telegram_username" placeholder="@username" data-required-when-visible="true">
          </div>
          <div class="form-group" data-channel-field="email" hidden>
            <label>E-Mail-Adresse</label>
            <input type="email" name="contact_email" placeholder="name@example.com" data-required-when-visible="true">
          </div>
          <button type="submit" class="submit-btn">Beratungsanfrage senden</button>
          <p class="form-note">Pflichtfelder. Wir nutzen Ihre Angaben ausschließlich zur rechtmäßigen Beratung und Vermittlung.</p>
        </form>
</aside>';
}

/** Footer block shared across product pages. */
function dk_footer(): string
{
    return '  <footer class="footer">
    <div class="footer-content">
      <div class="footer-contact">
        <h3>Beratung anfragen</h3>
        <p>Beschreiben Sie Ihr Studien-, Prüfungs- oder Anerkennungsziel. Wir melden uns über mindestens zwei gewünschte Kontaktkanäle.</p>
        <div class="footer-buttons">
          <a href="../kontakt.html" class="footer-btn">Anfrageformular</a>
          <a href="mailto:leitung@akademischergrad.de" class="footer-btn">E-Mail</a>
          <a href="https://wa.me/+491791530217/" class="footer-btn footer-btn-small" target="_blank" rel="noopener noreferrer">WhatsApp</a>
          <a href="https://t.me/Wissenschaft_VIP" class="footer-btn footer-btn-small" target="_blank" rel="noopener noreferrer">Telegram</a>
        </div>
      </div>
      <div class="footer-links">
        <h3>Wichtige Seiten</h3>
        <a href="../category/universitaetsdokumente.html">Studienberatung</a>
        <a href="../category/ihk-zertifikate.html">IHK-Beratung</a>
        <a href="../category/hwk-meisterbriefe.html">HWK-Beratung</a>
        <a href="../category/sprachzertifikate.html">Sprachzertifikate</a>
        <a href="../category/gewerbeordnung.html">Gewerbeordnung</a>
        <a href="../ueber-uns.html">Über uns</a>
        <a href="../bewertungen.html">Bewertungen</a>
        <a href="../faq.html">FAQ</a>
        <a href="../blog/">Blog</a>
        <a href="../datenschutz.html">Datenschutz</a>
        <a href="../agb.html">AGB</a>
        <a href="../rueckgabe.html">Rückgabe</a>
        <a href="../impressum.html">Impressum</a>
      </div>
      <div class="footer-bottom">
        <p>&copy; ' . date('Y') . ' Dokuments Hub. Rechtmäßige Beratung und Agentenvermittlung.</p>
      </div>
    </div>
  </footer>';
}

/* ---------------------------------------------------------------------------
 * Utilities for the renderer
 * ------------------------------------------------------------------------- */

/** Inline critical CSS (kept in sync with the original product template). */
function dk_critical_css(): string
{
    return file_get_contents(__DIR__ . '/lib/critical.css') ?: '';
}
