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

try {
    $totalPlaces = (int) $pdo->query(
        'SELECT COUNT(*) FROM places'
    )->fetchColumn();

   $categoryCounts = $pdo->query(
    'SELECT c.category_id, c.category_name,
            COUNT(p.place_id) AS place_count
     FROM categories c
     LEFT JOIN places p ON p.category_id = c.category_id
     GROUP BY c.category_id, c.category_name
     ORDER BY c.category_name'
)->fetchAll();

    $totalMessages = (int) $pdo->query(
        'SELECT COUNT(*) FROM contact_messages'
    )->fetchColumn();

    $unreadMessages = (int) $pdo->query(
        'SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('Dashboard counts failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load dashboard. Please try again.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Bogawantalawa Planner</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
  href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700&display=swap"
  rel="stylesheet"
>
  <link rel="stylesheet" href="css/style.css?v=2">

<style>
.dashboard-page {
    min-width: 0;
}

.dashboard-page .dashboard-overview {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 24px;
}

.dashboard-page .dashboard-box {
    min-width: 0;
    padding: 24px;
    border: 1px solid #dce8e1;
    border-radius: 18px;
    background: #fff;
}

.dashboard-page .dashboard-box h2,
.dashboard-page .dashboard-section-title {
    margin: 0;
    font-family: inherit;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.4;
    color: #103d32;
}

.dashboard-page .dashboard-overview .dashboard-box {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.dashboard-page .dashboard-number {
    display: block;
    margin: 14px 0 20px;
    font-size: 2.4rem;
    line-height: 1.2;
    color: #176b55;
}

.dashboard-page .dashboard-overview .btn {
    margin-top: auto;
}

.dashboard-page .dashboard-message-counts {
    display: flex;
    gap: 40px;
    margin: 14px 0 20px;
}

.dashboard-page .dashboard-message-counts span {
    display: block;
    color: #52665c;
    font-size: 0.85rem;
}

.dashboard-page .dashboard-message-counts strong {
    display: block;
    margin-top: 4px;
    font-size: 1.8rem;
    line-height: 1.2;
    color: #176b55;
}

.dashboard-page .dashboard-section-title {
    margin: 28px 0 14px;
}

.dashboard-page .dashboard-categories {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.dashboard-page .dashboard-category {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px;
    border: 1px solid #dce8e1;
    border-radius: 14px;
    background: #fff;
}

.dashboard-page .dashboard-category span {
    min-width: 0;
    overflow-wrap: anywhere;
    font-size: 0.95rem;
}

.dashboard-page .dashboard-category strong {
    font-size: 1.6rem;
    color: #176b55;
}

.dashboard-page .dashboard-actions {
    margin-top: 24px;
}

.dashboard-page .dashboard-actions p {
    margin: 8px 0 16px;
}

.dashboard-page .dashboard-action-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

@media (max-width: 1100px) {
    .dashboard-page .dashboard-categories {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .dashboard-page .dashboard-overview,
    .dashboard-page .dashboard-categories {
        grid-template-columns: 1fr;
    }

    .dashboard-page .dashboard-box {
        padding: 20px;
    }
}
</style>

</head>
<body>
  <div class="admin-shell">


<?php require __DIR__ . '/includes/admin-sidebar.php'; ?>


 <main class="admin-main dashboard-page">
    <h1>Dashboard</h1>
    <p class="muted">Manage your places and visitor messages.</p>

    <div class="dashboard-overview">
        <section class="dashboard-box">
            <h2>Total Places</h2>
            <strong class="dashboard-number">
                <?= $totalPlaces ?>
            </strong>
            <a class="btn btn-outline" href="manage-places.php">
                Manage Places
            </a>
        </section>

        <section class="dashboard-box">
            <h2>Messages</h2>

            <div class="dashboard-message-counts">
                <div>
                    <span>Total</span>
                    <strong><?= $totalMessages ?></strong>
                </div>

                <div>
                    <span>Unread</span>
                    <strong><?= $unreadMessages ?></strong>
                </div>
            </div>

            <a class="btn btn-primary" href="admin-messages.php">
                View Messages
            </a>
        </section>
    </div>

    <h2 class="dashboard-section-title">Places by Category</h2>

    <div class="dashboard-categories">
        <?php foreach ($categoryCounts as $category): ?>
            <article class="dashboard-category">
                <span>
                    <?= htmlspecialchars(
                        $category['category_name'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </span>

                <strong>
                    <?= (int) $category['place_count'] ?>
                </strong>
            </article>
        <?php endforeach; ?>
    </div>

    <section class="dashboard-box dashboard-actions">
        <h2>Quick Actions</h2>
        <p class="muted">Add new places or manage existing attractions.</p>

        <div class="dashboard-action-links">
          <a class="btn btn-primary" href="add-place.php">
    Add New Place
</a>
            <a class="btn btn-outline" href="manage-places.php">
                Manage Places
            </a>
        </div>
    </section>
</main>
  </div>
  <script src="js/script.js"></script>
</body>
</html>
