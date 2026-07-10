<?php
/**
 * Blog post + blog index renderer.
 *
 * Mirrors admin/renderer.php but for blog posts:
 *   dk_render_blog_post($post)   -> writes /blog/{slug}.html (with Article JSON-LD)
 *   dk_remove_blog_file($slug)   -> deletes a post's static file
 *   dk_render_blog_index()       -> regenerates /blog/index.html from all published posts
 *
 * The public blog pages stay static HTML; the DB is the source of truth.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

/**
 * Render a single blog post to its static HTML file.
 *
 * @param array $post Post DB row (associative).
 * @return string Absolute path of the written file.
 */
function dk_render_blog_post(array $post): string
{
    $siteUrl = dk_site_url();
    $slug    = $post['slug'];
    $pageUrl = $siteUrl . '/blog/' . rawurlencode($slug) . '.html';
    $title   = (string) $post['title'];
    $desc    = (string) ($post['meta_description'] ?: $post['excerpt']);
    $keywords = (string) $post['meta_keywords'];

    $ogImage    = (string) $post['og_image'];
    $ogImageUrl = $ogImage ? ($siteUrl . '/' . ltrim($ogImage, '/')) : ($siteUrl . '/images/logo-new.png');
    $imgSrc     = $ogImage ? ('../' . ltrim($ogImage, '/')) : '../images/logo-new.png';

    $category      = (string) $post['category'];
    $categoryLabel = dk_post_categories()[$category] ?? ucfirst(str_replace('-', ' ', $category));
    $categoryUrl   = $siteUrl . '/blog/';

    $author     = (string) ($post['author'] ?: 'Dokuments Hub');
    $pubDate    = (string) $post['published_at'];
    $dateMeta   = $pubDate ? date('d.m.Y', strtotime($pubDate)) : '';
    $isoDate    = $pubDate ? date('c', strtotime($pubDate)) : date('c');

    $excerpt = (string) $post['excerpt'];
    $content = (string) $post['content']; // trusted HTML, sanitized on save

    $css = file_get_contents(__DIR__ . '/lib/critical-blog.css') ?: '';

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="' . e($desc) . '">
  <meta name="keywords" content="' . e($keywords) . '">
  <meta name="author" content="' . e($author) . '">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="' . e($title) . ' | Dokuments Hub">
  <meta property="og:description" content="' . e($desc) . '">
  <meta property="og:type" content="article">
  <meta property="og:url" content="' . e($pageUrl) . '">
  <meta property="og:image" content="' . e($ogImageUrl) . '">
  <meta property="article:author" content="' . e($author) . '">
  <meta property="article:published_time" content="' . e($isoDate) . '">

  <title>' . e($title) . ' | Dokuments Hub</title>

  <meta name="twitter:card" content="summary_large_image">
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

  <style>
' . $css . '
  </style>

  <link rel="stylesheet" href="../css/style.min.css?v=' . dk_asset_version() . '">

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
        "breadcrumb": { "@id": "' . e($pageUrl) . '#breadcrumb" }
      },
      {
        "@type": "BlogPosting",
        "@id": "' . e($pageUrl) . '#article",
        "headline": "' . e($title) . '",
        "description": "' . e($desc) . '",
        "image": "' . e($ogImageUrl) . '",
        "datePublished": "' . e($isoDate) . '",
        "dateModified": "' . e($isoDate) . '",
        "author": { "@type": "Organization", "name": "' . e($author) . '", "@id": "' . e($siteUrl) . '/#organization" },
        "publisher": { "@id": "' . e($siteUrl) . '/#organization" },
        "mainEntityOfPage": { "@type": "WebPage", "@id": "' . e($pageUrl) . '" },
        "inLanguage": "de",
        "articleSection": "' . e($categoryLabel) . '",
        "wordCount": ' . str_word_count(strip_tags($content)) . ',
        "keywords": "' . e($keywords ?: $categoryLabel) . '"
      },
      {
        "@type": "BreadcrumbList",
        "@id": "' . e($pageUrl) . '#breadcrumb",
        "itemListElement": [
          { "@type": "ListItem", "position": 1, "name": "Startseite", "item": "' . e($siteUrl) . '/" },
          { "@type": "ListItem", "position": 2, "name": "Blog", "item": "' . e($categoryUrl) . '" },
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
      <a href="../bewertungen.html">Bewertungen</a>
    </div>
  </nav>

  <div class="breadcrumb"><a href="../index.html">Startseite</a><span>/</span><a href="index.html">Blog</a><span>/</span>' . e($title) . '</div>

  <main id="content">
    <article class="section-card">
      <p class="section-kicker">' . e($categoryLabel) . '</p>
      <h1>' . e($title) . '</h1>
';

    if ($excerpt !== '') {
        $html .= '      <div class="content-card">
        ' . $excerpt . '
      </div>
';
    }

    if ($content !== '') {
        $html .= '      <div class="article-body">
        ' . $content . '
      </div>
';
    }

    $html .= '    </article>
  </main>
';

    $html .= dk_blog_footer();

    $html .= '
  <script src="../js/main.min.js" defer></script>
  <script src="../js/chat-widget.js" defer></script>
  <script src="../js/session-timer.js" defer></script>
</body>
</html>
';

    $dest = dk_site_root() . '/blog/' . $slug . '.html';
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
 * Delete the static file for a blog post (used on delete/unpublish).
 */
function dk_remove_blog_file(string $slug): void
{
    $file = dk_site_root() . '/blog/' . $slug . '.html';
    if (file_exists($file)) {
        @unlink($file);
    }
}

/**
 * Regenerate the blog index page (/blog/index.html) from all published posts.
 */
function dk_render_blog_index(): void
{
    $siteUrl = dk_site_url();
    $blogUrl = $siteUrl . '/blog/';
    $css     = file_get_contents(__DIR__ . '/lib/critical-blog.css') ?: '';

    $stmt = dk_db()->query(
        "SELECT * FROM posts WHERE is_published = 1 ORDER BY published_at DESC, title ASC"
    );
    $posts = $stmt->fetchAll();

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Dokuments Hub Blog – Ratgeber zu Studienberatung, Prüfungsvorbereitung, Anerkennung und rechtmäßigen Wegen in Deutschland.">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="Dokuments Hub Blog">
  <meta property="og:description" content="Ratgeber zu Studienberatung, Prüfungsvorbereitung und Anerkennung.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="' . e($blogUrl) . '">
  <meta property="og:image" content="' . e($siteUrl) . '/images/logo-new.png">

  <title>Dokuments Hub Blog | Dokuments Hub</title>

  <meta name="twitter:card" content="summary">
  <link rel="canonical" href="' . e($blogUrl) . '">

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
      { "@type": "CollectionPage", "@id": "' . e($blogUrl) . '#webpage", "url": "' . e($blogUrl) . '", "name": "Dokuments Hub Blog", "inLanguage": "de", "isPartOf": { "@id": "' . e($siteUrl) . '/#website" } }
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
      <a href="../bewertungen.html">Bewertungen</a>
    </div>
  </nav>

  <div class="breadcrumb"><a href="../index.html">Startseite</a><span>/</span>Blog</div>

  <main id="content">
    <article class="section-card">
      <p class="section-kicker">Ratgeber & Wissen</p>
      <h1>Dokuments Hub Blog</h1>
      <div class="content-card">
        <p>Ratgeber und Einordnungen zu Studienberatung, Prüfungsvorbereitung, Anerkennung und rechtmäßigen Wegen in Deutschland.</p>
      </div>
    </article>
';

    if ($posts) {
        $html .= '    <div class="blog-grid">' . "\n";
        foreach ($posts as $p) {
            $pUrl   = $p['slug'] . '.html';
            $pTitle = e($p['title']);
            $pExcer = e(mb_substr(strip_tags((string)$p['excerpt'] ?: (string)$p['title']), 0, 140));
            $pImg   = $p['og_image'] ? ('../' . ltrim($p['og_image'], '/')) : '';
            $pDate  = $p['published_at'] ? date('d.m.Y', strtotime($p['published_at'])) : '';
            $pCat   = dk_post_categories()[$p['category']] ?? '';

            $html .= '      <a class="blog-card" href="' . $pUrl . '">';
            if ($pImg) {
                $html .= '<img src="' . e($pImg) . '" alt="' . $pTitle . '" loading="lazy">';
            }
            $html .= '<div class="blog-card-body">';
            $html .= '<h3>' . $pTitle . '</h3>';
            $html .= '<p>' . $pExcer . '</p>';
            $html .= '<div class="blog-card-meta">' . e($pDate ? $pDate . ' · ' . $pCat : $pCat) . '</div>';
            $html .= '</div></a>' . "\n";
        }
        $html .= "    </div>\n";
    } else {
        $html .= '    <div class="section-card"><p class="dk-muted">Aktuell sind keine Beiträge vorhanden.</p></div>' . "\n";
    }

    $html .= '  </main>
';

    $html .= dk_blog_footer();
    $html .= '
  <script src="../js/main.min.js" defer></script>
  <script src="../js/chat-widget.js" defer></script>
  <script src="../js/session-timer.js" defer></script>
</body>
</html>
';

    $dest = dk_site_root() . '/blog/index.html';
    $written = file_put_contents($dest, $html, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException("Konnte blog/index.html nicht schreiben.");
    }
}

/** Shared footer for blog pages. */
function dk_blog_footer(): string
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
          <a href="https://t.me/mikibucherbox" class="footer-btn footer-btn-small" target="_blank" rel="noopener noreferrer">Telegram</a>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; ' . date('Y') . ' Dokuments Hub. Rechtmäßige Beratung und Agentenvermittlung.</p>
      </div>
    </div>
  </footer>';
}
