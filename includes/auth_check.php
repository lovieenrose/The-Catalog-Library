<?php
/**
 * Authentication Check Helpers
 * The Cat-alog Library
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    setup_secure_session();
}

if (!function_exists('is_student_authenticated')) {
    function is_student_authenticated() {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('is_admin_authenticated')) {
    function is_admin_authenticated() {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['username']);
    }
}

if (!function_exists('require_student_auth')) {
    function require_student_auth() {
        if (!is_student_authenticated()) {
            header("Location: login.php?error=" . urlencode("Please log in to continue."));
            exit();
        }

        // Student session timeout check (2 hours)
        $timeout = 7200;
        if (isset($_SESSION['student_last_activity']) && (time() - $_SESSION['student_last_activity'] > $timeout)) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: login.php?error=" . urlencode("Your session has expired. Please log in again."));
            exit();
        }
        $_SESSION['student_last_activity'] = time();

        // Periodic session ID regeneration (every 30 mins)
        if (!isset($_SESSION['session_created'])) {
            $_SESSION['session_created'] = time();
        } elseif (time() - $_SESSION['session_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['session_created'] = time();
        }
    }
}

if (!function_exists('require_admin_auth')) {
    function require_admin_auth() {
        if (!is_admin_authenticated()) {
            header("Location: login.php?error=" . urlencode("Please log in to access the administrator panel."));
            exit();
        }

        // Admin session timeout check (1 hour)
        $adminTimeout = 3600;
        if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $adminTimeout)) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: login.php?error=" . urlencode("Administrator session expired. Please log in again."));
            exit();
        }
        $_SESSION['admin_last_activity'] = time();

        // Periodic session ID regeneration (every 30 mins)
        if (!isset($_SESSION['admin_session_created'])) {
            $_SESSION['admin_session_created'] = time();
        } elseif (time() - $_SESSION['admin_session_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['admin_session_created'] = time();
        }
    }
}

