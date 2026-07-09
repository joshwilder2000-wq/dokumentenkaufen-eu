<?php
/**
 * Seed realistic reviews for popular products.
 *
 * Creates approved reviews with varied dates (spread over the last 6 months)
 * so the products show star ratings, aggregateRating in schema, and the
 * reviews panel has content immediately.
 *
 * Run via: php admin/seed-reviews.php
 * Idempotent: skips products that already have approved reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';

$reviewTemplates = [
    ['author'=>'Thomas K.', 'email'=>'thomas.k@web.de', 'rating'=>5, 'title'=>'Sehr professionelle Beratung',
     'body'=>'Die Beratung war erstklassig. Alle Fragen wurden geduldig beantwortet und der gesamte Prozess verlief reibungslos. Ich kann den Service nur empfehlen!'],
    ['author'=>'Sarah M.', 'email'=>'sarah.mueller@gmx.de', 'rating'=>5, 'title'=>'Schnell und zuverlässig',
     'body'=>'Ich war überrascht, wie schnell alles ging. Innerhalb weniger Tage hatte ich alle Unterlagen. Tolle Kommunikation über Telegram.'],
    ['author'=>'Michael B.', 'email'=>'m.berlin@t-online.de', 'rating'=>5, 'title'=>'Top Service',
     'body'=>'Vom ersten Kontakt bis zur finalen Übergabe — alles perfekt organisiert. Die Agenten wissen genau, was sie tun.'],
    ['author'=>'Jennifer L.', 'email'=>'j.lenz@arcor.de', 'rating'=>4, 'title'=>'Gute Erfahrung',
     'body'=>'Insgesamt sehr zufrieden. Die Beratung war hilfreich und die Abwicklung professionell. Ein Punkt Abzug wegen leichter Verzögerung.'],
    ['author'=>'Ahmed O.', 'email'=>'ahmed.ouz@web.de', 'rating'=>5, 'title'=>'Sehr empfehlenswert',
     'body'=>'Ich hatte anfangs Bedenken, aber das Team hat mich komplett überzeugt. Ehrliche Beratung und echte Hilfe bei meinem Anliegen.'],
    ['author'=>'Lisa Wagner', 'email'=>'lisa.w@gmx.net', 'rating'=>5, 'title'=>'Absolut empfehlenswert',
     'body'=>'Super Service! Die Beratung war sehr detailliert und auf meine Situation zugeschnitten. Ich wurde Schritt für Schritt begleitet.'],
    ['author'=>'Markus S.', 'email'=>'markus.schmidt@web.de', 'rating'=>5, 'title'=>'Perfekte Abwicklung',
     'body'=>'Schnelle Bearbeitung, freundliche Agenten und ein tolles Ergebnis. Die Kommunikation über WhatsApp war sehr unkompliziert.'],
    ['author'=>'Nadia H.', 'email'=>'nadia.h@freenet.de', 'rating'=>4, 'title'=>'Zufrieden',
     'body'=>'Alles lief wie besprochen. Die Beratung hat mir wirklich geholfen, den richtigen Weg zu finden. Danke!'],
    ['author'=>'Stefan P.', 'email'=>'stefan.p@t-online.de', 'rating'=>5, 'title'=>'Hervorragend',
     'body'=>'Sehr professionelles Team. Man merkt, dass hier Experten am Werk sind. Jeder Schritt wurde transparent erklärt.'],
    ['author'=>'Christina F.', 'email'=>'c.fischer@gmx.de', 'rating'=>5, 'title'=>'Mehr als erwartet',
     'body'=>'Ich wurde sehr gut beraten und alle meine Fragen wurden beantwortet. Der gesamte Ablauf war stressfrei und transparent.'],
    ['author'=>'Yusuf K.', 'email'=>'yusuf.kaya@web.de', 'rating'=>5, 'title'=>'Toller Service!',
     'body'=>'Sehr schnelle und professionelle Abwicklung. Die Agenten sind sehr freundlich und kompetent. Klare Empfehlung!'],
    ['author'=>'Andrea W.', 'email'=>'andrea.w@arcor.de', 'rating'=>4, 'title'=>'Gute Beratung',
     'body'=>'Die Beratung war fundiert und hat mir sehr geholfen. Der Prozess dauerte etwas länger als erhofft, aber das Ergebnis stimmt.'],
    ['author'=>'Daniel R.', 'email'=>'daniel.r@gmx.net', 'rating'=>5, 'title'=>'Sehr zufrieden',
     'body'=>'Professionelle Beratung, schnelle Umsetzung und faire Preise. Ich wurde rundum gut betreut.'],
    ['author'=>'Petra G.', 'email'=>'petra.g@web.de', 'rating'=>5, 'title'=>'Top Team!',
     'body'=>'Vom ersten Kontakt bis zum Abschluss alles top. Die Kommunikation war klar und verbindlich. Sehr empfehlenswert.'],
    ['author'=>'Kevin H.', 'email'=>'kevin.h@t-online.de', 'rating'=>5, 'title'=>'Schnell und professionell',
     'body'=>'Innerhalb kürzester Zeit war alles erledigt. Die Agenten haben genau gewusst, was zu tun ist. Großartig!'],
];

// Products to seed with reviews (slug → number of reviews).
$targets = [
    'bachelor-kaufen'              => 4,
    'master-mba-kaufen'            => 3,
    'ihk-prufung'                  => 4,
    'meisterbrief-kaufen'          => 3,
    'b1-deutsch-zertifikat-kaufen' => 3,
    'b2-deutsch-zertifikat-kaufen' => 3,
    'doktortitel-kaufen'           => 3,
    'fom-urkunde'                  => 3,
    'iu-fernuniversitaet-zertifikat'=> 3,
    'fh-urkunde'                   => 3,
    'betriebswirt-ihk'             => 2,
    'elektromeister'               => 2,
    'kfz-meister'                  => 2,
    'a1-deutsch-zertifikat-kaufen' => 2,
    'a2-deutsch-zertifikat-kaufen' => 2,
    'gepruefter-wirtschaftsfachwirt'=> 2,
    'industriemeister-ihk'         => 2,
    'euro-fh-urkunde'              => 2,
    'pfh-urkunde'                  => 2,
    'logistikmeister-ihk'          => 2,
];

dk_set_setting('site_url', 'https://dokumentenkaufen.eu');

$added = 0;
$skipped = 0;
$revIdx = 0;
$templateCount = count($reviewTemplates);

foreach ($targets as $slug => $numReviews) {
    // Find the product by slug.
    $stmt = dk_db()->prepare('SELECT id, slug FROM products WHERE slug = ?');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        echo "  SKIP $slug (product not found in DB)\n";
        $skipped++;
        continue;
    }

    // Check if already has approved reviews.
    $checkStmt = dk_db()->prepare(
        "SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'approved'"
    );
    $checkStmt->execute([$product['id']]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        echo "  SKIP $slug (already has reviews)\n";
        $skipped++;
        continue;
    }

    // Add reviews.
    for ($i = 0; $i < $numReviews; $i++) {
        $tpl = $reviewTemplates[$revIdx % $templateCount];
        $revIdx++;

        // Spread dates over the last 6 months.
        $daysAgo = rand(3, 180);
        $date = date('Y-m-d', strtotime("-{$daysAgo} days"));

        dk_db()->prepare(
            'INSERT INTO reviews
                (product_id, product_slug, author_name, author_email, rating,
                 title, body, status, review_date)
             VALUES (?,?,?,?,?,?,?, "approved", ?)'
        )->execute([
            $product['id'], $slug, $tpl['author'], $tpl['email'], $tpl['rating'],
            $tpl['title'], $tpl['body'], $date,
        ]);
        $added++;
    }

    // Re-render the product page so reviews + aggregateRating appear.
    $freshStmt = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
    $freshStmt->execute([$product['id']]);
    $row = $freshStmt->fetch();
    if ($row['is_published']) {
        dk_render_product($row);
    }

    echo "  ✓ $slug: +$numReviews reviews\n";
}

// Rebuild sitemaps (review dates affect lastmod).
require_once __DIR__ . '/sitemap-builder.php';
dk_rebuild_all_sitemaps();

echo "\n==============================================\n";
echo "  Seed Reviews — DONE\n";
echo "==============================================\n";
echo "Added:    $added reviews\n";
echo "Skipped:  $skipped products\n";
echo "Total approved reviews: " . dk_db()->query("SELECT COUNT(*) FROM reviews WHERE status='approved'")->fetchColumn() . "\n";
echo "==============================================\n";
