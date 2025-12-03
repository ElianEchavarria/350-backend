<?php
// Ensure CORS headers are sent before anything else
require __DIR__ . '/cors.php';
setupCORS();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Robust error/exception handlers that still return JSON with CORS
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

require __DIR__ . '/vendor/autoload.php'; // Composer autoload

// Load environment variables from .env (simple parser)
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

// checkout.php - process session cart, update inventory, send email
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST']);
    exit;
}

// require a logged-in user
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$name = trim($_POST['name'] ?? ($_SESSION['user']['username'] ?? 'Guest'));
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty']);
    exit;
}

$db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// start transaction
$db->beginTransaction();
try {
    $total = 0;
    $items = [];
    foreach ($cart as $productId => $qty) {
        $stmt = $db->prepare('SELECT id,name,price,stock FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new Exception('Product not found: ' . $productId);
        if ($p['stock'] < $qty) throw new Exception('Out of stock: ' . $p['name']);
        $newStock = $p['stock'] - $qty;
        $up = $db->prepare('UPDATE products SET stock = :s WHERE id = :id');
        $up->execute([':s' => $newStock, ':id' => $productId]);
        $items[] = ['id' => $p['id'], 'name' => $p['name'], 'qty' => $qty, 'price' => $p['price']];
        $total += intval($p['price']) * intval($qty);
    }

    // write order
    $ins = $db->prepare('INSERT INTO orders (user_id, customer_name, items, total) VALUES (:uid,:name,:items,:total)');
    $ins->execute([':uid' => $_SESSION['user']['id'], ':name' => $name, ':items' => json_encode($items), ':total' => $total]);
    $db->commit();

    // send email to admin (ADMIN_EMAIL from env or admin_email.txt)
    $admin = getenv('ADMIN_EMAIL') ?: trim(file_get_contents(__DIR__ . '/admin_email.txt') ?? "");
    $subject = sprintf("%s ordered items, total $%0.2f", $name, $total/100);
    $bodyHtml = sprintf('<p><strong>%s</strong> ordered the following items:</p><ul>', htmlspecialchars($name));
    $bodyText = "$name ordered the following items:\n";
    foreach ($items as $it) {
        $bodyHtml .= sprintf('<li>%s x%d — $%0.2f</li>', htmlspecialchars($it['name']), $it['qty'], $it['price']/100);
        $bodyText .= sprintf("- %s x%d — $%0.2f\n", $it['name'], $it['qty'], $it['price']/100);
    }
    $bodyHtml .= '</ul>' . sprintf('<p><strong>Total:</strong> $%0.2f</p>', $total/100);
    $bodyText .= sprintf("Total: $%0.2f\n", $total/100);

    $sent = false;
    $smtpError = null;
    $sendgridError = null;

    if ($admin) {
        // Prefer SendGrid HTTPS API if available (Render blocks SMTP egress)
        $sgKey = getenv('SENDGRID_API_KEY') ?: null;
        if ($sgKey) {
            $payload = [
                'personalizations' => [[
                    'to' => [[ 'email' => $admin ]],
                    'subject' => $subject,
                ]],
                'from' => [
                    'email' => (getenv('SENDGRID_FROM') ?: getenv('SMTP_FROM') ?: 'no-reply@example.com'),
                    'name'  => (getenv('SMTP_FROM_NAME') ?: 'Shop'),
                ],
                'content' => [
                    [ 'type' => 'text/plain', 'value' => $bodyText ],
                    [ 'type' => 'text/html',  'value' => $bodyHtml ],
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
            if (!$sent && $sendgridError) {
                error_log('SendGrid error: ' . $sendgridError);
            }
        }

        // Fallback to SMTP if SendGrid not configured or failed
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

                $from = getenv('SMTP_FROM') ?: $mail->Username;
                $fromName = getenv('SMTP_FROM_NAME') ?: 'Shop';
                $mail->setFrom($from, $fromName);
                $mail->addAddress($admin);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $bodyHtml;
                $mail->AltBody = $bodyText;

                $mail->send();
                $sent = true;
            } catch (Exception $e) {
                $sent = false;
                $smtpError = $mail->ErrorInfo ?: $e->getMessage();
                error_log('PHPMailer error: ' . $smtpError);
            }
        }
    }



    // fallback: write to file
    $log = __DIR__ . '/orders.log';
    $entry = date('c') . " | " . $name . " | " . json_encode(['items' => $items, 'total' => $total]) . "\n";
    file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);

    // clear cart
    $_SESSION['cart'] = [];

    $resp = ['ok' => true, 'email_sent' => (bool)$sent, 'admin' => $admin, 'order_total' => $total];
    if (!$sent) {
        if (isset($sendgridError)) $resp['sendgrid_error'] = $sendgridError;
        if (isset($smtpError)) $resp['smtp_error'] = $smtpError;
        if (!isset($resp['sendgrid_error']) && !isset($resp['smtp_error'])) $resp['error'] = 'send failed';
    }
    echo json_encode($resp);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
