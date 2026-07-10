<?php
/**
 * Seed photo reviews from the original backup's image/Reviews/ folder.
 *
 * Each review has an image, a German name, rating, title, body, and date.
 * Linked to the closest matching product.
 *
 * Run via: php admin/seed-photo-reviews.php
 * Idempotent: skips if product already has image-bearing reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

dk_set_setting('site_url', 'https://dokumentenkaufen.eu');

/**
 * The 14 photo reviews mapped to products.
 */
function dk_photo_reviews(): array
{
    return [
        [
            'image' => 'images/Reviews/FOM MBA Business administration.webp',
            'slug' => 'fom-urkunde',
            'author' => 'Marco D.', 'rating' => 5,
            'title' => 'FOM MBA — Top Beratung',
            'body' => 'Die Beratung zur FOM war erstklassig. Ich hatte innerhalb kurzer Zeit alle Unterlagen. Sehr professionelle Abwicklung!',
            'days_ago' => 45,
        ],
        [
            'image' => 'images/Reviews/FOM urkunde.webp',
            'slug' => 'fom-urkunde',
            'author' => 'Janina K.', 'rating' => 5,
            'title' => 'Schnell und zuverlässig',
            'body' => 'FOM Urkunde ohne Probleme erhalten. Das Team war sehr hilfsbereit und hat alle Fragen geduldig beantwortet.',
            'days_ago' => 60,
        ],
        [
            'image' => 'images/Reviews/Geprufter Betriebswirt -Rhein-Nektar',
            'slug' => 'betriebswirt-ihk',
            'author' => 'Stefan R.', 'rating' => 5,
            'title' => 'Geprüfter Betriebswirt IHK',
            'body' => 'Sehr zufrieden mit der Beratung. Alles verlief reibungslos und ich konnte meinen Abschluss wie geplant einreichen.',
            'days_ago' => 30,
        ],
        [
            'image' => 'images/Reviews/Geprufter Industriemeister fachrichtung Pharmazie- IHK Hochrhein-Bodensce.webp',
            'slug' => 'industriemeister-ihk',
            'author' => 'Thomas B.', 'rating' => 5,
            'title' => 'Industriemeister Pharmazie',
            'body' => 'Perfekte Unterstützung bei der IHK-Prüfung. Die Experten wussten genau, was zu tun ist. Klare Empfehlung!',
            'days_ago' => 75,
        ],
        [
            'image' => 'images/Reviews/Geprufter Wirtschaftfachwirth.webp',
            'slug' => 'gepruefter-wirtschaftsfachwirt',
            'author' => 'Andrea M.', 'rating' => 4,
            'title' => 'Wirtschaftsfachwirt — gute Erfahrung',
            'body' => 'Die Beratung war fundiert und hat mir sehr geholfen. Der Prozess dauerte etwas länger als erhofft, aber das Ergebnis stimmt.',
            'days_ago' => 90,
        ],
        [
            'image' => 'images/Reviews/HWK meisterbrief Bremen, kraftfahrzeugtechniker-Handwerk.webp',
            'slug' => 'kfz-meister',
            'author' => 'Klaus W.', 'rating' => 5,
            'title' => 'Kfz-Meisterbrief Bremen',
            'body' => 'Hervorragender Service! Innerhalb kürzester Zeit hatte ich meinen Meisterbrief. Die Kommunikation über WhatsApp war sehr unkompliziert.',
            'days_ago' => 50,
        ],
        [
            'image' => 'images/Reviews/HWK meisterprufungzeugnis Munchen.webp',
            'slug' => 'meisterbrief-kaufen',
            'author' => 'Michael S.', 'rating' => 5,
            'title' => 'HWK Meisterprüfung München',
            'body' => 'Sehr professionelles Team. Man merkt, dass hier Experten am Werk sind. Jeder Schritt wurde transparent erklärt.',
            'days_ago' => 110,
        ],
        [
            'image' => 'images/Reviews/IHK Braunschweig prufungzeugnis.webp',
            'slug' => 'ihk-prufung',
            'author' => 'Yusuf K.', 'rating' => 5,
            'title' => 'IHK Braunschweig — perfekt',
            'body' => 'Toller Service! Die Beratung war sehr detailliert und auf meine Situation zugeschnitten. Ich wurde Schritt für Schritt begleitet.',
            'days_ago' => 65,
        ],
        [
            'image' => 'images/Reviews/IHK Leipzig.webp',
            'slug' => 'ihk-prufung',
            'author' => 'Petra G.', 'rating' => 4,
            'title' => 'IHK Leipzig Zeugnis',
            'body' => 'Alles lief wie besprochen. Die Beratung hat mir wirklich geholfen, den richtigen Weg zu finden. Danke an das Team!',
            'days_ago' => 80,
        ],
        [
            'image' => 'images/Reviews/IHK industriemeister.webp',
            'slug' => 'industriemeister-ihk',
            'author' => 'Daniel R.', 'rating' => 5,
            'title' => 'IHK Industriemeister',
            'body' => 'Sehr schnelle und professionelle Abwicklung. Die Agenten sind sehr freundlich und kompetent. Klare Empfehlung!',
            'days_ago' => 40,
        ],
        [
            'image' => 'images/Reviews/IHK meisterbrief Geprufter industriemeister.webp',
            'slug' => 'meisterbrief-kaufen',
            'author' => 'Kevin H.', 'rating' => 5,
            'title' => 'IHK Meisterbrief — top!',
            'body' => 'Innerhalb kürzester Zeit war alles erledigt. Die Agenten haben genau gewusst, was zu tun ist. Großartig!',
            'days_ago' => 55,
        ],
        [
            'image' => 'images/Reviews/IHK stuttgart.webp',
            'slug' => 'ihk-prufung',
            'author' => 'Christina F.', 'rating' => 5,
            'title' => 'IHK Stuttgart Zeugnis',
            'body' => 'Ich wurde sehr gut beraten und alle meine Fragen wurden beantwortet. Der gesamte Ablauf war stressfrei und transparent.',
            'days_ago' => 100,
        ],
        [
            'image' => 'images/Reviews/IHK zeugnis.webp',
            'slug' => 'ihk-prufung',
            'author' => 'Lisa W.', 'rating' => 5,
            'title' => 'IHK Zeugnis — empfehlenswert',
            'body' => 'Super Service! Die Beratung war klar und verbindlich. Sehr empfehlenswert für alle, die schnelle Hilfe brauchen.',
            'days_ago' => 70,
        ],
        [
            'image' => 'images/Reviews/WBK.webp',
            'slug' => 'waffenbesitzkarte-wbk',
            'author' => 'Markus P.', 'rating' => 5,
            'title' => 'Waffenbesitzkarte (WBK)',
            'body' => 'Professionelle Beratung, schnelle Umsetzung. Ich wurde rundum gut betreut und kann den Service nur weiterempfehlen.',
            'days_ago' => 35,
        ],
    ];
}

