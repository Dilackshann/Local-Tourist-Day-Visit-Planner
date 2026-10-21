<?php
$sidebarPage = basename($_SERVER['SCRIPT_NAME']);

$sidebarLinks = [
    'admin-dashboard.php' => 'Dashboard',
    'manage-places.php' => 'Manage Places',
    'admin-messages.php' => 'Messages',
    'add-place.php' => 'Add New Place',
    'index.html' => 'View website'
];

// Editing a place belongs to Manage Places.
if ($sidebarPage === 'edit-place.php') {
    $sidebarPage = 'manage-places.php';
}
?>

<aside class="admin-side">
    <strong class="admin-side-title">Admin panel</strong>

    <nav aria-label="Admin navigation">
        <?php foreach ($sidebarLinks as $file => $label): ?>
            <a
                href="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>"

<?php if ($file === 'index.html'): ?>
    target="_blank" rel="noopener noreferrer"
<?php endif; ?>

                <?php if ($sidebarPage === $file): ?>
                    class="active" aria-current="page"
                <?php endif; ?>
            >
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form action="api/logout.php" method="POST" class="logout-form">
        <button class="btn btn-danger" type="submit">
            Logout
        </button>
    </form>
</aside>