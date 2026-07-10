<?php
/**
 * Bewertungen (Reviews) page — dynamic PHP page showing real reviews + submission form.
 * Saved as bewertungen.html but runs as PHP.
 */

require_once __DIR__ . '/admin/lib/helpers.php';

$siteUrl = dk_site_url();
$css = file_get_contents(__DIR__ . '/admin/lib/critical-blog.css') ?: '';

$reviews = dk_db()->query(
    "SELECT r.*, p.title AS product_title
     FROM reviews r
     LEFT JOIN products p ON r.product_id = p.id
     WHERE r.status = 'approved'
     ORDER BY r.review_date DESC, r.id DESC"
)->fetchAll();

$agg = dk_db()->query(
    "SELECT COUNT(*) AS n, AVG(rating) AS avg FROM reviews WHERE status = 'approved'"
)->fetch();
$avgRating = $agg['n'] > 0 ? round((float)$agg['avg'], 1) : 0;
$totalReviews = (int)$agg['n'];
$products = dk_db()->query('SELECT id, title FROM products WHERE is_published = 1 ORDER BY title ASC')->fetchAll();
$formStatus = $_GET['review'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Echte Bewertungen und Erfahrungen von Kunden der Dokuments Hub Beratung.">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="Bewertungen | Dokuments Hub">
  <meta property="og:description" content="Echte Kundenbewertungen und Erfahrungen.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo e($siteUrl); ?>/bewertungen.html">
  <meta property="og:image" content="<?php echo e($siteUrl); ?>/images/logo-new.png">
  <title>Bewertungen | Dokuments Hub</title>
  <link rel="canonical" href="<?php echo e($siteUrl); ?>/bewertungen.html">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <meta name="theme-color" content="#000000">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <style>
<?php echo $css; ?>
.dk-reviews-page{max-width:1000px;margin:0 auto;padding:0 20px}
.dk-reviews-hero{text-align:center;padding:48px 20px 32px}
.dk-reviews-hero h1{font-size:2.2rem;font-weight:700;color:#000;margin-bottom:8px}
.dk-reviews-hero p{font-size:1.05rem;color:#666;max-width:600px;margin:0 auto 24px}
.dk-reviews-summary{display:inline-flex;align-items:center;gap:16px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px 32px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.dk-reviews-avg{font-size:2.5rem;font-weight:800;color:#000;line-height:1}
.dk-reviews-stars-big{color:#f59e0b;font-size:1.4rem;letter-spacing:2px}
.dk-reviews-count{font-size:.85rem;color:#888}
.dk-rv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin:40px 0}
.dk-rv-card{background:#fff;border:1px solid #e8e8e8;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .2s}
.dk-rv-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.dk-rv-card-top{padding:18px 20px 12px;display:flex;gap:14px;align-items:flex-start}
.dk-rv-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;flex-shrink:0}
.dk-rv-author{font-weight:600;font-size:.95rem;color:#000}
.dk-rv-date{font-size:.75rem;color:#aaa;margin-top:2px}
.dk-rv-stars{color:#f59e0b;font-size:.9rem;letter-spacing:1px;margin:6px 0}
.dk-rv-title{font-size:.92rem;font-weight:600;color:#333;margin-bottom:6px}
.dk-rv-body{font-size:.85rem;color:#666;line-height:1.6}
.dk-rv-product{font-size:.72rem;color:#999;padding:8px 20px;border-top:1px solid #f5f5f5;background:#fafafa}
.dk-rv-product a{color:#666;text-decoration:none;font-weight:500}
.dk-rv-product a:hover{color:#000}
.dk-rv-img{width:100%;max-height:200px;object-fit:cover;border-top:1px solid #f0f0f0}
.dk-rv-form-section{margin:48px 0;background:#fff;border:1px solid #e0e0e0;border-radius:16px;padding:36px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.dk-rv-form-section h2{font-size:1.5rem;font-weight:700;color:#000;margin-bottom:6px}
.dk-rv-form-intro{font-size:.92rem;color:#666;margin-bottom:24px}
.dk-rv-form{display:flex;flex-direction:column;gap:16px}
.dk-rv-form-row{display:flex;gap:14px}
.dk-rv-form-row>div{flex:1}
.dk-rv-form-field label{display:block;font-weight:600;font-size:.85rem;color:#333;margin-bottom:6px}
.dk-rv-form-field label .req{color:#b91c1c}
.dk-rv-form-field input,.dk-rv-form-field select,.dk-rv-form-field textarea{width:100%;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:8px;font:inherit;font-size:.95rem;background:#fafafa;box-sizing:border-box}
.dk-rv-form-field input:focus,.dk-rv-form-field select:focus,.dk-rv-form-field textarea:focus{outline:none;border-color:#000;background:#fff}
.dk-rv-star-input{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px}
.dk-rv-star-input input{display:none}
.dk-rv-star-input label{font-size:2rem;color:#d1d5db;cursor:pointer;line-height:1}
.dk-rv-star-input label:hover,.dk-rv-star-input label:hover~label,.dk-rv-star-input input:checked~label{color:#f59e0b}
.dk-rv-submit{padding:15px;background:linear-gradient(135deg,#1a1a1a,#000);color:#fff;border:none;border-radius:10px;font-size:1.05rem;font-weight:600;cursor:pointer;font:inherit}
.dk-rv-submit:hover{opacity:.88}
.dk-rv-success{background:#dcfce7;color:#15803d;padding:16px;border-radius:10px;margin-bottom:20px;border:1px solid #bbf7d0}
.dk-rv-err{background:#fee2e2;color:#b91c1c;padding:16px;border-radius:10px;margin-bottom:20px;border:1px solid #fecaca}
.honeypot{position:absolute;left:-9999px;top:-9999px}
@media(max-width:600px){.dk-rv-grid{grid-template-columns:1fr}.dk-rv-form-row{flex-direction:column}.dk-rv-form-section{padding:20px}}
  </style>
  <link rel="stylesheet" href="css/style.min.css?v=<?php echo date('Ymd'); ?>">
</head>
<body>
  <a href="#content" class="skip-link">Zum Inhalt springen</a>
  <header class="header">
    <div class="header-content">
      <a href="index.html" class="logo"><img src="images/logo-new.png" width="240" height="80" alt="Dokuments Hub"></a>
      <p>Rechtmäßige Studienberatung, Prüfungsvorbereitung, Anerkennungshilfe und Agentenvermittlung</p>
    </div>
  </header>
  <nav class="nav"><div class="nav-container">
    <a href="index.html">Startseite</a>
    <a href="bewertungen.html">Bewertungen</a>
  </div></nav>

  <main id="content">
    <div class="dk-reviews-page">
      <div class="dk-reviews-hero">
        <h1>Bewertungen</h1>
        <p>Echte Erfahrungen von Kunden, die unsere Beratung in Anspruch genommen haben.</p>
        <?php if ($totalReviews > 0): ?>
        <div class="dk-reviews-summary">
          <div class="dk-reviews-avg"><?php echo $avgRating; ?></div>
          <div>
            <div class="dk-reviews-stars-big"><?php echo str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)); ?></div>
            <div class="dk-reviews-count">basierend auf <?php echo $totalReviews; ?> Bewertung(en)</div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($formStatus === 'thanks'): ?>
        <div class="dk-rv-success">✅ Vielen Dank! Ihre Bewertung wurde eingereicht und wird nach Prüfung veröffentlicht.</div>
      <?php elseif ($formStatus === 'error'): ?>
        <div class="dk-rv-err">⚠️ <?php echo e($_GET['msg'] ?? 'Fehler beim Einreichen.'); ?></div>
      <?php endif; ?>

      <div class="dk-rv-grid">
        <?php if (!$reviews): ?>
          <div style="text-align:center;padding:40px;color:#999">Noch keine Bewertungen vorhanden.</div>
        <?php endif; ?>
        <?php foreach ($reviews as $r): ?>
          <div class="dk-rv-card">
            <div class="dk-rv-card-top">
              <div class="dk-rv-avatar"><?php echo e(mb_substr($r['author_name'], 0, 1)); ?></div>
              <div style="flex:1;min-width:0">
                <div class="dk-rv-author"><?php echo e($r['author_name']); ?></div>
                <div class="dk-rv-date"><?php echo e($r['review_date'] ? date('d.m.Y', strtotime($r['review_date'])) : ''); ?></div>
                <div class="dk-rv-stars"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></div>
                <?php if ($r['title']): ?>
                  <div class="dk-rv-title"><?php echo e($r['title']); ?></div>
                <?php endif; ?>
                <div class="dk-rv-body"><?php echo nl2br(e($r['body'])); ?></div>
              </div>
            </div>
            <?php if (!empty($r['image'])): ?>
              <img src="<?php echo e($r['image']); ?>" alt="Bewertungsbild" class="dk-rv-img" loading="lazy">
            <?php endif; ?>
            <?php if (!empty($r['product_title'])): ?>
              <div class="dk-rv-product">📦 <a href="product/<?php echo e($r['product_slug']); ?>.html"><?php echo e($r['product_title']); ?></a></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="dk-rv-form-section">
        <h2>Bewertung schreiben</h2>
        <p class="dk-rv-form-intro">Teilen Sie Ihre Erfahrung mit anderen. Ihre Bewertung wird nach Prüfung durch unser Team veröffentlicht.</p>
        <form class="dk-rv-form" action="review-submit.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="return_url" value="bewertungen.html">
          <div class="honeypot" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
            <input type="text" name="company_url" tabindex="-1" autocomplete="off">
          </div>
          <div class="dk-rv-form-row">
            <div class="dk-rv-form-field">
              <label>Ihr Name <span class="req">*</span></label>
              <input type="text" name="review_name" placeholder="Vor- und Nachname" required maxlength="80">
            </div>
            <div class="dk-rv-form-field">
              <label>E-Mail <span class="req">*</span></label>
              <input type="email" name="review_email" placeholder="ihre@email.de" required maxlength="120">
            </div>
          </div>
          <div class="dk-rv-form-field">
            <label>Produkt bewerten</label>
            <select name="product_id">
              <option value="0">— Allgemein —</option>
              <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['title']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="dk-rv-form-field">
            <label>Sternebewertung <span class="req">*</span></label>
            <div class="dk-rv-star-input">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="rv-star<?php echo $i; ?>" value="<?php echo $i; ?>" required <?php echo $i === 5 ? 'checked' : ''; ?>>
                <label for="rv-star<?php echo $i; ?>" title="<?php echo $i; ?> Stern(e)">★</label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="dk-rv-form-field">
            <label>Titel</label>
            <input type="text" name="review_title" placeholder="Kurze Zusammenfassung" maxlength="120">
          </div>
          <div class="dk-rv-form-field">
            <label>Ihre Bewertung <span class="req">*</span></label>
            <textarea name="review_body" rows="4" placeholder="Wie war Ihre Erfahrung?" required maxlength="3000"></textarea>
          </div>
          <div class="dk-rv-form-field">
            <label>Bild hochladen (optional)</label>
            <input type="file" name="review_image" accept="image/webp,image/jpeg,image/png">
            <small style="color:#aaa;font-size:.78rem">WebP, JPG oder PNG.</small>
          </div>
          <button type="submit" class="dk-rv-submit">Bewertung einreichen</button>
        </form>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="footer-content">
      <div class="footer-contact">
        <h3>Beratung anfragen</h3>
        <div class="footer-buttons">
          <a href="kontakt.html" class="footer-btn">Anfrageformular</a>
          <a href="https://t.me/mikibucherbox" class="footer-btn footer-btn-small" target="_blank" rel="noopener">Telegram</a>
          <a href="https://wa.me/+491791530217" class="footer-btn footer-btn-small" target="_blank" rel="noopener">WhatsApp</a>
        </div>
      </div>
      <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> Dokuments Hub.</p></div>
    </div>
  </footer>
  <script src="js/chat-widget.js" defer></script>
  <script src="js/session-timer.js" defer></script>
</body>
</html>
