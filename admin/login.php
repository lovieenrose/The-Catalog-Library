<?php
session_start();
require_once '../includes/db.php';

$error = "";
$success_message = "";

if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        // Query the admin_users table
        $stmt = $conn->prepare("SELECT admin_id, username, password, first_name, last_name, email, role, status FROM admin_users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Login successful - set session variables that dashboard.php expects
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['first_name'] = $admin['first_name'];
            $_SESSION['last_name'] = $admin['last_name'];
            $_SESSION['email'] = $admin['email'];
            $_SESSION['role'] = $admin['role'];
            
            // Update last login time in database
            $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE admin_id = ?");
            $updateStmt->execute([$admin['admin_id']]);
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
        
    } catch (PDOException $e) {
        $error = "Login error. Please try again.";
        // For debugging, you can uncomment the line below:
        // $error = "Database error: " . $e->getMessage();
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
        <div style="color: green; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
             <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background-color: #ffe6e6; color: #d32f2f; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="text" name="username" placeholder="Admin Username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
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