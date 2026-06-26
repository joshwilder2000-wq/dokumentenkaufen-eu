<?php
/**
 * Akademischer Grad - Secure consultation mail handler.
 * Handles lawful consultation, review, and general contact submissions.
 */

define('ADMIN_EMAIL', 'leitung@akademischergrad.de');
define('SITE_URL', 'https://dokumentenkaufen.eu');
define('VALID_ORDER_CODE', '011094');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Direct access not allowed');
}

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }

    $data = trim((string) $data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sendEmail($to, $subject, $message, $replyTo = '') {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: noreply@dokumentenkaufen.eu\r\n";

    if (!empty($replyTo) && isValidEmail($replyTo)) {
        $headers .= "Reply-To: $replyTo\r\n";
    }

    $headers .= "X-Mailer: PHP/" . phpversion();
    return mail($to, $subject, $message, $headers);
}

function logSubmission($type, $data, $success) {
    $logFile = 'submissions.log';
    if (!file_exists($logFile) || !is_writable($logFile)) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[$timestamp] [$status] [$type] [IP: $ip] " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function respond($success, $message, $redirect = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

function selectedChannels() {
    $raw = $_POST['communication_channels'] ?? $_POST['kontaktkanal'] ?? [];
    if (!is_array($raw)) {
        $raw = [$raw];
    }

    $allowed = ['whatsapp', 'telegram', 'email'];
    return array_values(array_unique(array_filter(array_map(function ($channel) use ($allowed) {
        $channel = strtolower(sanitize($channel));
        return in_array($channel, $allowed, true) ? $channel : '';
    }, $raw))));
}

function collectConsultationData() {
    $studentName = sanitize($_POST['student_name'] ?? $_POST['name'] ?? $_POST['Name'] ?? '');
    $programDetails = sanitize($_POST['program_details'] ?? $_POST['details'] ?? $_POST['Bewertungstext'] ?? '');
    $serviceArea = sanitize($_POST['service_area'] ?? $_POST['document'] ?? $_POST['dokument'] ?? $_POST['Dokument'] ?? 'Allgemeine Studienberatung');
    $channels = selectedChannels();

    $contact = [
        'email' => sanitize($_POST['contact_email'] ?? $_POST['email'] ?? $_POST['kontakt_email'] ?? ''),
        'telegram' => sanitize($_POST['telegram_username'] ?? ''),
        'whatsapp_country_code' => sanitize($_POST['whatsapp_country_code'] ?? ''),
        'whatsapp_number' => sanitize($_POST['whatsapp_number'] ?? $_POST['telefon'] ?? '')
    ];

    $validChannelCount = 0;
    $channelLines = [];

    if (in_array('email', $channels, true) && isValidEmail($contact['email'])) {
        $validChannelCount++;
        $channelLines[] = "E-Mail: " . $contact['email'];
    }

    if (in_array('telegram', $channels, true) && !empty($contact['telegram'])) {
        $validChannelCount++;
        $channelLines[] = "Telegram: " . $contact['telegram'];
    }

    if (
        in_array('whatsapp', $channels, true)
        && !empty($contact['whatsapp_country_code'])
        && !empty($contact['whatsapp_number'])
    ) {
        $validChannelCount++;
        $channelLines[] = "WhatsApp: " . $contact['whatsapp_country_code'] . ' ' . $contact['whatsapp_number'];
    }

    return [
        'student_name' => $studentName,
        'program_details' => $programDetails,
        'service_area' => $serviceArea,
        'channels' => $channels,
        'valid_channel_count' => $validChannelCount,
        'channel_lines' => $channelLines,
        'contact' => $contact
    ];
}

function validateConsultation($data) {
    if (empty($data['student_name']) || empty($data['program_details']) || empty($data['service_area'])) {
        return 'Bitte geben Sie Ihren Namen, den Beratungsbereich und die Details zu Studium, Abschluss oder Zertifikat an.';
    }

    if (count($data['channels']) < 2 || $data['valid_channel_count'] < 2) {
        return 'Bitte wählen Sie mindestens zwei Kontaktkanäle und füllen Sie die zugehörigen Kontaktdaten aus.';
    }

    if (in_array('email', $data['channels'], true) && !isValidEmail($data['contact']['email'])) {
        return 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
    }

    return '';
}

if (!empty($_POST['website']) || !empty($_POST['company_url'] ?? '')) {
    respond(true, 'Vielen Dank! Ihre Anfrage wurde gesendet.', 'danke.html');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'last_submit_' . md5($ip);
$now = time();

if (isset($_SESSION[$rateLimitKey]) && ($now - $_SESSION[$rateLimitKey]) < 60) {
    respond(false, 'Bitte warten Sie eine Minute vor dem nächsten Versand.');
}

$formType = sanitize($_POST['form_type'] ?? 'contact');

try {
    if ($formType === 'review') {
        $orderCode = sanitize($_POST['Bestellcode'] ?? '');
        $name = sanitize($_POST['Name'] ?? '');
        $topic = sanitize($_POST['Dokument'] ?? 'Beratung');
        $rating = intval($_POST['Bewertung'] ?? 0);
        $reviewText = sanitize($_POST['Bewertungstext'] ?? '');
        $date = sanitize($_POST['Datum'] ?? date('Y-m-d'));

        if ($orderCode !== VALID_ORDER_CODE) {
            respond(false, 'Ungültiger Bestellcode. Bitte überprüfen Sie Ihre Eingabe.');
        }

        if (empty($name) || empty($topic) || empty($reviewText) || $rating < 1 || $rating > 5) {
            respond(false, 'Bitte füllen Sie alle Pflichtfelder aus.');
        }

        $subject = "Neue Beratungsbewertung von $name";
        $message = "NEUE BEWERTUNG EINGEGANGEN\n";
        $message .= "========================\n\n";
        $message .= "Name: $name\n";
        $message .= "Beratungsbereich: $topic\n";
        $message .= "Bewertung: $rating von 5 Sternen\n";
        $message .= "Datum: $date\n";
        $message .= "Bestellcode: $orderCode\n\n";
        $message .= "Bewertungstext:\n$reviewText\n\n";
        $message .= "Gesendet am: " . date('d.m.Y H:i:s') . "\n";
        $message .= "IP-Adresse: $ip\n";

        if (!sendEmail(ADMIN_EMAIL, $subject, $message)) {
            throw new Exception('E-Mail konnte nicht gesendet werden.');
        }

        $_SESSION[$rateLimitKey] = $now;
        logSubmission('review', ['name' => $name, 'topic' => $topic], true);
        respond(true, 'Vielen Dank für Ihre Bewertung! Wir haben sie erhalten.', 'danke.html?type=review');
    }

    $data = collectConsultationData();
    $validationError = validateConsultation($data);
    if (!empty($validationError)) {
        respond(false, $validationError);
    }

    $replyTo = isValidEmail($data['contact']['email']) ? $data['contact']['email'] : '';
    $subject = "Neue Beratungsanfrage von " . $data['student_name'];
    $message = "NEUE BERATUNGSANFRAGE\n";
    $message .= "=====================\n\n";
    $message .= "Student/in: " . $data['student_name'] . "\n";
    $message .= "Beratungsbereich: " . $data['service_area'] . "\n\n";
    $message .= "Kontaktkanäle:\n";
    $message .= implode("\n", $data['channel_lines']) . "\n\n";
    $message .= "Details zu Studium, Abschluss oder Zertifikat:\n";
    $message .= "---------------------------------------------\n";
    $message .= $data['program_details'] . "\n\n";
    $message .= "Hinweis: Diese Anfrage ist als rechtmäßige Beratung, Vorbereitung, Antragshilfe, Anerkennungsberatung, Ersatzbeschaffung für bereits erworbene Nachweise oder Agentenvermittlung zu behandeln.\n\n";
    $message .= "Gesendet am: " . date('d.m.Y H:i:s') . "\n";
    $message .= "IP-Adresse: $ip\n";

    if (!sendEmail(ADMIN_EMAIL, $subject, $message, $replyTo)) {
        throw new Exception('E-Mail konnte nicht gesendet werden.');
    }

    $_SESSION[$rateLimitKey] = $now;
    logSubmission($formType, [
        'student_name' => $data['student_name'],
        'service_area' => $data['service_area'],
        'channels' => $data['channels']
    ], true);

    respond(true, 'Vielen Dank! Ihre Beratungsanfrage wurde erfolgreich gesendet.', 'danke.html?type=consultation');
} catch (Exception $e) {
    logSubmission($formType, $_POST, false);
    respond(false, 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
}
