<?php
// add_to_cart.php - stores cart in session and returns enriched cart data
require __DIR__ . '/cors.php';
setupCORS();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST']);
    exit;
}

$productId = $_POST['product_id'] ?? null;
$qty = intval($_POST['qty'] ?? 1);
if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['cart'][$productId])) $_SESSION['cart'][$productId] = 0;
$_SESSION['cart'][$productId] += max(1, $qty);

// Fetch enriched cart data with product details (same as get_cart.php)
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    foreach ($cart as $pid => $q) {
        $stmt = $db->prepare('SELECT id,name,category,price,stock,image FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $p['qty'] = intval($q);
            $items[] = $p;
        }
    }

    echo json_encode(['ok' => true, 'cart' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
