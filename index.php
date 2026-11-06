<?php
// Signal that index.php handles DB connection failure gracefully
$allow_db_failure = true;
require_once __DIR__ . '/includes/config.php';
setup_secure_session();
require_once __DIR__ . '/includes/db.php';

$db_connected = false;
if (isset($conn) && $conn instanceof PDO) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM book_titles");
        $stmt->execute();
        $db_connected = true;
    } catch (Exception $e) {
        $db_connected = false;
        error_log("Database connection issue on homepage: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>The Cat-alog Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include 'includes/favicon.php'; ?>
</head>
<body>

<div class="welcome-container">
    <div class="banner">
        <img src="assets/images/banner.png" alt="Banner Background" class="banner-bg">
        <div class="banner-content">
            <img src="assets/images/logo.png" alt="Logo" class="banner-logo">
            <h1 class="banner-title">The Cat-alog Library</h1>
        </div>
    </div>

    <div class="button-group">
        <a href="student/login.php" class="btn">LOGIN AS STUDENT</a>
        <a href="admin/login.php" class="btn">LOGIN AS ADMIN</a>
    </div>

    <?php if (!$db_connected): ?>
        <div style="background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 20px; border-radius: 5px; color: #c62828; text-align: center;">
            <strong>Note:</strong> Database connection issue detected. Please check your database configuration in <code>.env</code>.
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
