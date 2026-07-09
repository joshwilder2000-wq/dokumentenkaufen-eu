<?php
/**
 * Generate the 5 dedicated form pages.
 *
 * Each page has its own specific fields. Forms POST to /form-handler.php.
 * Run once: php admin/build-form-pages.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

dk_set_setting('site_url', 'https://dokumentenkaufen.eu');

/**
 * Form page definitions.
 */
function dk_form_definitions(): array
{
    $commonContact = [
        ['type' => 'text', 'name' => 'name', 'label' => 'Vollständiger Name', 'required' => true, 'placeholder' => 'Vor- und Nachname'],
        ['type' => 'text', 'name' => 'birth_date', 'label' => 'Geburtsdatum', 'required' => true, 'placeholder' => 'TT.MM.JJJJ'],
        ['type' => 'text', 'name' => 'birth_place', 'label' => 'Geburtsort', 'required' => true],
        ['type' => 'email', 'name' => 'email', 'label' => 'E-Mail-Adresse', 'required' => true, 'placeholder' => 'ihre@email.de'],
        ['type' => 'row', 'fields' => [
            ['type' => 'text', 'name' => 'whatsapp_cc', 'label' => 'WhatsApp-Vorwahl', 'value' => '+49', 'width' => '80px'],
            ['type' => 'tel', 'name' => 'whatsapp_number', 'label' => 'WhatsApp-Nummer', 'required' => true, 'placeholder' => '170 1234567'],
        ]],
        ['type' => 'textarea', 'name' => 'address', 'label' => 'Aktuelle Adresse', 'required' => true, 'rows' => 2, 'placeholder' => 'Straße, PLZ, Stadt, Land'],
    ];

    return [
        'formular-fuer-sprachpruefungen' => [
            'title' => 'Formular für Sprachprüfungen (telc / Goethe)',
            'description' => 'Reichen Sie Ihre Daten für eine Sprachprüfung (telc oder Goethe) ein.',
            'kicker' => 'Sprachprüfung',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'exam_type', 'label' => 'Prüfungstyp', 'required' => true, 'options' => [
                    'Goethe-Zertifikat A1', 'Goethe-Zertifikat A2', 'Goethe-Zertifikat B1', 'Goethe-Zertifikat B2',
                    'Goethe-Zertifikat C1', 'Goethe-Zertifikat C2',
                    'telc Deutsch A1', 'telc Deutsch A2', 'telc Deutsch B1', 'telc Deutsch B2',
                    'telc Deutsch C1', 'telc Deutsch C2',
                    'Deutsch-Test für Zuwanderer (DTZ)',
                ]],
                ['type' => 'text', 'name' => 'desired_level', 'label' => 'Gewünschtes Niveau', 'required' => true, 'placeholder' => 'z.B. B1'],
                ['type' => 'select', 'name' => 'urgency', 'label' => 'Dringlichkeit', 'options' => ['Normal', 'Express (48h)', 'Sehr dringend']],
            ], $commonContact),
        ],
        'hwk-zeugnisvorform' => [
            'title' => 'HWK Zeugnis Formular',
            'description' => 'Reichen Sie Ihre Daten für ein HWK-Zeugnis / Meisterbrief ein.',
            'kicker' => 'HWK-Zeugnis',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'hwk_type', 'label' => 'Art des Zeugnisses', 'required' => true, 'options' => [
                    'Meisterbrief', 'Gesellenbrief', 'Zertifizierung', 'Fortbildungszeugnis',
                ]],
                ['type' => 'text', 'name' => 'profession', 'label' => 'Beruf / Fachrichtung', 'required' => true, 'placeholder' => 'z.B. Elektromeister, Tischlermeister'],
                ['type' => 'select', 'name' => 'chamber', 'label' => 'Zuständige Kammer', 'options' => [
                    'HWK Berlin', 'HWK München', 'HWK Hamburg', 'HWK Köln', 'HWK Frankfurt', 'HWK Stuttgart', 'HWK Düsseldorf', 'Andere',
                ]],
            ], $commonContact),
        ],
        'ihk-zeugnisvorform' => [
            'title' => 'IHK Zeugnis Formular',
            'description' => 'Reichen Sie Ihre Daten für ein IHK-Zeugnis / Zertifikat ein.',
            'kicker' => 'IHK-Zeugnis',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'ihk_type', 'label' => 'Art des Zeugnisses', 'required' => true, 'options' => [
                    'Abschlusszeugnis', 'Fortbildungszeugnis', 'Prüfungszeugnis', 'Zertifikat',
                ]],
                ['type' => 'text', 'name' => 'profession', 'label' => 'Beruf / Abschluss', 'required' => true, 'placeholder' => 'z.B. Industriekaufmann, Fachwirt'],
                ['type' => 'select', 'name' => 'chamber', 'label' => 'Zuständige IHK', 'options' => [
                    'IHK Berlin', 'IHK München', 'IHK Hamburg', 'IHK Köln', 'IHK Frankfurt', 'IHK Stuttgart', 'IHK Düsseldorf', 'Andere',
                ]],
            ], $commonContact),
        ],
        'fuhrerscheinantragsformular' => [
            'title' => 'Führerschein-Antragsformular',
            'description' => 'Reichen Sie Ihre Daten für einen Führerschein-Antrag ein.',
            'kicker' => 'Führerschein',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'license_class', 'label' => 'Führerscheinklasse', 'required' => true, 'options' => [
                    'Klasse A (Motorrad)', 'Klasse A1', 'Klasse B (PKW)', 'Klasse B1', 'Klasse BE',
                    'Klasse C1', 'Klasse C (LKW)', 'Klasse CE', 'Klasse D (Bus)', 'Klasse T (Traktor)', 'Andere',
                ]],
                ['type' => 'select', 'name' => 'country', 'label' => 'Land', 'required' => true, 'options' => [
                    'Deutschland', 'Österreich', 'Schweiz', 'Niederlande', 'Andere',
                ]],
            ], $commonContact),
        ],
        'ausweisformular' => [
            'title' => 'Ausweis-Formular',
            'description' => 'Reichen Sie Ihre Daten für einen Personalausweis oder Reisepass ein.',
            'kicker' => 'Ausweis',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'id_type', 'label' => 'Art des Ausweises', 'required' => true, 'options' => [
                    'Personalausweis', 'Reisepass', 'Aufenthaltserlaubnis', 'Andere',
                ]],
                ['type' => 'text', 'name' => 'nationality', 'label' => 'Staatsangehörigkeit', 'required' => true, 'placeholder' => 'z.B. Deutsch'],
                ['type' => 'select', 'name' => 'country', 'label' => 'Ausstellungsland', 'required' => true, 'options' => [
                    'Deutschland', 'Österreich', 'Schweiz', 'Niederlande', 'Andere',
                ]],
            ], $commonContact),
        ],
        'Hochschulabschluss' => [
            'title' => 'Hochschulabschluss Formular',
            'description' => 'Reichen Sie Ihre Daten für einen Hochschulabschluss (Bachelor, Master, Diplom, Doktor) ein.',
            'kicker' => 'Hochschulabschluss',
            'fields' => array_merge([
                ['type' => 'select', 'name' => 'degree_type', 'label' => 'Art des Abschlusses', 'required' => true, 'options' => [
                    'Bachelor', 'Master', 'MBA', 'Diplom', 'Doktor / PhD', 'Staatsexamen', 'Zertifikat', 'Anderer',
                ]],
                ['type' => 'text', 'name' => 'university', 'label' => 'Universität / Hochschule', 'required' => true, 'placeholder' => 'z.B. FernUniversität Hagen, IU, FOM'],
                ['type' => 'text', 'name' => 'field_of_study', 'label' => 'Studiengang / Fachrichtung', 'required' => true, 'placeholder' => 'z.B. Wirtschaftsinformatik, Betriebswirtschaft'],
                ['type' => 'select', 'name' => 'study_mode', 'label' => 'Studienform', 'options' => [
                    'Präsenzstudium', 'Fernstudium', 'Online-Studium', 'Berufsbegleitend', 'Dual',
                ]],
                ['type' => 'select', 'name' => 'urgency', 'label' => 'Dringlichkeit', 'options' => ['Normal', 'Express (48h)', 'Sehr dringend']],
            ], $commonContact),
        ],
    ];
}

