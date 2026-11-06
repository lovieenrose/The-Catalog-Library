<?php
require_once __DIR__ . '/../includes/config.php';
setup_secure_session();

if (!empty($_SESSION['username']) || !empty($_SESSION['admin_id'])) {
    $identifier = $_SESSION['username'] ?? $_SESSION['admin_id'] ?? 'Unknown';
    error_log("Admin logout: " . $identifier . " at " . date('Y-m-d H:i:s'));
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header('Location: login.php?message=' . urlencode('Successfully logged out'));
exit();
