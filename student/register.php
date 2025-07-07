<?php
session_start();
require_once '../includes/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_number = trim($_POST['student_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($student_number) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if student number already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$student_number]);
            if ($stmt->fetch()) {
                $error = "Student number already exists.";
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email address already registered.";
                } else {
                    // Hash password and insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$student_number, $hashed_password, $first_name, $last_name, $email]);
                    
                    $success = "Registration successful! You can now log in.";
                    
                    // Clear form data on success
                    $student_number = $first_name = $last_name = $email = "";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/register.css">
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
</head>
<body>  

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <h2 class="login-title">Student Registration</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="text" name="student_number" placeholder="Student Number" value="<?php echo isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : ''; ?>" required>
        
        <div class="input-row">
            <input type="text" name="first_name" placeholder="First Name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
            <input type="text" name="last_name" placeholder="Last Name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
        </div>
        
        <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        
        <div class="input-row">
            <input type="password" name="password" placeholder="Password (min. 6 chars)" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        </div>
        
        <button type="submit" class="btn">REGISTER</button>
        <a href="../index.php">
            <button type="button" class="btn">BACK</button>
        </a>
    </form>
    
    <div style="margin-top: 20px; text-align: center;">
        <p><small>Already have an account? <a href="login.php">Login here</a></small></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>