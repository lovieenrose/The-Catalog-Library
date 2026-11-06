<?php
require_once __DIR__ . '/../includes/config.php';
setup_secure_session();

if (!empty($_SESSION['username']) || !empty($_SESSION['user_id'])) {
    $identifier = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'Unknown';
    error_log("Student logout: " . $identifier . " at " . date('Y-m-d H:i:s'));
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
header("Location: login.php?logout=success");
exit();
