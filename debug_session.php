<?php
// debug_session.php - temporary endpoint to check if session persists
require __DIR__ . '/cors.php';
setupCORS();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['test_value'] = $_POST['value'] ?? 'test-' . time();
    echo json_encode([
        'ok' => true,
        'message' => 'Session value set',
        'session_id' => session_id(),
        'test_value' => $_SESSION['test_value'],
        'all_session' => $_SESSION
    ]);
} else {
    echo json_encode([
        'ok' => true,
        'session_id' => session_id(),
        'test_value' => $_SESSION['test_value'] ?? null,
        'all_session' => $_SESSION
    ]);
}
