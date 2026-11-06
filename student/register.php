<?php
require_once __DIR__ . '/../includes/config.php';
setup_secure_session();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } elseif (!check_rate_limit($conn, 'student_register', 5, 900)) {
        $error = "Too many registration attempts from this address. Please wait 15 minutes.";
    } else {
        $student_number = trim($_POST['student_number'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($student_number) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
        } elseif (strlen($student_number) < 3 || strlen($student_number) > 50) {
            $error = "Student number must be between 3 and 50 characters.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check if student number or email already exists
                $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$student_number, $email]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    if (strcasecmp($existing['username'], $student_number) === 0) {
                        $error = "Student number already exists.";
                    } else {
                        $error = "Email address already registered.";
                    }
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $conn->prepare("
                        INSERT INTO users (username, password, first_name, last_name, email, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $insertStmt->execute([$student_number, $hashed_password, $first_name, $last_name, $email]);

                    $success = "Registration successful! You can now log in.";
                    $student_number = $first_name = $last_name = $email = "";
                }
            } catch (PDOException $e) {
                $error = "Database error. Please try again later.";
                error_log("Registration error: " . $e->getMessage());
            }
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
        <p style="color: red;"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo e($success); ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
        <?php echo csrf_field(); ?>
        <input type="text" name="student_number" placeholder="Student Number" value="<?php echo isset($student_number) ? e($student_number) : ''; ?>" required>
        
        <div class="input-row">
            <input type="text" name="first_name" placeholder="First Name" value="<?php echo isset($first_name) ? e($first_name) : ''; ?>" required>
            <input type="text" name="last_name" placeholder="Last Name" value="<?php echo isset($last_name) ? e($last_name) : ''; ?>" required>
        </div>
        
        <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($email) ? e($email) : ''; ?>" required>
        
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
