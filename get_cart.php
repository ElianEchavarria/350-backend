<?php
// get_cart.php - returns session cart with product details
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

try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    foreach ($cart as $productId => $qty) {
        $stmt = $db->prepare('SELECT id,name,category,price,stock,image FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $p['qty'] = intval($qty);
            $items[] = $p;
        }
    }

    echo json_encode(['ok' => true, 'cart' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}







