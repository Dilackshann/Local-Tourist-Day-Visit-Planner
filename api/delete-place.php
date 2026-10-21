<?php

session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Cache-Control: no-store');

// Only logged-in administrators can delete places.
if (empty($_SESSION['admin_id'])) {
    header('Location: ../admin-login.html');
    exit;
}

// Opening this URL directly must not delete anything.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../manage-places.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['delete_place_csrf'] ?? '';

if (
    !is_string($token) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    http_response_code(403);
    exit('Form verification failed. Reload Manage Places and try again.');
}

$placeId = filter_input(
    INPUT_POST,
    'place_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$placeId) {
    http_response_code(400);
    exit('Invalid place ID.');
}

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->prepare(
        'DELETE FROM places WHERE place_id = :id'
    );

    $stmt->execute(['id' => $placeId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        exit('This place no longer exists. Return to Manage Places.');
    }
} catch (PDOException $e) {
    error_log('Delete place failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to delete this place. It may be linked to other records.');
}

header('Location: ../manage-places.php', true, 303);
exit;