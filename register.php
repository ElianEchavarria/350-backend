<?php
// register.php - accepts POST username & password
// Add CORS support for local frontend during development
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    // allow localhost origins and file:// (some dev setups)
    if (preg_match('#^https?://localhost(:[0-9]+)?$#', $origin) || strpos($origin, 'file://') === 0 || $origin === 'null') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // CORS preflight
    http_response_code(200);
    exit;
}
header('Content-Type: application/json');
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

$db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $stmt = $db->prepare('INSERT INTO users (username,password_hash) VALUES (:u,:p)');
    $stmt->execute([':u' => $username, ':p' => $hash]);
    $_SESSION['user'] = ['id' => $db->lastInsertId(), 'username' => $username];
    echo json_encode(['ok' => true, 'message' => 'Registered', 'user' => $_SESSION['user']]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'UNIQUE') !== false) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already exists']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'DB error']);
    }
}
