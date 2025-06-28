<?php
session_start();

// Placeholder logic - to be replaced with real login handling later
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - The Cat-alog Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Banner + Logo -->
<div class="banner">
    <img src="assets/images/banner2.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="assets/images/logo.png" alt="The Cat-alog Library Logo" class="logo-img">
    </div>

    <h2 class="login-title">Admin Login</h2>

    <form method="post" class="login-form">
        <input type="text" name="admin_number" placeholder="Admin Number" required>
        <input type="password" name="password" placeholder="Password" required>

        <div class="forgot-link">
            <a href="forgot_password.php">Forgot password?</a>
        </div>

        <button type="submit" class="btn">LOGIN</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
