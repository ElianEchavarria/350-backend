<?php
// Minimal endpoint to test email delivery without placing an order
require __DIR__ . '/cors.php';
setupCORS();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

set_exception_handler(function($e) {
    setupCORS();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    setupCORS();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $errstr]);
    exit;
});

require __DIR__ . '/vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) > 1 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === '\'' && substr($v, -1) === '\''))) {
            $v = substr($v, 1, -1);
        }
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST']);
    exit;
}

$to = $_POST['to'] ?? (getenv('ADMIN_EMAIL') ?: trim(@file_get_contents(__DIR__ . '/admin_email.txt')));
if (!$to) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing recipient. Provide `to` or set ADMIN_EMAIL.']);
    exit;
}

$subject = $_POST['subject'] ?? 'Test Email';
$html = $_POST['html'] ?? '<p>This is a test email.</p>';
$text = $_POST['text'] ?? 'This is a test email.';

$sent = false;
$sendgridError = null;
$smtpError = null;

// Try SendGrid first
$sgKey = getenv('SENDGRID_API_KEY') ?: null;
if ($sgKey) {
    $payload = [
        'personalizations' => [[
            'to' => [[ 'email' => $to ]],
            'subject' => $subject,
        ]],
        'from' => [
            'email' => (getenv('SENDGRID_FROM') ?: getenv('SMTP_FROM') ?: 'no-reply@example.com'),
            'name'  => (getenv('SMTP_FROM_NAME') ?: 'Shop'),
        ],
        'content' => [
            [ 'type' => 'text/plain', 'value' => $text ],
            [ 'type' => 'text/html',  'value' => $html ],
        ],
    ];
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $sgKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $sendgridError = 'curl_error: ' . curl_error($ch);
    } elseif ($code >= 200 && $code < 300) {
        $sent = true;
    } else {
        $sendgridError = 'HTTP ' . $code . ' ' . $response;
    }
    curl_close($ch);
}

// Fallback to SMTP
if (!$sent) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER') ?: '';
        $mail->Password = getenv('SMTP_PASS') ?: '';
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
        $mail->Port = intval(getenv('SMTP_PORT') ?: 587);
        $mail->Timeout = intval(getenv('SMTP_TIMEOUT') ?: 8);
        $mail->SMTPKeepAlive = false;
        $mail->SMTPDebug = 0;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        $from = getenv('SMTP_FROM') ?: $mail->Username ?: 'no-reply@example.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'Shop';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        $mail->send();
        $sent = true;
    } catch (Exception $e) {
        $sent = false;
        $smtpError = $mail->ErrorInfo ?: $e->getMessage();
    }
}

$resp = ['ok' => true, 'email_sent' => (bool)$sent, 'to' => $to, 'provider' => $sgKey ? 'sendgrid' : 'smtp'];
if (!$sent) {
    if ($sendgridError) $resp['sendgrid_error'] = $sendgridError;
    if ($smtpError) $resp['smtp_error'] = $smtpError;
}
echo json_encode($resp);
