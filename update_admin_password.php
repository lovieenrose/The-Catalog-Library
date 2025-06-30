<?php
// Run this ONCE to update admin password to 'admin123'
// Then DELETE this file immediately!

require_once 'includes/db.php';

$username = 'admin';
$new_password = 'admin123';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
    $result = $stmt->execute([$password_hash, $username]);
    
    if ($result) {
        echo "<h2 style='color: green;'>✅ SUCCESS!</h2>";
        echo "<p>Admin password has been updated successfully.</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<hr>";
        echo "<p style='color: red; font-weight: bold;'>🚨 DELETE THIS FILE NOW! 🚨</p>";
        echo "<p>This file is a security risk. Delete it immediately.</p>";
        
        // Show current admin user info
        $checkStmt = $conn->prepare("SELECT username, first_name, last_name, email, role, status FROM admin_users WHERE username = ?");
        $checkStmt->execute([$username]);
        $admin = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<h3>Updated Admin User:</h3>";
            echo "<p><strong>Name:</strong> {$admin['first_name']} {$admin['last_name']}</p>";
            echo "<p><strong>Email:</strong> {$admin['email']}</p>";
            echo "<p><strong>Role:</strong> {$admin['role']}</p>";
            echo "<p><strong>Status:</strong> {$admin['status']}</p>";
        }
        
    } else {
        echo "<h2 style='color: red;'>❌ FAILED</h2>";
        echo "<p>Could not update password.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ ERROR</h2>";
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 40px; 
    background-color: #f5f5f5;
}
h2, h3 { color: #333; }
p { margin: 10px 0; }
</style>