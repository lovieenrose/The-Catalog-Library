<?php
require_once __DIR__ . '/../includes/config.php';
setup_secure_session();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['admin_id']) && !empty($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success_message = "";

if (isset($_GET['message'])) {
    $success_message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } elseif (!check_rate_limit($conn, 'admin_login', 5, 900)) {
        $error = "Too many login attempts. Please wait 15 minutes before trying again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            try {
                $stmt = $conn->prepare("
                    SELECT admin_id, username, password, first_name, last_name, email, role, status 
                    FROM admin_users 
                    WHERE username = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    // Check if password needs rehash
                    if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $rehashStmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE admin_id = ?");
                        $rehashStmt->execute([$newHash, $admin['admin_id']]);
                    }

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_login_time'] = time();
                    $_SESSION['admin_last_activity'] = time();
                    $_SESSION['admin_session_created'] = time();
                    $_SESSION['first_name'] = $admin['first_name'];
                    $_SESSION['last_name'] = $admin['last_name'];
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['role'] = $admin['role'];

                    // Update last login timestamp
                    $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?");
                    $updateStmt->execute([$admin['admin_id']]);

                    clear_rate_limit($conn, 'admin_login');

                    safe_redirect('dashboard.php');
                } else {
                    $error = "Invalid administrator username or password.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred during login. Please try again.";
                error_log("Admin login error: " . $e->getMessage());
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
</head>
<body>

<div class="banner">
    <img src="../assets/images/banner.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <h2 class="login-title">Admin Login</h2>

    <?php if ($success_message): ?>
        <div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
            <?php echo e($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background-color: #ffe6e6; color: #d32f2f; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form">
        <?php echo csrf_field(); ?>
        <input type="text" name="username" placeholder="Admin Username" required value="<?php echo isset($username) ? e($username) : ''; ?>">
        <input type="password" name="password" placeholder="Admin Password" required>
        &nbsp;
        <button type="submit" class="btn">LOGIN</button>
        <a href="../index.php">
            <button type="button" class="btn">BACK</button>
        </a>    
    </form>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
