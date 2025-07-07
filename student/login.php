<?php
session_start();
require_once '../includes/db.php';

$error = "";
$success = "";

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "You have been successfully logged out.";
}

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
    <style>
        /* Force password eye toggle styles */
        .password-container {
            position: relative !important;
            width: 100% !important;
            display: block !important;
        }
        
        .password-container input {
            width: 100% !important;
            padding: 1rem !important;
            padding-right: 3.5rem !important; /* More space for eye */
            border-radius: 1rem !important;
            border: 1px solid #888 !important;
            font-family: 'Sniglet', sans-serif !important;
            font-size: 1rem !important;
            background-color: var(--blush) !important;
            box-sizing: border-box !important;
            margin: 0 !important;
        }
        
        .password-toggle {
            position: absolute !important;
            right: 15px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            color: #555 !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: color 0.3s ease !important;
            z-index: 100 !important;
            width: 20px !important;
            height: 20px !important;
            line-height: 1 !important;
        }
        
        .password-toggle:hover {
            color: #333 !important;
        }
        
        .password-toggle:focus {
            outline: none !important;
            color: var(--caramel) !important;
        }
        
        .password-toggle svg {
            width: 18px !important;
            height: 18px !important;
            fill: currentColor !important;
        }
        
        /* Hide the ::before content since we're using SVG */
        .eye-open::before,
        .eye-closed::before {
            display: none !important;
        }
        
        /* Ensure no margin/padding conflicts */
        .login-form .password-container {
            margin: 0 !important;
            padding: 0 !important;
        }
    </style>
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

    <h2 class="login-title">Student Login</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="text" name="student_number" placeholder="Student Number" value="<?php echo isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : ''; ?>" required>
        
        <div class="password-container">
            <input type="password" id="password" name="password" placeholder="Password" required>
            <button type="button" class="password-toggle eye-closed" onclick="togglePassword()" aria-label="Show password">
                <!-- Eye Closed (Hidden) Icon -->
                <svg class="eye-closed-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.99902 3L20.999 21M9.8433 9.91364C9.32066 10.4536 8.99902 11.1892 8.99902 12C8.99902 13.6569 10.3422 15 11.999 15C12.8215 15 13.5667 14.669 14.1086 14.133M6.49902 6.64715C4.59972 7.90034 3.15305 9.78394 2.45703 12C3.73128 16.0571 7.52159 19 11.9992 19C13.9881 19 15.8414 18.4194 17.3988 17.4184M10.999 5.04939C11.328 5.01673 11.6617 5 11.9992 5C16.4769 5 20.2672 7.94291 21.5414 12C21.2607 12.894 20.8577 13.7338 20.3522 14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <!-- Eye Open (Visible) Icon -->
                <svg class="eye-open-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                    <path d="M2.45703 12C3.73128 7.94288 7.52159 5 11.9992 5C16.4769 5 20.2672 7.94291 21.5414 12C20.2672 16.0571 16.4769 19 11.9992 19C7.52159 19 3.73128 16.0571 2.45703 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M11.9992 15C13.6561 15 14.9992 13.6569 14.9992 12C14.9992 10.3431 13.6561 9 11.9992 9C10.3424 9 8.99923 10.3431 8.99923 12C8.99923 13.6569 10.3424 15 11.9992 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        
        <div class="forgot-link">
            <a href="../student/forgot_password.php">Forgot password?</a>
        </div>
        <button type="submit" class="btn">LOGIN</button>
        <a href="../index.php">
        <button type="button" class="btn">BACK</button>
        </a>

    </form>
    
    <div style="margin-top: 20px; text-align: center;">
        <p><small>Don't have an account? <a href="register.php">Register here</a></small></p>
    </div>

</div>


<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.password-toggle');
    const eyeOpenIcon = document.querySelector('.eye-open-icon');
    const eyeClosedIcon = document.querySelector('.eye-closed-icon');
    
    console.log('Toggle clicked'); // Debug log
    console.log('Current type:', passwordInput.type); // Debug log
    
    if (passwordInput.type === 'password') {
        // Show password
        passwordInput.type = 'text';
        eyeClosedIcon.style.display = 'none';
        eyeOpenIcon.style.display = 'block';
        toggleButton.setAttribute('aria-label', 'Hide password');
        console.log('Password shown'); // Debug log
    } else {
        // Hide password
        passwordInput.type = 'password';
        eyeOpenIcon.style.display = 'none';
        eyeClosedIcon.style.display = 'block';
        toggleButton.setAttribute('aria-label', 'Show password');
        console.log('Password hidden'); // Debug log
    }
}

// Debug: Check if elements exist when page loads
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.password-toggle');
    const eyeOpenIcon = document.querySelector('.eye-open-icon');
    const eyeClosedIcon = document.querySelector('.eye-closed-icon');
    
    console.log('Password input found:', passwordInput);
    console.log('Toggle button found:', toggleButton);
    console.log('Eye open icon found:', eyeOpenIcon);
    console.log('Eye closed icon found:', eyeClosedIcon);
    
    if (!passwordInput) {
        console.error('Password input not found!');
    }
    if (!toggleButton) {
        console.error('Toggle button not found!');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>