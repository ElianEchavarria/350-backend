<?php
// add_to_cart.php - stores cart in session
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