// --- Run ---
$reviews = dk_photo_reviews();
$added = 0;
$skipped = 0;

foreach ($reviews as $rev) {
    // Find product by slug.
    $stmt = dk_db()->prepare('SELECT id, slug FROM products WHERE slug = ?');
    $stmt->execute([$rev['slug']]);
    $product = $stmt->fetch();

    if (!$product) {
        echo "  SKIP {$rev['slug']} (product not found)\n";
        $skipped++;
        continue;
    }

    // Skip if this product already has an image review.
    $chk = dk_db()->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND image != ''");
    $chk->execute([$product['id']]);
    if ((int)$chk->fetchColumn() > 0) {
        echo "  SKIP {$rev['slug']} (already has photo reviews)\n";
        $skipped++;
        continue;
    }

    $date = date('Y-m-d', strtotime("-{$rev['days_ago']} days"));

    dk_db()->prepare(
        'INSERT INTO reviews (product_id, product_slug, author_name, author_email, rating, title, body, image, status, review_date)
         VALUES (?,?,?,?,?,?,?,?,"approved",?)'
    )->execute([
        $product['id'], $rev['slug'], $rev['author'], 'review@dokumenthub.space',
        $rev['rating'], $rev['title'], $rev['body'], $rev['image'], $date,
    ]);
    $added++;

    // Re-render the product page so the review + image appears.
    $fresh = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $fresh->execute([$product['id']]);
    $row = $fresh->fetch();
    if ($row['is_published']) {
        dk_render_product($row);
    }

    echo "  ✓ {$rev['slug']}: +1 photo review ({$rev['author']}, {$rev['rating']}★)\n";
}

require_once __DIR__ . '/sitemap-builder.php';
dk_rebuild_all_sitemaps();

echo "\n==============================================\n";
echo "  Photo Reviews Seed — DONE\n";
echo "==============================================\n";
echo "Added:    $added reviews\n";
echo "Skipped:  $skipped\n";
echo "Total reviews: " . dk_db()->query("SELECT COUNT(*) FROM reviews WHERE status='approved'")->fetchColumn() . "\n";
echo "With images:   " . dk_db()->query("SELECT COUNT(*) FROM reviews WHERE image != '' AND status='approved'")->fetchColumn() . "\n";
echo "==============================================\n";