/**
 * Render a form page to HTML.
 */
function dk_render_form_page(string $slug, array $def): string
{
    $siteUrl = dk_site_url();
    $pageUrl = $siteUrl . '/' . $slug . '.html';
    $title   = $def['title'];
    $desc    = $def['description'];
    $kicker  = $def['kicker'];

    $css = file_get_contents(__DIR__ . '/lib/critical-blog.css') ?: '';

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="' . e($desc) . '">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="' . e($title) . ' | Dokuments Hub">
  <meta property="og:description" content="' . e($desc) . '">
  <meta property="og:type" content="website">
  <meta property="og:url" content="' . e($pageUrl) . '">
  <title>' . e($title) . ' | Dokuments Hub</title>
  <link rel="canonical" href="' . e($pageUrl) . '">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <meta name="theme-color" content="#000000">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <style>
' . $css . '
.dk-form-page { max-width: 680px; margin: 60px auto; padding: 0 20px; }
.dk-form-page-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 36px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.dk-form-page-card h1 { font-size: 1.6rem; color: #000; margin-bottom: 8px; }
.dk-form-page-card .dk-form-intro { font-size: .92rem; color: #666; margin-bottom: 24px; }
.dk-form-field { margin-bottom: 16px; }
.dk-form-field label { display: block; font-weight: 600; font-size: .85rem; color: #333; margin-bottom: 5px; }
.dk-form-field label .req { color: #b91c1c; }
.dk-form-field input, .dk-form-field select, .dk-form-field textarea {
  width: 100%; padding: 11px 13px; border: 1.5px solid #e0e0e0; border-radius: 8px;
  font: inherit; font-size: .95rem; background: #fafafa; box-sizing: border-box;
}
.dk-form-field input:focus, .dk-form-field select:focus, .dk-form-field textarea:focus {
  outline: none; border-color: #000; background: #fff;
}
.dk-form-row { display: flex; gap: 12px; }
.dk-form-row > div { flex: 1; }
.dk-form-success { background: #dcfce7; color: #15803d; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0; }
.dk-form-error { background: #fee2e2; color: #b91c1c; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }
.dk-form-submit { width: 100%; padding: 15px; background: linear-gradient(135deg, #1a1a1a, #000); color: #fff; border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 600; cursor: pointer; font: inherit; }
.dk-form-submit:hover { opacity: .88; }
.honeypot { position: absolute; left: -9999px; }
@media(max-width:600px) { .dk-form-row { flex-direction: column; } .dk-form-page-card { padding: 20px; } }
  </style>
  <script type="application/ld+json">
  { "@context": "https://schema.org", "@type": "WebPage", "name": "' . e($title) . '", "url": "' . e($pageUrl) . '", "isPartOf": { "@type": "WebSite", "url": "' . e($siteUrl) . '/" } }
  </script>
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
    <a href="category/universitaetsdokumente.html">Studienberatung</a>
    <a href="category/ihk-zertifikate.html">Prüfungsvorbereitung</a>
    <a href="category/sprachzertifikate.html">Sprachzertifikate</a>
    <a href="preise.html">Preise</a>
    <a href="kontakt.html">Beratung anfragen</a>
  </div></nav>
  <main id="content">';

    // ===== ACCESS CODE GATE OVERLAY (Phase 1) =====
    $html .= '
    <div id="dkGate" style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;padding:20px;font-family:Inter,sans-serif">
      <div style="max-width:400px;width:100%;background:linear-gradient(135deg,#1a1a1a,#000);border:1px solid #333;border-radius:16px;padding:40px 32px;text-align:center">
        <div style="font-size:2.5rem;margin-bottom:16px">🔒</div>
        <h2 style="color:#fff;font-size:1.3rem;margin-bottom:8px">Zugangscode erforderlich</h2>
        <p style="color:#999;font-size:.88rem;margin-bottom:24px;line-height:1.5">Dieses Formular ist durch einen Zugangscode geschützt. Bitte geben Sie den Code ein, den Sie von Ihrem Agenten erhalten haben.</p>
        <input type="text" id="dkGateCode" placeholder="z.B. ABC123" style="width:100%;padding:14px;border:2px solid #444;border-radius:10px;font-size:1.1rem;text-align:center;background:#111;color:#fff;letter-spacing:3px;font-family:monospace;box-sizing:border-box;text-transform:uppercase" maxlength="20">
        <button id="dkGateBtn" onclick="dkVerifyCode()" style="width:100%;padding:14px;margin-top:16px;background:#fff;color:#000;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .15s">Entsperren →</button>
        <p id="dkGateErr" style="color:#ef4444;font-size:.85rem;margin-top:12px;display:none"></p>
      </div>
    </div>';

    // ===== FORM CONTENT (Phase 2 — hidden until code verified) =====
    $html .= '
    <div id="dkFormWrap" style="display:none">
    <div class="dk-form-page">
      <div class="dk-form-page-card">
        <p class="section-kicker">' . e($kicker) . '</p>
        <h1>' . e($title) . '</h1>
        <p class="dk-form-intro">' . e($desc) . '</p>';

    $status = $_GET['form'] ?? '';
    if ($status === 'thanks') {
        $html .= '<div class="dk-form-success">✅ Vielen Dank! Ihre Daten wurden übermittelt. Ein Experte meldet sich in Kürze.</div>';
    } elseif ($status === 'error') {
        $html .= '<div class="dk-form-error">⚠️ ' . e($_GET['msg'] ?? 'Fehler beim Einreichen.') . '</div>';
    }

    $html .= '<form action="form-handler.php" method="POST">
          <input type="hidden" name="form_type" value="' . e($kicker) . '">
          <input type="hidden" name="form_slug" value="' . e($slug) . '">
          <input type="hidden" name="access_code" id="dkAccessCode" value="">
          <input type="hidden" name="return_url" value="' . e($slug) . '.html">
          <div class="honeypot"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>';

    foreach ($def['fields'] as $field) {
        if ($field['type'] === 'row') {
            $html .= '<div class="dk-form-field"><div class="dk-form-row">';
            foreach ($field['fields'] as $sub) {
                $html .= dk_render_form_field($sub);
            }
            $html .= '</div></div>';
        } else {
            $html .= '<div class="dk-form-field">';
            $html .= dk_render_form_field($field);
            $html .= '</div>';
        }
    }

    $html .= '
          <button type="submit" class="dk-form-submit">Formular absenden →</button>
        </form>
      </div>
    </div>
    </div>
  </main>
  <footer class="footer">
    <div class="footer-content">
      <div class="footer-contact">
        <h3>Beratung anfragen</h3>
        <div class="footer-buttons">
          <a href="kontakt.html" class="footer-btn">Anfrageformular</a>
          <a href="https://t.me/mikibucherbox" class="footer-btn footer-btn-small" target="_blank">Telegram</a>
          <a href="https://wa.me/+491791530217" class="footer-btn footer-btn-small" target="_blank">WhatsApp</a>
        </div>
      </div>
      <div class="footer-bottom"><p>&copy; ' . date('Y') . ' Dokuments Hub.</p></div>
    </div>
  </footer>
  <script src="js/chat-widget.js" defer></script>
  <script>
  // ===== Access code gate logic (self-contained) =====
  function dkVerifyCode() {
    var btn = document.getElementById("dkGateBtn");
    var input = document.getElementById("dkGateCode");
    var errP = document.getElementById("dkGateErr");
    var code = input.value.trim();
    if (!code) { errP.textContent = "Bitte Code eingeben."; errP.style.display = "block"; return; }
    btn.disabled = true; btn.textContent = "Prüfe…"; errP.style.display = "none";

    var body = new URLSearchParams();
    body.append("verify_code", "1");
    body.append("form_slug", "' . e($slug) . '");
    body.append("access_code", code);

    fetch("form-handler.php?verify_code=1", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: body.toString()
    })
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.valid) {
        document.getElementById("dkAccessCode").value = code;
        document.getElementById("dkGate").style.display = "none";
        document.getElementById("dkFormWrap").style.display = "block";
      } else {
        errP.textContent = d.error || "Ungültiger Code.";
        errP.style.display = "block";
        btn.disabled = false; btn.textContent = "Entsperren →";
      }
    })
    .catch(function(){
      errP.textContent = "Netzwerkfehler. Bitte erneut versuchen.";
      errP.style.display = "block";
      btn.disabled = false; btn.textContent = "Entsperren →";
    });
  }
  // Enter key submits code.
  document.getElementById("dkGateCode").addEventListener("keydown", function(e){
    if (e.key === "Enter") { e.preventDefault(); dkVerifyCode(); }
  });
  </script>
</body>
</html>';

    return $html;
}

function dk_render_form_field(array $f): string
{
    $name = e($f['name']);
    $label = e($f['label']);
    $req = !empty($f['required']) ? ' <span class="req">*</span>' : '';
    $required = !empty($f['required']) ? ' required' : '';
    $placeholder = isset($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
    $value = isset($f['value']) ? ' value="' . e($f['value']) . '"' : '';
    $width = isset($f['width']) ? ' style="width:' . e($f['width']) . ';flex-shrink:0"' : '';

    $out = "<div{$width}>\n";
    $out .= "  <label>{$label}{$req}</label>\n";

    switch ($f['type']) {
        case 'select':
            $out .= "  <select name=\"{$name}\"{$required}>\n";
            $out .= "    <option value=\"\">Bitte wählen…</option>\n";
            foreach ($f['options'] ?? [] as $opt) {
                $out .= "    <option value=\"" . e($opt) . "\">" . e($opt) . "</option>\n";
            }
            $out .= "  </select>\n";
            break;
        case 'textarea':
            $rows = $f['rows'] ?? 3;
            $out .= "  <textarea name=\"{$name}\" rows=\"{$rows}\"{$required}{$placeholder}></textarea>\n";
            break;
        default:
            $type = e($f['type']);
            $out .= "  <input type=\"{$type}\" name=\"{$name}\"{$required}{$placeholder}{$value}>\n";
    }

    $out .= "</div>\n";
    return $out;
}

// --- Generate all form pages ---
$forms = dk_form_definitions();
$generated = 0;

foreach ($forms as $slug => $def) {
    $html = dk_render_form_page($slug, $def);
    $dest = dk_site_root() . '/' . $slug . '.html';
    file_put_contents($dest, $html, LOCK_EX);
    echo "  ✓ {$slug}.html\n";
    $generated++;
}

echo "\n{$generated} form pages generated.\n";
