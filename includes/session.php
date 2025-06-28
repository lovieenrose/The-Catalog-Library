<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: Redirect if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../student_login.php");
    exit();
}
