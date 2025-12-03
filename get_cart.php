<?php
// get_cart.php - returns session cart with product details
require __DIR__ . '/cors.php';
setupCORS();
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







