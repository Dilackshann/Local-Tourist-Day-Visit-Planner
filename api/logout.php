<?php

session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin-login.html');
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ]
    );
}

session_destroy();

header('Location: ../admin-login.html');
exit;