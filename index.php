<?php
session_start();
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
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/script.js"></script>
</body>
</html>