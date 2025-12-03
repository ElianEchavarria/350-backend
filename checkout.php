<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // Composer autoload
require __DIR__ . '/cors.php';

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
setupCORS();
header('Content-Type: application/json');
session_start();
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

    // send email to admin (MAIL_TO env or MAIL_TO in file)
    $admin = getenv('ADMIN_EMAIL') ?: trim(file_get_contents(__DIR__ . '/admin_email.txt') ?? "");
    $subject = sprintf("%s ordered items, total $%0.2f", $name, $total/100);
    $body = "$name ordered the following items:\n";
    foreach ($items as $it) {
        $body .= sprintf("- %s x%d — $%0.2f\n", $it['name'], $it['qty'], $it['price']/100);
    }
    $body .= sprintf("Total: $%0.2f\n", $total/100);


$sent = false;

if ($admin) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER') ?: 'elianstanly431@gmail.com';
        $mail->Password = getenv('SMTP_PASS') ?: 'pvdpbhcdqxejmzwo';
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
        $mail->Port = intval(getenv('SMTP_PORT') ?: 587);

        // FROM must match SMTP username (or use SMTP_FROM)
        $from = getenv('SMTP_FROM') ?: $mail->Username;
        $fromName = getenv('SMTP_FROM_NAME') ?: 'Shop';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($admin); // recipient
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $sent = true;
    } catch (Exception $e) {
        $sent = false;
        error_log("PHPMailer error: " . $mail->ErrorInfo); // see the real error
    }
}



    // fallback: write to file
    $log = __DIR__ . '/orders.log';
    $entry = date('c') . " | " . $name . " | " . json_encode(['items' => $items, 'total' => $total]) . "\n";
    file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);

    // clear cart
    $_SESSION['cart'] = [];

    echo json_encode(['ok' => true, 'email_sent' => (bool)$sent, 'admin' => $admin, 'order_total' => $total]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
