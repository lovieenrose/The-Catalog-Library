<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Simulate successful login
    $_SESSION['student_id'] = 1;
    $_SESSION['student_number'] = $_POST['student_number'];
    $_SESSION['full_name'] = 'Maria Theresa';

    header("Location: dashboard.php");
    exit();
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
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="text" name="student_number" placeholder="Student Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <div class="forgot-link">
            <a href="../forgot_password.php">Forgot password?</a>
        </div>
        <button type="submit" class="btn">LOGIN</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
