<?php
// logout.php - POST to clear session
require __DIR__ . '/cors.php';
setupCORS();
header('Content-Type: application/json');
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
echo json_encode(['ok' => true]);
