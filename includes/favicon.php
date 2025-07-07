<?php
/**
 * Favicon Include File
 * Include this file in the <head> section of your HTML pages
 * Usage: <?php include 'includes/favicon.php'; ?>
 */

// Determine the correct path based on the current directory
$current_dir = dirname($_SERVER['SCRIPT_NAME']);
$favicon_base_path = '';

// If we're in a subdirectory (like admin/ or student/), go up one level
if (strpos($current_dir, '/admin') !== false || strpos($current_dir, '/student') !== false) {
    $favicon_base_path = '../assets/images/';  // Fixed: removed favicon.png
} else {
    $favicon_base_path = 'assets/images/';     // Fixed: removed favicon.png
}
?>
<!-- Favicon - Simple version using your existing favicon.png -->
<link rel="icon" type="image/png" href="<?php echo $favicon_base_path; ?>favicon.png">
<link rel="shortcut icon" href="<?php echo $favicon_base_path; ?>favicon.png">