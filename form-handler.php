<?php
/**
 * Form submission handler for dedicated form pages.
 *
 * Stores submissions in form_submissions table, emails admin,
 * and forwards to Telegram. Redirects back to the form with a success message.
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Honeypot.
if (!empty($_POST['website'])) {
    header('Location: index.html?form=thanks');
    exit;
}

$formType = trim((string)($_POST['form_type'] ?? ''));
$returnUrl = trim((string)($_POST['return_url'] ?? ''));
if ($returnUrl === '') {
    $returnUrl = 'index.html';
}

// Collect all form fields (excluding system fields).
$systemFields = ['form_type', 'return_url', 'website', 'company_url', 'csrf_token', 'submit'];
$data = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, $systemFields, true)) continue;
    if (is_array($value)) {
        $data[$key] = implode(', ', array_map('strval', $value));
    } else {
        $data[$key] = trim((string)$value);
    }
}

// Extract common fields for indexing.
$name = $data['name'] ?? $data['full_name'] ?? $data['applicant_name'] ?? '';
$email = $data['email'] ?? '';
$whatsapp = ($data['whatsapp_cc'] ?? '') . ' ' . ($data['whatsapp_number'] ?? '');

// Validate minimum.
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
