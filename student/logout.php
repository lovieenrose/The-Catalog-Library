<?php
session_start();

// Log the logout action (optional - for security/audit purposes)
if (isset($_SESSION['user_id']) || isset($_SESSION['username'])) {
    $user_identifier = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'Unknown';
    error_log("User logout: " . $user_identifier . " at " . date('Y-m-d H:i:s'));
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with a logout message
header("Location: login.php?logout=success");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
</head>
<body>

<div class="banner">
    <img src="../assets/images/banner1.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <h2 class="login-title">Logging Out...</h2>
    
    <p style="text-align: center; margin: 20px 0;">
        You are being logged out. Please wait...
    </p>
    
    <div style="text-align: center; margin-top: 20px;">
        <p><small>If you are not redirected automatically, <a href="login.php">click here</a></small></p>
    </div>
</div>

<script>
// Redirect after 2 seconds if header redirect doesn't work
setTimeout(function() {
    window.location.href = 'login.php?logout=success';
}, 2000);
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>