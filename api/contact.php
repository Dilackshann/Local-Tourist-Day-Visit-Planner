<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Please submit the contact form.');
}

foreach (['name', 'email', 'message'] as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
        respond(422, false, 'Please complete all fields.');
    }
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$message = trim($_POST['message']);

if ($name === '' || $email === '' || $message === '') {
    respond(422, false, 'Please complete all fields.');
}

if (
    !mb_check_encoding($name, 'UTF-8') ||
    !mb_check_encoding($message, 'UTF-8')
) {
    respond(422, false, 'Please enter valid text.');
}

if (
    mb_strlen($name, 'UTF-8') > 100 ||
    strlen($email) > 254 ||
    mb_strlen($message, 'UTF-8') > 3000
) {
    respond(422, false, 'One or more fields are too long.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Please enter a valid email address.');
}

try {
    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, message)
         VALUES (:name, :email, :message)'
    );

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'message' => $message
    ]);

    respond(201, true, 'Your message has been sent. Thank you!');
} catch (PDOException $e) {
    error_log('Contact form failed: ' . $e->getMessage());

    respond(500, false, 'Unable to send your message. Please try again.');
}