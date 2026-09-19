<?php
// CLI-only worker: sends the "account created" welcome email out-of-band so the
// admin_actions.php request that creates the account doesn't block on a slow
// synchronous SMTP handshake (observed to take 5s+ to Gmail from this host).
// Launched detached via Security::dispatchBackgroundEmail(), never web-accessible
// as a normal request (php_sapi_name() guard below).

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

$payloadFile = $argv[1] ?? '';
if (empty($payloadFile) || !is_file($payloadFile)) {
    exit(1);
}

$raw = file_get_contents($payloadFile);
@unlink($payloadFile);

$data = json_decode($raw, true);
if (!is_array($data)) {
    exit(1);
}

require_once __DIR__ . '/mailer.php';

GymBrosMailer::sendAccountCreatedEmail(
    $data['email'] ?? '',
    $data['recipientName'] ?? '',
    $data['username'] ?? '',
    $data['tempPassword'] ?? '',
    $data['roleLabel'] ?? 'Member'
);
