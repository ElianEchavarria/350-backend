<?php
// update_cart.php - update or remove items from session cart
require __DIR__ . '/cors.php';
setupCORS();
header('Content-Type: application/json');
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST']);
    exit;
}

$action = $_POST['action'] ?? '';
$productId = $_POST['product_id'] ?? null;
$qty = isset($_POST['qty']) ? intval($_POST['qty']) : null;

if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($action === 'remove') {
    unset($_SESSION['cart'][$productId]);
} elseif ($action === 'set' && $qty !== null) {
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'invalid action']);
    exit;
}

// return updated cart (reuse get logic)
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

