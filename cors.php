<?php
function setupCORS() {

    $allowedOrigins = [
        'https://three50-frontend.onrender.com',
        'https://www.three50-frontend.onrender.com',
        'http://localhost:3000',
        'http://localhost',
    ];

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        }
    }

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Always answer OPTIONS correctly
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Sessions for cross-site cookies
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
