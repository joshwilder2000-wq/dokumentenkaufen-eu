#!/usr/bin/env php
<?php
/**
 * Telegram Bot Setup Diagnostic.
 *
 * Run from cPanel Terminal: php admin/telegram-setup.php
 *
 * Shows every step with raw output so we can see exactly what's happening.
 */

echo "==============================================\n";
echo "  Telegram Bot Setup Diagnostic\n";
echo "==============================================\n\n";

require_once __DIR__ . '/lib/helpers.php';

$token = dk_setting('telegram_bot_token', '');
echo "1. Stored bot token: " . ($token ? substr($token, 0, 25) . '...' : '(NOT SET)') . "\n";

if (!$token) {
    echo "   ⚠️ No token stored. Setting it now...\n";
    dk_set_setting('telegram_bot_token', '8766213858:AAGPFq26a17N6bLGAVu-L_nXGmAGbUvV7oo');
    $token = '8766213858:AAGPFq26a17N6bLGAVu-L_nXGmAGbUvV7oo';
    echo "   ✅ Token stored.\n";
}

echo "\n2. Testing connection to Telegram API...\n";
echo "   Connecting to: https://api.telegram.org/bot{$token}/getMe\n";

// Try cURL first (more reliable than file_get_contents on shared hosting).
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.telegram.org/bot{$token}/getMe",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$rawResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "   HTTP code: {$httpCode}\n";
if ($curlError) {
    echo "   cURL error: {$curlError}\n";
}
echo "   Raw response: " . substr((string)$rawResponse, 0, 500) . "\n";

$meData = json_decode((string)$rawResponse, true);
if (!empty($meData['ok'])) {
    echo "   ✅ Bot valid: @{$meData['result']['username']} ({$meData['result']['first_name']})\n";
} else {
    echo "   ❌ Bot validation failed.\n";
    echo "\n   If the connection timed out, the server may block outbound HTTPS.\n";
    echo "   Try: php -r 'echo file_get_contents(\"https://api.telegram.org\");'\n";
}

echo "\n3. Checking for messages sent to the bot (getUpdates)...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.telegram.org/bot{$token}/getUpdates",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$updatesRaw = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError2 = curl_error($ch);
curl_close($ch);

echo "   HTTP code: {$httpCode2}\n";
if ($curlError2) {
    echo "   cURL error: {$curlError2}\n";
}

$updatesData = json_decode((string)$updatesRaw, true);
$updateCount = !empty($updatesData['result']) ? count($updatesData['result']) : 0;
echo "   Updates found: {$updateCount}\n";

if ($updateCount > 0) {
    echo "\n4. Extracting chat_id...\n";
    foreach ($updatesData['result'] as $update) {
        $msg = $update['message'] ?? $update['edited_message'] ?? [];
        $cid = $msg['chat']['id'] ?? null;
        if ($cid) {
            dk_set_setting('telegram_chat_id', (string)$cid);
            $fromUser = ($msg['from']['first_name'] ?? '') . ' @' . ($msg['from']['username'] ?? '');
            echo "   ✅ Chat ID found: {$cid}\n";
            echo "   From: {$fromUser}\n";
            break;
        }
    }
} else {
    echo "\n4. ⚠️ No messages found.\n";
    echo "   You must open your bot in Telegram and send it a message first.\n";
    echo "   Steps:\n";
    echo "     a) Open Telegram\n";
    echo "     b) Search for your bot's username (the one from @BotFather)\n";
    echo "     c) Tap START\n";
    echo "     d) Send the message: hi\n";
    echo "     e) Then re-run this script: php admin/telegram-setup.php\n";
}

echo "\n5. Sending test message...\n";
$chatId = dk_setting('telegram_chat_id', '');
if ($chatId) {
    $testText = "✅ Telegram-Bot verbunden!\n\nLive-Chat ist jetzt aktiv. Besucher-Nachrichten werden hier empfangen.";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.telegram.org/bot{$token}/sendMessage",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chatId,
            'text' => $testText,
            'parse_mode' => 'HTML',
        ]),
    ]);
    $sendRaw = curl_exec($ch);
    $httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError3 = curl_error($ch);
    curl_close($ch);

    echo "   HTTP code: {$httpCode3}\n";
    if ($curlError3) {
        echo "   cURL error: {$curlError3}\n";
    }

    $sendData = json_decode((string)$sendRaw, true);
    if (!empty($sendData['ok'])) {
        echo "   ✅ Test message sent successfully!\n";
        echo "   Message ID: " . $sendData['result']['message_id'] . "\n";
    } else {
        echo "   ❌ Send failed: " . substr((string)$sendRaw, 0, 300) . "\n";
    }
} else {
    echo "   ⏭️ Skipped (no chat_id yet).\n";
}

echo "\n==============================================\n";
echo "  Summary\n";
echo "==============================================\n";
echo "  Token:    " . ($token ? '✅ Stored' : '❌ Missing') . "\n";
echo "  Chat ID:  " . ($chatId ?: '❌ Not set') . "\n";
echo "  Bot:      " . (!empty($meData['result']['username']) ? '@' . $meData['result']['username'] : '⚠️ Unknown') . "\n";
echo "  Messages: {$updateCount} received\n";
echo "  Test msg: " . (!empty($sendData['ok']) ? '✅ Sent' : '❌ Not sent') . "\n";

// --- Set webhook so Telegram forwards replies to our PHP endpoint ---
echo "\n6. Setting webhook for bidirectional replies...\n";
$webhookUrl = dk_site_url() . '/telegram-webhook.php';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.telegram.org/bot{$token}/setWebhook",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['url' => $webhookUrl]),
]);
$whRaw = curl_exec($ch);
$whCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$whData = json_decode((string)$whRaw, true);
if (!empty($whData['ok'])) {
    echo "   ✅ Webhook set to: {$webhookUrl}\n";
    echo "   Admin can now reply in Telegram → reply flows to visitor.\n";
} else {
    echo "   ⚠️ Webhook setup failed (HTTP {$whCode}): " . substr((string)$whRaw, 0, 200) . "\n";
}
echo "==============================================\n";
