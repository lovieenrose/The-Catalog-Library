<?php
session_start();
require_once '../includes/db.php';

$error = "";
$success = "";
$step = isset($_GET['step']) ? $_GET['step'] : 'identify';

// Step 1: User enters username/email to identify account
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 'identify') {
    $identifier = trim($_POST['identifier']); // Can be username or email
    
    if (empty($identifier)) {
        $error = "Please enter your username or email address.";
    } else {
        try {
            // Check if user exists by username or email
            $stmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Store user info in session for security questions
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_username'] = $user['username'];
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect to security questions
                header("Location: forgot_password.php?step=verify");
                exit();
            } else {
                $error = "No account found with that username or email address.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}

// Step 2: Verify identity with simple questions
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 'verify') {
    if (!isset($_SESSION['reset_user_id'])) {
        header("Location: forgot_password.php");
        exit();
    }
    
    $user_email = trim($_POST['user_email']);
    $user_first_name = trim($_POST['user_first_name']);
    
    if (empty($user_email) || empty($user_first_name)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Verify the provided information matches
            $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['reset_user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && 
                strtolower($user_data['email']) === strtolower($user_email) && 
                strtolower($user_data['first_name']) === strtolower($user_first_name)) {
                
                // Identity verified, proceed to password reset
                header("Location: forgot_password.php?step=reset");
                exit();
            } else {
                $error = "The information provided doesn't match our records.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
        }
    }
}

// Step 3: Reset password
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 'reset') {
    if (!isset($_SESSION['reset_user_id'])) {
        header("Location: forgot_password.php");
        exit();
    }
    
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password)) {
        $error = "Please enter a new password.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$password_hash, $_SESSION['reset_user_id']]);
            
            // Clear session data
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_username']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_name']);
            
            $success = "Your password has been successfully updated!";
            $step = 'complete';
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
            error_log("Password update error: " . $e->getMessage());
        }
    }
}

// Check session for verification steps
if (($step === 'verify' || $step === 'reset') && !isset($_SESSION['reset_user_id'])) {
    $step = 'identify';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        <?php 
        if ($step === 'verify') echo 'Verify Identity';
        elseif ($step === 'reset') echo 'Reset Password';
        elseif ($step === 'complete') echo 'Password Reset Complete';
        else echo 'Forgot Password';
        ?> - The Cat-alog Library
    </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <style>
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .step.active {
            background-color: var(--caramel);
            color: white;
        }
        
        .step.completed {
            background-color: #28a745;
            color: white;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background-color: #e7f3ff;
            color: #004085;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #b6d7ff;
        }
        
        .btn-back {
            background-color: var(--caramel);
            color: white;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Sniglet', sans-serif;
            cursor: pointer;
            text-align: center;
        }
        
        .btn-back:hover {
            background-color: #a0622d;
            transform: translateY(-2px);
        }
        
        .user-info {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>

<div class="banner">
    <img src="../assets/images/banner1.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step <?php echo ($step === 'identify') ? 'active' : (in_array($step, ['verify', 'reset', 'complete']) ? 'completed' : ''); ?>">1</div>
        <div class="step <?php echo ($step === 'verify') ? 'active' : (in_array($step, ['reset', 'complete']) ? 'completed' : ''); ?>">2</div>
        <div class="step <?php echo ($step === 'reset') ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">3</div>
    </div>

    <?php if ($step === 'identify'): ?>
        <!-- Step 1: Identify Account -->
        <h2 class="login-title">Forgot Password?</h2>
        
        <div class="info-box">
            <strong>🔐 Step 1: Find Your Account</strong><br>
            Enter your username or email address to get started.
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <input type="text" name="identifier" placeholder="Username or Email Address" 
                   value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>" required>
            
            <button type="submit" class="btn">Find My Account</button>
            <a href="login.php" class="btn-back">← Back to Login</a>
        </form>

    <?php elseif ($step === 'verify'): ?>
        <!-- Step 2: Verify Identity -->
        <h2 class="login-title">Verify Your Identity</h2>
        
        <div class="user-info">
            <strong>👤 Account Found:</strong><br>
            Username: <?php echo htmlspecialchars($_SESSION['reset_username']); ?><br>
            Name: <?php echo htmlspecialchars($_SESSION['reset_name']); ?>
        </div>
        
        <div class="info-box">
            <strong>🛡️ Step 2: Verify It's You</strong><br>
            Please confirm your account details to verify your identity.
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <input type="email" name="user_email" placeholder="Confirm Your Email Address" required>
            <input type="text" name="user_first_name" placeholder="Confirm Your First Name" required>
            
            <button type="submit" class="btn">Verify Identity</button>
            <a href="forgot_password.php" class="btn-back">← Start Over</a>
        </form>

    <?php elseif ($step === 'reset'): ?>
        <!-- Step 3: Reset Password -->
        <h2 class="login-title">Reset Your Password</h2>
        
        <div class="user-info">
            <strong>✅ Identity Verified</strong><br>
            You can now set a new password for: <?php echo htmlspecialchars($_SESSION['reset_username']); ?>
        </div>
        
        <div class="info-box">
            <strong>🔑 Step 3: Create New Password</strong><br>
            Choose a strong password (at least 6 characters).
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <input type="password" name="new_password" placeholder="New Password (min. 6 characters)" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            
            <button type="submit" class="btn">Update Password</button>
            <a href="forgot_password.php" class="btn-back">← Start Over</a>
        </form>

    <?php else: ?>
        <!-- Step 4: Success -->
        <h2 class="login-title">Password Updated!</h2>
        
        <div class="success-message">
            <strong>🎉 Success!</strong><br>
            Your password has been successfully updated.
        </div>
        
        <div class="info-box">
            <strong>✨ All Done!</strong><br>
            You can now login with your new password.
        </div>
        
        <a href="login.php" class="btn">Go to Login</a>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>