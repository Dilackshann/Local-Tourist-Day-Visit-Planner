<?php

session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.html');
    exit;
}

require_once __DIR__ . '/config/database.php';

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


if (empty($_SESSION['message_csrf'])) {
    $_SESSION['message_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['message_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request. Please reload the page.');
    }

    $messageId = filter_input(
        INPUT_POST,
        'message_id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$messageId) {
        http_response_code(400);
        exit('Invalid message.');
    }

   $action = $_POST['action'] ?? 'mark_read';

if (
    !is_string($action) ||
    !in_array($action, ['mark_read', 'delete'], true)
) {
    http_response_code(400);
    exit('Invalid action.');
}

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare(
            'DELETE FROM contact_messages
             WHERE message_id = :id'
        );
    } else {
        $stmt = $pdo->prepare(
            'UPDATE contact_messages
             SET is_read = 1
             WHERE message_id = :id'
        );
    }

    $stmt->execute(['id' => $messageId]);
} catch (PDOException $e) {
    error_log('Unable to update message: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to update message. Please try again.');
}

    header('Location: admin-messages.php', true, 303);
    exit;
}

$messages = [];
$error = '';

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = max(1, $page ?: 1);
$perPage = 20;
$totalPages = 1;

try {
    $total = (int) $pdo->query(
        'SELECT COUNT(*) FROM contact_messages'
    )->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        'SELECT message_id, name, email, message, is_read, created_at
         FROM contact_messages
         ORDER BY created_at DESC, message_id DESC
         LIMIT :limit OFFSET :offset'
    );

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Unable to load messages: ' . $e->getMessage());
    http_response_code(500);
    $error = 'Unable to load messages. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Bogo DayTrails</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
    <style>
        .messages-list {
            display: grid;
            gap: 16px;
            margin-top: 24px;
        }

        .message-card {
            min-width: 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #d7e6df;
            border-radius: 14px;
            overflow-wrap: anywhere;
        }

        .message-card h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
        }

        .message-meta {
            margin: 4px 0;
            color: #52665c;
            font-size: 0.9rem;
        }

        .message-body {
            margin: 16px 0 0;
            white-space: pre-wrap;
            line-height: 1.7;
        }

        .messages-pagination {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 24px;
        }
    </style>
</head>
<body>
<div class="admin-shell">
   <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>

    <main class="admin-main">
        <h1>Messages</h1>
        <p class="muted">Messages received through the contact form.</p>

        <?php if ($error !== ''): ?>
            <p role="alert"><?= escapeHtml($error) ?></p>

        <?php elseif (!$messages): ?>
            <p>No messages received yet.</p>

        <?php else: ?>
            <div class="messages-list">
                <?php foreach ($messages as $item): ?>




                    
                    <article class="message-card">
                        <h2><?= escapeHtml($item['name']) ?></h2>

                        <p class="message-meta">
                            Email: <?= escapeHtml($item['email']) ?>
                        </p>

                        <p class="message-meta">
                            Received: <?= escapeHtml($item['created_at']) ?>
                        </p>

                        <p class="message-body"><?= escapeHtml($item['message']) ?></p>

<div style="margin-top: 16px;">
    <?php if ((int) $item['is_read'] === 1): ?>
        <span style="color: #176b45; font-weight: 700;">
            ✓ Read
        </span>
    <?php else: ?>
        <p style="margin-bottom: 10px; font-weight: 700;">
            Unread
        </p>

        <form action="admin-messages.php" method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeHtml($_SESSION['message_csrf']) ?>"
            >

            <input
                type="hidden"
                name="message_id"
                value="<?= (int) $item['message_id'] ?>"
            >

          <button
    class="btn btn-primary"
    type="submit"
    name="action"
    value="mark_read"
>
    Mark as Read
</button>
        </form>
    <?php endif; ?>
</div>

<!-- Delete message -->
<form
    action="admin-messages.php"
    method="POST"
    style="margin-top: 12px;"
    onsubmit="return confirm('Delete this message permanently?');"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= escapeHtml($_SESSION['message_csrf']) ?>"
    >

    <input
        type="hidden"
        name="message_id"
        value="<?= (int) $item['message_id'] ?>"
    >

    <button
        class="btn btn-danger"
        type="submit"
        name="action"
        value="delete"
    >
        Delete Message
    </button>
</form>

</article>



        <?php endforeach; ?>
            </div>

            <nav class="messages-pagination" aria-label="Messages pages">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline"
                       href="admin-messages.php?page=<?= $page - 1 ?>">
                        Previous
                    </a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-outline"
                       href="admin-messages.php?page=<?= $page + 1 ?>">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
</div>
</body>
</html>