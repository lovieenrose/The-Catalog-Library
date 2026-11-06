<?php
/**
 * CLI Tool: Create Administrator Account
 *
 * Usage:
 *   Interactive:
 *     php bin/create_admin.php
 *
 *   Non-interactive / automation:
 *     php bin/create_admin.php --username="admin" --email="admin@example.com" --first_name="Admin" --last_name="User" --password="StrongPassword123!"
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: This script must be run from the command line.\n");
}

require_once __DIR__ . '/../includes/db.php';

echo "========================================================\n";
echo " The Cat-alog Library - Administrator Setup Tool\n";
echo "========================================================\n\n";

// Parse CLI options
$options = getopt("", [
    "username::",
    "email::",
    "first_name::",
    "last_name::",
    "password::",
    "role::",
    "help"
]);

if (isset($options['help'])) {
    echo "Options:\n";
    echo "  --username     Admin username (alphanumeric, min 3 chars)\n";
    echo "  --email        Admin email address\n";
    echo "  --first_name   Admin first name\n";
    echo "  --last_name    Admin last name\n";
    echo "  --password     Admin password (min 8 chars, 1 uppercase, 1 lowercase, 1 digit)\n";
    echo "  --role         Role (default: 'admin')\n";
    echo "  --help         Show this help\n";
    exit(0);
}

function prompt($prompt, $hidden = false) {
    echo $prompt . ": ";
    if ($hidden && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
        $value = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $value = trim(fgets(STDIN));
    }
    return $value;
}

$username = $options['username'] ?? '';
$email = $options['email'] ?? '';
$first_name = $options['first_name'] ?? '';
$last_name = $options['last_name'] ?? '';
$password = $options['password'] ?? '';
$role = $options['role'] ?? 'admin';

if (empty($username)) {
    $username = prompt("Admin Username");
}
if (empty($first_name)) {
    $first_name = prompt("First Name");
}
if (empty($last_name)) {
    $last_name = prompt("Last Name");
}
if (empty($email)) {
    $email = prompt("Email Address");
}
if (empty($password)) {
    $password = prompt("Password", true);
    $confirm_password = prompt("Confirm Password", true);
    if ($password !== $confirm_password) {
        fwrite(STDERR, "Error: Passwords do not match.\n");
        exit(1);
    }
}

// Validation
$errors = [];
if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
    $errors[] = "Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
}
if (empty($first_name) || empty($last_name)) {
    $errors[] = "First and last name are required.";
}
if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
}

if (!empty($errors)) {
    fwrite(STDERR, "Validation errors:\n");
    foreach ($errors as $err) {
        fwrite(STDERR, "  - $err\n");
    }
    exit(1);
}

try {
    // Check for existing administrator with same username or email
    $checkStmt = $conn->prepare("SELECT admin_id, username, email FROM admin_users WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['username'] === $username) {
            fwrite(STDERR, "Error: An administrator with username '{$username}' already exists.\n");
        } else {
            fwrite(STDERR, "Error: An administrator with email '{$email}' already exists.\n");
        }
        exit(1);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare("
        INSERT INTO admin_users (username, password, first_name, last_name, email, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
    ");

    $insertStmt->execute([$username, $passwordHash, $first_name, $last_name, $email, $role]);
    $adminId = $conn->lastInsertId();

    echo "✅ Administrator account created successfully!\n";
    echo "   Admin ID: {$adminId}\n";
    echo "   Username: {$username}\n";
    echo "   Email:    {$email}\n";
    echo "   Role:     {$role}\n\n";
    exit(0);

} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}

