<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // Composer autoload

// checkout.php - process session cart, update inventory, send email (mail() fallback)
// Add CORS support for local frontend during development
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (preg_match('#^https?://localhost(:[0-9]+)?$#', $origin) || strpos($origin, 'file://') === 0 || $origin === 'null') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
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
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elianstanly431@gmail.com'; // SMTP account
        $mail->Password = 'pvdpbhcdqxejmzwo';          // App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // FROM must match SMTP username
        $mail->setFrom('elianstanly431@gmail.com', 'Shop');
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
