<?php
// admin/includes/admin_session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php?error=Please login to access admin panel');
    exit();
}

// Check admin session timeout (1 hour = 3600 seconds)
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > 3600) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Admin session expired. Please login again.');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Optional: Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>