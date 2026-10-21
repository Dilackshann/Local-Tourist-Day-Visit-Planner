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

require_once __DIR__ . '/../config/database.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!is_string($username) || !is_string($password)) {
    http_response_code(400);
    exit('Invalid input.');
}

$username = trim($username);

if ($username === '' || $password === '' || strlen($username) > 50) {
    http_response_code(400);
    exit('Please enter a valid username and password.');
}

$stmt = $pdo->prepare(
    'SELECT admin_id, username, password_hash
     FROM administrators
     WHERE username = :username
     LIMIT 1'
);

$stmt->execute(['username' => $username]);

$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    http_response_code(401);
    exit('Invalid username or password. Go back and try again.');
}

session_regenerate_id(true);

$_SESSION['admin_id'] = (int) $admin['admin_id'];

header('Location: ../admin-dashboard.php');
exit;