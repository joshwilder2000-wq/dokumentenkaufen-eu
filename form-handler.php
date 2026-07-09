<?php
/**
 * Form submission handler + access code verification.
 *
 * Modes:
 *   GET/POST ?verify_code=1 → validate access code for a form slug → JSON
 *   POST (normal)           → validate code → store submission → email + Telegram
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// --- Mode: verify access code ---
if (($_GET['verify_code'] ?? '') === '1') {
    $formSlug  = trim((string)($_REQUEST['form_slug'] ?? ''));
    $codeInput = trim((string)($_REQUEST['access_code'] ?? ''));

    if ($formSlug === '' || $codeInput === '') {
        echo json_encode(['valid' => false, 'error' => 'Bitte Formular und Code angeben.']);
        exit;
    }

    $valid = dk_validate_form_access_code($formSlug, $codeInput);
    if ($valid === true) {
        echo json_encode(['valid' => true]);
    } else {
        echo json_encode(['valid' => false, 'error' => $valid]);
    }
    exit;
}

// --- Mode: normal POST submission ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Honeypot.
if (!empty($_POST['website'])) {
    header('Location: index.html?form=thanks');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$formType  = trim((string)($_POST['form_type'] ?? ''));
$formSlug  = trim((string)($_POST['form_slug'] ?? ''));
$accessCode = trim((string)($_POST['access_code'] ?? ''));
$returnUrl = trim((string)($_POST['return_url'] ?? ''));
if ($returnUrl === '') {
    $returnUrl = 'index.html';
}

// Validate access code server-side.
if ($formSlug !== '' && $accessCode !== '') {
    $codeCheck = dk_validate_form_access_code($formSlug, $accessCode);
    if ($codeCheck !== true) {
        header('Location: ' . $returnUrl . '?form=error&msg=' . rawurlencode('Ungültiger oder abgelaufener Zugangscode: ' . $codeCheck));
        exit;
    }
    // Increment usage count.
    dk_increment_code_usage($formSlug, $accessCode);
}

// Collect all form fields.
$systemFields = ['form_type', 'form_slug', 'access_code', 'return_url', 'website', 'company_url', 'csrf_token', 'submit'];
$data = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, $systemFields, true)) continue;
    if (is_array($value)) {
        $data[$key] = implode(', ', array_map('strval', $value));
    } else {
        $data[$key] = trim((string)$value);
    }
}

$name = $data['name'] ?? $data['full_name'] ?? $data['applicant_name'] ?? '';
$email = $data['email'] ?? '';
$whatsapp = ($data['whatsapp_cc'] ?? '') . ' ' . ($data['whatsapp_number'] ?? '');

if ($name === '') {
    header('Location: ' . $returnUrl . '?form=error&msg=' . rawurlencode('Bitte Ihren Namen angeben.'));
    exit;
}

// Store in DB.
dk_db()->prepare(
    'INSERT INTO form_submissions (form_type, form_data, visitor_name, visitor_email, visitor_whatsapp, status)
     VALUES (?,?,?,?,?,"new")'
)->execute([
    $formType,
    json_encode($data, JSON_UNESCAPED_UNICODE),
    $name,
    $email,
    trim($whatsapp),
]);

// Email admin.
$subject = "Neue Formular-Einreichung: {$formType}";
$body = "Formular: {$formType}\n\n";
foreach ($data as $k => $v) {
    $body .= ucfirst(str_replace('_', ' ', $k)) . ": {$v}\n";
}
$body .= "\nEingereicht: " . date('d.m.Y H:i') . "\n";
$headers = "From: noreply@dokumentenkaufen.eu\r\nContent-Type: text/plain; charset=UTF-8\r\n";
@mail('leitung@akademischergrad.de', $subject, $body, $headers);

// Forward to Telegram.
$tgText = "📋 <b>Neues Formular: {$formType}</b>\n\n";
foreach ($data as $k => $v) {
    if ($v !== '') {
        $tgText .= "• <b>" . ucfirst(str_replace('_', ' ', $k)) . ":</b> " . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . "\n";
    }
}
dk_send_telegram($tgText);

header('Location: ' . $returnUrl . '?form=thanks');
exit;

// ===== Helper functions =====

/**
 * Validate an access code for a form slug.
 * @return true|string  true if valid, or error message string.
 */
function dk_validate_form_access_code(string $formSlug, string $code): bool|string
{
    $stmt = dk_db()->prepare(
        "SELECT * FROM form_access_codes
         WHERE form_slug = ? AND access_code = ? AND is_active = 1
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$formSlug, $code]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'Code nicht gefunden oder deaktiviert.';
    }
    if ((int)$row['uses_count'] >= (int)$row['max_uses']) {
        return 'Code vollständig genutzt (' . $row['max_uses'] . 'x).';
    }
    if ($row['expires_at'] !== '' && strtotime($row['expires_at']) < time()) {
        return 'Code abgelaufen.';
    }
    return true;
}

/**
 * Increment the usage count of an access code.
 */
function dk_increment_code_usage(string $formSlug, string $code): void
{
    dk_db()->prepare(
        "UPDATE form_access_codes SET uses_count = uses_count + 1
         WHERE form_slug = ? AND access_code = ? AND is_active = 1"
    )->execute([$formSlug, $code]);
}
