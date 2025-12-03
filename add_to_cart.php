<?php
// add_to_cart.php - stores cart in session
require __DIR__ . '/cors.php';
setupCORS();
header('Content-Type: application/json');
session_start();
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

echo json_encode(['ok' => true, 'cart' => $_SESSION['cart']]);
