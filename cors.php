<?php
// cors.php - centralized CORS handler and session setup for all endpoints
function setupCORS() {
    // Detect if we're on HTTPS (check both direct and reverse proxy)
    $isHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    
    // SEND CORS HEADERS FIRST before anything else
    // For debugging / deployment, echo back the incoming Origin so browsers receive CORS headers.
    // NOTE: This allows any origin that sends a request — restrict to specific origins for production.
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    // Handle OPTIONS preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // NOW configure session for cross-origin use BEFORE starting session
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'  // More permissive than None, works better with credentials
        ]);
    } else {
        // Fallback for older PHP versions
        ini_set('session.cookie_secure', $isHttps ? 1 : 0);
        ini_set('session.cookie_httponly', 1);
    }
    
    // Start session FIRST before any output
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();  // Suppress warnings if session already started
    }
}
?>
    
    $allowedOrigins = [
        // Local development
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5000',
        'http://localhost:8000',
        'http://localhost:5173',
        'file://',
        'null', // some dev tools
        
        // Production frontend
        'https://350-frontend-14fwamqln-elianechavarrias-projects.vercel.app',
        'https://350-frontend-55ix6qa0c-elianechavarrias-projects.vercel.app',
    ];
    
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        
        // Check if origin matches allowed list or localhost pattern
        $isAllowed = false;
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === 'file://' && strpos($origin, 'file://') === 0) {
                $isAllowed = true;
                break;
            } elseif (preg_match('#^https?://localhost(:[0-9]+)?$#', $allowed) && preg_match('#^https?://localhost(:[0-9]+)?$#', $origin)) {
                $isAllowed = true;
                break;
            } elseif ($origin === $allowed) {
                $isAllowed = true;
                break;
            }
        }
        
        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
    }
    
    // Handle OPTIONS preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>
    
    $allowedOrigins = [
        // Local development
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5000',
        'http://localhost:8000',
        'http://localhost:5173',
        'file://',
        'null', // some dev tools
        
        // Production frontend
        'https://350-frontend-14fwamqln-elianechavarrias-projects.vercel.app',
        'https://350-frontend-55ix6qa0c-elianechavarrias-projects.vercel.app',
    ];
    
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        
        // Check if origin matches allowed list or localhost pattern
        $isAllowed = false;
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === 'file://' && strpos($origin, 'file://') === 0) {
                $isAllowed = true;
                break;
            } elseif (preg_match('#^https?://localhost(:[0-9]+)?$#', $allowed) && preg_match('#^https?://localhost(:[0-9]+)?$#', $origin)) {
                $isAllowed = true;
                break;
            } elseif ($origin === $allowed) {
                $isAllowed = true;
                break;
            }
        }
        
        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
    }
    
    // Handle OPTIONS preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>
