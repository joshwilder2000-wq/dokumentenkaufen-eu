<?php
/**
 * Build all 44 missing product pages.
 *
 * Defines real product data (title, meta, descriptions, features, process steps)
 * for each missing product, imports them into the DB, renders the static HTML,
 * and rebuilds the product sitemap.
 *
 * Run once via: php admin/build-missing-products.php
 * Idempotent: updates existing rows if re-run.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/renderer.php';
require_once __DIR__ . '/sitemap-builder.php';

/**
 * The 44 missing products with full data.
 */
function dk_missing_products(): array
{
    // Helper to reduce repetition in the arrays.
    $defaultSteps = [
        ['title' => 'Ziel klären', 'text' => 'Sie beschreiben Ihr Ziel, Ihren Bedarf und Ihre aktuelle Situation.'],
        ['title' => 'Unterlagen prüfen', 'text' => 'Ein Berater prüft vorhandene Nachweise, Fristen und Voraussetzungen.'],
        ['title' => 'Plan erhalten', 'text' => 'Sie bekommen einen legalen Aktionsplan für Antrag, Vorbereitung oder offizielle Ersatzbeschaffung.'],
        ['title' => 'Begleitung', 'text' => 'Bei Bedarf vermitteln wir an passende Experten oder administrative Ansprechpartner.'],
    ];
    $defaultFeatures = [
        'Beratung und Orientierung',
        'Prüfung der Unterlagen und Voraussetzungen',
        'Hilfe bei Anträgen und offiziellen Stellen',
        'Vermittlung an qualifizierte Ansprechpartner',
    ];

    $items = [];

    // ===== GEWERBEORDNUNG / OFFICIAL DOCUMENTS =====
    $items[] = ['slug'=>'34f-gewerbeordnung','title'=>'34f Sachkundeprüfung Gewerbeordnung','category'=>'gewerbeordnung',
        'meta_description'=>'Rechtmäßige Beratung zur 34f-Sachkundeprüfung nach der Gewerbeordnung für das Bewachungsgewerbe. Antragshilfe und Vorbereitung.',
        'meta_keywords'=>'34f Gewerbeordnung, Sachkundeprüfung, Bewachungsgewerbe, GewO, Beratung',
        'short_description'=>'Beratung zur 34f-Sachkundeprüfung nach § 34a GewO für das Bewachungsgewerbe.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'34i-gewerbeordnung','title'=>'34i Sachkundeprüfung Geldtransport','category'=>'gewerbeordnung',
        'meta_description'=>'Rechtmäßige Beratung zur 34i-Sachkundeprüfung nach der Gewerbeordnung für Geldtransport und Wertlogistik.',
        'meta_keywords'=>'34i Gewerbeordnung, Sachkundeprüfung, Geldtransport, GewO, Beratung',
        'short_description'=>'Beratung zur 34i-Sachkundeprüfung für Geldtransport und Wertlogistik.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'aufenthaltserlaubnis','title'=>'Aufenthaltserlaubnis Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Rechtmäßige Beratung und Antragshilfe für Aufenthaltserlaubnisse nach dem AufenthG. Vorbereitung, Unterlagen und Anerkennung.',
        'meta_keywords'=>'Aufenthaltserlaubnis, AufenthG, Aufenthaltsrecht, Antragshilfe, Beratung',
        'short_description'=>'Beratung und Antragshilfe für Aufenthaltserlaubnisse nach dem AufenthG.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'betaeubungsmittelrezept','title'=>'Betäubungsmittelrezept Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Rechtmäßige Beratung zum Betäubungsmittelrezept (BtM-Rezept). Informationen zu Vorschriften, beantragung und korrekter Verwendung.',
        'meta_keywords'=>'Betäubungsmittelrezept, BtM-Rezept, BtMG, Verschreibung, Beratung',
        'short_description'=>'Beratung zum Betäubungsmittelrezept (BtM-Rezept) und den rechtlichen Vorschriften.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fuhrerschein-kaufen','title'=>'Führerschein Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Rechtmäßige Beratung rund um den Führerschein: Neubeantragung, Umschreibung, Ersatzbeschaffung bei Verlust und Anerkennung ausländischer Fahrerlaubnisse.',
        'meta_keywords'=>'Führerschein, Fahrerlaubnis, Neubeantragung, Umschreibung, Beratung',
        'short_description'=>'Beratung zu Führerschein-Angelegenheiten: Antrag, Umschreibung und Ersatz.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fahrerlaubnis-klasse-b-schluesselzahl-197','title'=>'Fahrerlaubnis Klasse B Schlüsselzahl 197','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zur Fahrerlaubnis Klasse B mit Schlüsselzahl 197 (Begleitetes Fahren ab 17). Antrag, Voraussetzungen und Begleitpersonen.',
        'meta_keywords'=>'Klasse B, Schlüsselzahl 197, BF17, Begleitetes Fahren, Führerschein',
        'short_description'=>'Beratung zur Fahrerlaubnis Klasse B mit Schlüsselzahl 197 (Begleitetes Fahren ab 17).',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'grundbuchauszuege-eigentumsnachweise','title'=>'Grundbuchauszüge und Eigentumsnachweise','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zu Grundbuchauszügen und Eigentumsnachweisen. Hilfe bei Beantragung beim Amtsgericht und Prüfung von Dokumenten.',
        'meta_keywords'=>'Grundbuchauszug, Eigentumsnachweis, Grundbuch, Amtsgericht, Beratung',
        'short_description'=>'Beratung zu Grundbuchauszügen und amtlichen Eigentumsnachweisen.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'notarielle-vollmachten','title'=>'Notarielle Vollmachten Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zu notariellen Vollmachten: Generalvollmacht, Vorsorgevollmacht, Handlungsvollmacht. Beantragung und rechtliche Prüfung.',
        'meta_keywords'=>'Notarielle Vollmacht, Generalvollmacht, Vorsorgevollmacht, Notar, Beratung',
        'short_description'=>'Beratung zu notariellen Vollmachten und deren rechtlicher Wirkung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'paspoort','title'=>'Paspoort (Niederländischer Ausweis)','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zum niederländischen Paspoort (Reisepass) und der Identiteitskaart (Personalausweis). Beantragung, Verlängerung und Ersatz.',
        'meta_keywords'=>'Paspoort, niederländischer Reisepass, Identiteitskaart, Niederlande, Beratung',
        'short_description'=>'Beratung zum niederländischen Paspoort und Identiteitskaart.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'personalausweis','title'=>'Personalausweis Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zum Personalausweis: Beantragung, Verlängerung, Ersatz bei Verlust und eID-Funktion. Behördliche Hilfe.',
        'meta_keywords'=>'Personalausweis, Ausweis, Beantragung, Verlängerung, eID, Beratung',
        'short_description'=>'Beratung zu Personalausweis-Angelegenheiten: Antrag, Verlängerung und Ersatz.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'professionelle-gehaltsnachweise','title'=>'Gehaltsnachweise Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zu professionellen Gehaltsnachweisen für Vermietung, Kredit und Behörden. Rechtliche Einordnung und offizielle Wege.',
        'meta_keywords'=>'Gehaltsnachweis, Einkommensnachweis, Gehaltsabrechnung, Vermietung, Beratung',
        'short_description'=>'Beratung zu Gehaltsnachweisen für Vermietung, Kredite und Behörden.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'reisepass','title'=>'Reisepass Beratung','category'=>'gewerbeordnung',
        'meta_description'=>'Beratung zum Reisepass: Beantragung, Verlängerung, Ersatz bei Verlust und Express-Verfahren. Offizielle und legale Wege.',
        'meta_keywords'=>'Reisepass, Passbeantragung, Express-Pass, Passverlängerung, Beratung',
        'short_description'=>'Beratung zu Reisepass-Angelegenheiten: Antrag, Verlängerung und Ersatz.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== BILDUNGSABSCHLÜSSE (SCHOOL CERTIFICATES) =====
    $items[] = ['slug'=>'abitur-kaufen','title'=>'Abitur Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Rechtmäßige Beratung zum Abitur: Nachholprüfung, Anerkennung, Ersatz bei Verlust und Beratung zu Bildungswegen.',
        'meta_keywords'=>'Abitur, Abiturzeugnis, Nachholprüfung, Anerkennung, Beratung',
        'short_description'=>'Beratung zum Abitur: Anerkennung, Ersatz und Bildungsweg-Beratung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'hauptschulabschluss-kaufen-qualifizierend','title'=>'Hauptschulabschluss Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zum Hauptschulabschluss (qualifizierend): Nachholprüfung, Anerkennung und offizieller Ersatz bei Verlust.',
        'meta_keywords'=>'Hauptschulabschluss, qualifizierender Hauptschulabschluss, Schulabschluss, Beratung',
        'short_description'=>'Beratung zum Hauptschulabschluss (qualifizierend) und dessen Anerkennung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'realschulabschluss-bzw-mittlere-reife-kaufen','title'=>'Realschulabschluss / Mittlere Reife Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zum Realschulabschluss (Mittlere Reife): Nachholprüfung, Anerkennung und offizieller Ersatz bei Verlust.',
        'meta_keywords'=>'Realschulabschluss, Mittlere Reife, Schulabschluss, Nachholprüfung, Beratung',
        'short_description'=>'Beratung zum Realschulabschluss (Mittlere Reife) und Anerkennung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'matura-osterreich','title'=>'Matura (Österreich) Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zur österreichischen Matura (Reifeprüfung): Anerkennung in Deutschland, Ersatz und Beratung zu Bildungswegen.',
        'meta_keywords'=>'Matura, Österreich, Reifeprüfung, Anerkennung, Beratung',
        'short_description'=>'Beratung zur österreichischen Matura und deren Anerkennung in Deutschland.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'transkript-notenuebersicht','title'=>'Transkript / Notenübersicht Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zu Transkripten und Notenübersichten von Universitäten und Hochschulen. Offizielle Beschaffung und Anerkennung.',
        'meta_keywords'=>'Transkript, Notenübersicht, Transcript of Records, Universität, Beratung',
        'short_description'=>'Beratung zu offiziellen Transkripten und Notenübersichten von Hochschulen.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== UNIVERSITÄT / FH =====
    $items[] = ['slug'=>'anerkennung-auslaendischer-abschluesse','title'=>'Anerkennung ausländischer Abschlüsse','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zur Anerkennung ausländischer Bildungsabschlüsse in Deutschland. Zeugnisbewertung, ZAB und offizielle Verfahren.',
        'meta_keywords'=>'Anerkennung, ausländische Abschlüsse, ZAB, Zeugnisbewertung, Beratung',
        'short_description'=>'Beratung zur Anerkennung ausländischer Bildungsabschlüsse in Deutschland.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fernstudium-abschluss-kaufen','title'=>'Fernstudium Abschluss Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zu Fernstudium-Abschlüssen: Anerkennung, Akkreditierung und offizieller Ersatz bei Verlust von Zeugnissen.',
        'meta_keywords'=>'Fernstudium, Fernuniversität, Abschluss, Anerkennung, Beratung',
        'short_description'=>'Beratung zu Fernstudium-Abschlüssen und deren Anerkennung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fh-aachen-abschluss-kaufen','title'=>'FH Aachen Abschluss Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zu Abschlüssen der FH Aachen: Anerkennung, Ersatz bei Verlust und offizielle Nachweisbeschaffung.',
        'meta_keywords'=>'FH Aachen, Fachhochschule, Abschluss, Beratung',
        'short_description'=>'Beratung zu Abschlüssen der FH Aachen.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'hochschule-fuer-angewandte-wissenschaften-hm-muenchen','title'=>'Hochschule München (HM) Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zu Abschlüssen der Hochschule für angewandte Wissenschaften München (HM). Anerkennung und offizieller Ersatz.',
        'meta_keywords'=>'Hochschule München, HM, angewandte Wissenschaften, Abschluss, Beratung',
        'short_description'=>'Beratung zu Abschlüssen der Hochschule München (HM).',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== IHK =====
    $items[] = ['slug'=>'aus-und-weiterbildungspaedagoge-ihk','title'=>'Aus- und Weiterbildungspädagoge IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur Prüfung Aus- und Weiterbildungspädagoge IHK. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Aus- und Weiterbildungspädagoge, IHK, Fortbildung, Pädagoge, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Aus- und Weiterbildungspädagoge.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'bilanzbuchhalter-ihk','title'=>'Geprüfter Bilanzbuchhalter IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Prüfung Geprüfter Bilanzbuchhalter. Vorbereitung, Zertifikat und offizieller Nachweis.',
        'meta_keywords'=>'Bilanzbuchhalter, IHK, Fortbildung, Rechnungswesen, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Geprüfter Bilanzbuchhalter.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fachberater-ihk','title'=>'Fachberater IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Fortbildung Fachberater. Vorbereitung, Anerkennung und offizieller Nachweis des Zertifikats.',
        'meta_keywords'=>'Fachberater, IHK, Fortbildung, Zertifikat, Beratung',
        'short_description'=>'Beratung zur IHK-Fortbildung Fachberater.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'fachwirt-ihk','title'=>'Fachwirt IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Prüfung Fachwirt. Vorbereitung, Anerkennung und offizieller Ersatz des Fachwirt-Zertifikats.',
        'meta_keywords'=>'Fachwirt, IHK, Fortbildung, Zertifikat, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Fachwirt.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'gepruefter-energieberater-ihk-hwk','title'=>'Geprüfter Energieberater IHK/HWK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur Fortbildung Geprüfter Energieberater IHK/HWK. Vorbereitung, Zertifikat und offizieller Nachweis.',
        'meta_keywords'=>'Energieberater, IHK, HWK, Fortbildung, Energieeffizienz, Beratung',
        'short_description'=>'Beratung zur Fortbildung Geprüfter Energieberater IHK/HWK.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'gepruefter-marketingfachwirt-ihk','title'=>'Geprüfter Marketingfachwirt IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Prüfung Geprüfter Marketingfachwirt. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Marketingfachwirt, IHK, Marketing, Fortbildung, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Geprüfter Marketingfachwirt.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'gepruefter-personaldienstleistungskaufmann-ihk','title'=>'Geprüfter Personaldienstleistungskaufmann IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Prüfung Geprüfter Personaldienstleistungskaufmann. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Personaldienstleistungskaufmann, IHK, Personal, Fortbildung, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Geprüfter Personaldienstleistungskaufmann.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'gepruefter-wirtschaftsinformatiker','title'=>'Geprüfter Wirtschaftsinformatiker','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur Fortbildung Geprüfter Wirtschaftsinformatiker. Vorbereitung, IHK-Zertifikat und offizieller Nachweis.',
        'meta_keywords'=>'Wirtschaftsinformatiker, IHK, IT, Fortbildung, Beratung',
        'short_description'=>'Beratung zur Fortbildung Geprüfter Wirtschaftsinformatiker.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'berufspaedagoge-ihk','title'=>'Berufspädagoge IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Fortbildung Berufspädagoge. Vorbereitung, Anerkennung und offizieller Nachweis des Zertifikats.',
        'meta_keywords'=>'Berufspädagoge, IHK, Fortbildung, Pädagogik, Beratung',
        'short_description'=>'Beratung zur IHK-Fortbildung Berufspädagoge.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'technischer-redakteur-technischer-dokumentar-ihk','title'=>'Technischer Redakteur / Technischer Dokumentar IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Fortbildung Technischer Redakteur / Technischer Dokumentar. Vorbereitung und offizieller Nachweis.',
        'meta_keywords'=>'Technischer Redakteur, Technischer Dokumentar, IHK, Fortbildung, Beratung',
        'short_description'=>'Beratung zur IHK-Fortbildung Technischer Redakteur / Dokumentar.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'uebersetzer-dolmetscher-ihk','title'=>'Übersetzer / Dolmetscher IHK','category'=>'ihk-zertifikate',
        'meta_description'=>'Beratung zur IHK-Prüfung Übersetzer und Dolmetscher. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Übersetzer, Dolmetscher, IHK, Prüfung, Sprachen, Beratung',
        'short_description'=>'Beratung zur IHK-Prüfung Übersetzer und Dolmetscher.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== HWK / MEISTERBRIEFE =====
    $items[] = ['slug'=>'augenoptikermeister','title'=>'Augenoptikermeister (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung Augenoptikermeister (HWK). Vorbereitung, Anerkennung und offizieller Meisterbrief.',
        'meta_keywords'=>'Augenoptikermeister, Augenoptik, HWK, Meisterbrief, Meisterprüfung',
        'short_description'=>'Beratung zur HWK-Meisterprüfung Augenoptikermeister.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'baeckermeister','title'=>'Bäckermeister (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung Bäckermeister (HWK). Vorbereitung, Anerkennung und offizieller Meisterbrief.',
        'meta_keywords'=>'Bäckermeister, Bäcker, HWK, Meisterbrief, Meisterprüfung',
        'short_description'=>'Beratung zur HWK-Meisterprüfung Bäckermeister.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'dachdeckermeister','title'=>'Dachdeckermeister (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung Dachdeckermeister (HWK). Vorbereitung, Anerkennung und offizieller Meisterbrief.',
        'meta_keywords'=>'Dachdeckermeister, Dachdecker, HWK, Meisterbrief, Meisterprüfung',
        'short_description'=>'Beratung zur HWK-Meisterprüfung Dachdeckermeister.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'designer-hwk','title'=>'Designer (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur HWK-Prüfung Geprüfter Designer. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Designer, Gestalter, HWK, Kommunikationsdesign, Beratung',
        'short_description'=>'Beratung zur HWK-Prüfung Geprüfter Designer.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'malermeister','title'=>'Malermeister (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung Malermeister (HWK). Vorbereitung, Anerkennung und offizieller Meisterbrief.',
        'meta_keywords'=>'Malermeister, Maler, HWK, Meisterbrief, Meisterprüfung',
        'short_description'=>'Beratung zur HWK-Meisterprüfung Malermeister.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'restaurator-im-handwerk-hwk','title'=>'Restaurator im Handwerk (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur HWK-Fortbildung Restaurator im Handwerk. Vorbereitung, Anerkennung und offizieller Nachweis.',
        'meta_keywords'=>'Restaurator, Handwerk, HWK, Restaurierung, Fortbildung',
        'short_description'=>'Beratung zur HWK-Fortbildung Restaurator im Handwerk.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'shk-meister-sanitaer-heizungs-klimatechnik','title'=>'SHK Meister (Sanitär, Heizung, Klima)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung SHK (Sanitär-, Heizungs- und Klimatechnik). Vorbereitung und offizieller Meisterbrief.',
        'meta_keywords'=>'SHK Meister, Sanitär, Heizung, Klimatechnik, HWK, Meisterbrief',
        'short_description'=>'Beratung zur HWK-Meisterprüfung SHK (Sanitär, Heizung, Klima).',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'tischlermeister','title'=>'Tischlermeister (HWK)','category'=>'hwk-meisterbriefe',
        'meta_description'=>'Beratung zur Meisterprüfung Tischlermeister (HWK). Vorbereitung, Anerkennung und offizieller Meisterbrief.',
        'meta_keywords'=>'Tischlermeister, Tischler, Schreiner, HWK, Meisterbrief',
        'short_description'=>'Beratung zur HWK-Meisterprüfung Tischlermeister.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== SPRACHZERTIFIKATE =====
    $items[] = ['slug'=>'zertifikat-deutsch-test-fuer-zuwanderer-telc','title'=>'Zertifikat Deutsch / telc Deutsch','category'=>'sprachzertifikate',
        'meta_description'=>'Beratung zum Zertifikat Deutsch (telc Deutsch B1). Vorbereitung, Anerkennung und offizielle Prüfungsvorbereitung.',
        'meta_keywords'=>'Zertifikat Deutsch, telc Deutsch, B1, Goethe, Sprachzertifikat, Beratung',
        'short_description'=>'Beratung zum Zertifikat Deutsch / telc Deutsch B1.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'gestalter-fuer-visuelles-marketing','title'=>'Gestalter für visuelles Marketing','category'=>'sprachzertifikate',
        'meta_description'=>'Beratung zur Fortbildung Gestalter für visuelles Marketing. Vorbereitung, IHK/HWK und offizieller Nachweis.',
        'meta_keywords'=>'Gestalter, visuelles Marketing, Visual Merchandising, Fortbildung, Beratung',
        'short_description'=>'Beratung zur Fortbildung Gestalter für visuelles Marketing.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    // ===== INTERNATIONAL =====
    $items[] = ['slug'=>'sds-switzerland-certificate','title'=>'SDS Switzerland Certificate Beratung','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zum SDS Switzerland Certificate. Anerkennung, Prüfung und offizieller Nachweis für Schweizer Qualifikationen.',
        'meta_keywords'=>'SDS Switzerland, Swiss Diploma Supplement, Schweiz, Zertifikat, Beratung',
        'short_description'=>'Beratung zum SDS Switzerland Certificate und dessen Anerkennung.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];
    $items[] = ['slug'=>'titulo-certificado-de-la-uned-universidad-nacional-de-educacion-a-distancia','title'=>'Título UNED (Universidad Nacional de Educación a Distancia)','category'=>'universitaetsdokumente',
        'meta_description'=>'Beratung zum Título der UNED (Spanien). Anerkennung in Deutschland, Zeugnisbewertung und offizielle Verfahren.',
        'meta_keywords'=>'UNED, Título, Spanien, Anerkennung, Universidad Nacional, Beratung',
        'short_description'=>'Beratung zum Título der UNED (Spanien) und Anerkennung in Deutschland.',
        'features'=>$defaultFeatures,'process_steps'=>$defaultSteps];

    return $items;
}

/**
 * Generate a unique SKU for a product (DK-<category-prefix>-<slug-hash>).
 */
function dk_make_sku(string $slug, string $category): string
{
    $prefix = [
        'universitaetsdokumente' => 'UNI',
        'ihk-zertifikate'        => 'IHK',
        'hwk-meisterbriefe'      => 'HWK',
        'sprachzertifikate'      => 'SPR',
        'gewerbeordnung'         => 'GEW',
    ][$category] ?? 'DK';
    $hash = strtoupper(substr(md5($slug), 0, 8));
    return 'DK-' . $prefix . '-' . $hash;
}

/**
 * Map a site category to the Google Product Taxonomy category ID.
 * @see https://www.google.com/basepages/producttype/taxonomy-with-ids.de-DE.txt
 */
function dk_google_product_category(string $category): string
{
    return [
        'universitaetsdokumente' => '1001',     // Bildung
        'ihk-zertifikate'        => '1001',     // Bildung
        'hwk-meisterbriefe'      => '1001',     // Bildung
        'sprachzertifikate'      => '1001',     // Bildung
        'gewerbeordnung'         => '1072',     // Dienstleistungen
    ][$category] ?? '1001';
}

// ---------------------------------------------------------------------------
// Run.
// ---------------------------------------------------------------------------
$products = dk_missing_products();
$added = 0; $updated = 0; $errors = [];

dk_set_setting('site_url', 'https://dokumentenkaufen.eu');

foreach ($products as $item) {
    try {
        $slug = $item['slug'];

        // Generate Merchant identifiers.
        $sku    = dk_make_sku($slug, $item['category']);
        $mpn    = $sku; // MPN = same as SKU for our products
        $gpc    = dk_google_product_category($item['category']);

        // Check if exists.
        $stmt = dk_db()->prepare('SELECT id FROM products WHERE slug = ?');
        $stmt->execute([$slug]);
        $existing = $stmt->fetch();

        $row = [
            'slug' => $slug,
            'title' => $item['title'],
            'meta_description' => $item['meta_description'],
            'meta_keywords' => $item['meta_keywords'],
            'category' => $item['category'],
            'og_image' => '',
            'short_description' => $item['short_description'],
            'main_description' => '',
            'features' => json_encode($item['features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'process_steps' => json_encode($item['process_steps'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sku' => $sku,
            'mpn' => $mpn,
            'gtin' => '',
            'google_product_category' => $gpc,
        ];

        if ($existing) {
            dk_db()->prepare(
                'UPDATE products SET
                    title = ?, meta_description = ?, meta_keywords = ?,
                    category = ?, short_description = ?, features = ?, process_steps = ?,
                    sku = ?, mpn = ?, gtin = ?, google_product_category = ?,
                    updated_at = datetime("now")
                 WHERE slug = ?'
            )->execute([
                $row['title'], $row['meta_description'], $row['meta_keywords'],
                $row['category'], $row['short_description'], $row['features'], $row['process_steps'],
                $row['sku'], $row['mpn'], $row['gtin'], $row['google_product_category'],
                $slug,
            ]);
            $id = (int) $existing['id'];
            $updated++;
        } else {
            dk_db()->prepare(
                'INSERT INTO products
                    (slug, title, meta_description, meta_keywords, category, og_image,
                     short_description, main_description, features, process_steps,
                     sku, mpn, gtin, google_product_category,
                     is_published, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 1, 0)'
            )->execute([
                $row['slug'], $row['title'], $row['meta_description'], $row['meta_keywords'],
                $row['category'], $row['og_image'], $row['short_description'],
                $row['main_description'], $row['features'], $row['process_steps'],
                $row['sku'], $row['mpn'], $row['gtin'], $row['google_product_category'],
            ]);
            $id = (int) dk_db()->lastInsertId();
            $added++;
        }

        // Render the static HTML file.
        $fresh = dk_db()->prepare('SELECT * FROM products WHERE id = ?');
        $fresh->execute([$id]);
        $fullRow = $fresh->fetch();
        dk_render_product($fullRow);
    } catch (Throwable $e) {
        $errors[] = $item['slug'] . ': ' . $e->getMessage();
    }
}

// Rebuild sitemaps.
dk_rebuild_all_sitemaps();

echo "==============================================\n";
echo "  Build missing products — DONE\n";
echo "==============================================\n";
echo "Added:    $added\n";
echo "Updated:  $updated\n";
echo "Errors:   " . count($errors) . "\n";
foreach ($errors as $e) {
    echo "  - $e\n";
}
$total = (int) dk_db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
echo "Total products in DB: $total\n";
echo "==============================================\n";
