<?php
session_start();
require_once '../includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_number = trim($_POST['student_number']);
    $password = $_POST['password'];
    
    if (!empty($student_number) && !empty($password)) {
        try {
            // Query to find user by username (student number)
            $stmt = $conn->prepare("SELECT user_id, username, password, first_name, last_name, email FROM users WHERE username = ?");
            $stmt->execute([$student_number]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['student_number'] = $user['username']; // Keep for compatibility
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']); // Keep for compatibility
                $_SESSION['email'] = $user['email'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid student number or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="banner">
    <img src="../assets/images/banner1.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <h2 class="login-title">Student Login</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="text" name="student_number" placeholder="Student Number" value="<?php echo isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : ''; ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <div class="forgot-link">
            <a href="../forgot_password.php">Forgot password?</a>
        </div>
        <button type="submit" class="btn">LOGIN</button>
    </form>
    
    <div style="margin-top: 20px; text-align: center;">
        <p><small>Don't have an account? <a href="register.php">Register here</a></small></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>