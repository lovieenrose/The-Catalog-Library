<?php
require_once __DIR__ . '/../includes/config.php';
setup_secure_session();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = "";
$success = "";
$mailEnabled = env('MAIL_ENABLED', false);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isResetStep = !empty($token);
$validTokenRecord = null;

// If a token is provided, validate it
if ($isResetStep) {
    $tokenHash = hash('sha256', $token);
    try {
        $stmt = $conn->prepare("
            SELECT pr.id, pr.user_id, pr.expires_at, u.username, u.first_name 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.user_id
            WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $validTokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$validTokenRecord) {
            $error = "This password reset link is invalid or has expired. Please request a new one.";
            $isResetStep = false;
        }
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = "An error occurred while verifying the reset link. Please try again.";
        $isResetStep = false;
    }
}

// Handle POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } elseif ($isResetStep && $validTokenRecord) {
        // Step 2: Set New Password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password)) {
            $error = "Please enter a new password.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                $conn->beginTransaction();

                // Lock the reset record atomically
                $lockStmt = $conn->prepare("
                    SELECT id, user_id FROM password_resets 
                    WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() 
                    FOR UPDATE
                ");
                $lockStmt->execute([hash('sha256', $token)]);
                $lockedRecord = $lockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$lockedRecord) {
                    $conn->rollBack();
                    $error = "This reset token has already been used or has expired.";
                } else {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update user password
                    $updateUserStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $updateUserStmt->execute([$hashedPassword, $lockedRecord['user_id']]);

                    // Invalidate token atomically
                    $updateTokenStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                    $updateTokenStmt->execute([$lockedRecord['id']]);

                    $conn->commit();
                    $success = "Your password has been successfully updated! You can now log in.";
                    $isResetStep = false;
                    $token = '';
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Password update error: " . $e->getMessage());
                $error = "An error occurred while updating your password. Please try again.";
            }
        }
    } else {
        // Step 1: Request Password Reset Token
        if (!$mailEnabled) {
            $error = "Self-service password reset is currently unavailable. Please contact a library administrator.";
        } else {
            // Apply rate limiting (5 attempts per 15 minutes)
            if (!check_rate_limit($conn, 'password_reset_request', 5, 900)) {
                $error = "Too many password reset requests. Please wait 15 minutes before trying again.";
            } else {
                $identifier = trim($_POST['identifier'] ?? '');

                if (empty($identifier)) {
                    $error = "Please enter your student number or registered email address.";
                } else {
                    try {
                        $stmt = $conn->prepare("SELECT user_id, username, email, first_name FROM users WHERE username = ? OR email = ? LIMIT 1");
                        $stmt->execute([$identifier, $identifier]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user) {
                            // Generate cryptographically secure token
                            $rawToken = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $rawToken);

                            // Clean up previous unused tokens for this user
                            $delStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                            $delStmt->execute([$user['user_id']]);

                            // Insert new token with 30-minute validity
                            $insStmt = $conn->prepare("
                                INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
                            ");
                            $insStmt->execute([$user['user_id'], $tokenHash]);

                            // Deliver email
                            send_password_reset_email($user['email'], $user['first_name'], $rawToken);
                        }

                        // Prevent account enumeration by always displaying the exact same message
                        $success = "If a matching account was found, a password reset link has been sent to the associated email address. Please check your inbox.";
                    } catch (PDOException $e) {
                        error_log("Forgot password error: " . $e->getMessage());
                        $error = "A system error occurred. Please try again later.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isResetStep ? 'Set New Password' : 'Forgot Password'; ?> - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
    <style>
        .login-container {
            width: 90%;
            background-color: #fce6e0;
            margin: 10vh auto 2rem auto;
            padding: 2rem;
            border-radius: 1.5rem;
            max-width: 500px;
            border: 1px solid var(--black);
            text-align: center;
            box-shadow: 4px 4px var(--caramel);
            position: relative;
            z-index: 10;
            margin-bottom: 5rem;
        }

        .logo-wrapper {
            margin-top: -50px;
            margin-bottom: 1rem;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
            text-align: left;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
            text-align: left;
        }
        
        .info-box {
            background-color: #e7f3ff;
            color: #004085;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #b6d7ff;
            text-align: left;
        }

        .btn-back {
            background-color: var(--blush);
            color: white;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 5rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
            font-family: 'Sniglet', sans-serif;
            cursor: pointer;
            text-align: center;
            font-size: 1rem;
            text-shadow: -1px -1px 0 var(--black), 1px -1px 0 var(--black), -1px 1px 0 var(--black), 1px 1px 0 var(--black);
            box-shadow: 4px 4px var(--caramel);
            border: 2px solid var(--black);
        }

        .btn-back:hover {
            background-color: var(--pinkish);
            transform: translateY(-3px);
            box-shadow: 6px 6px var(--caramel);
        }

        .login-form input {
            width: 100%;
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid #888;
            font-family: 'Sniglet', sans-serif;
            font-size: 1rem;
            background-color: var(--blush);
            margin-bottom: 1rem;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

<div class="banner">
    <img src="../assets/images/banner.png" alt="Banner" class="banner-bg">
</div>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
    </div>

    <h2 class="login-title"><?php echo $isResetStep ? 'Reset Password' : 'Forgot Password'; ?></h2>

    <?php if ($error): ?>
        <div class="error-message"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?php echo e($success); ?></div>
        <a href="login.php" class="btn" style="display: block; margin-top: 15px; text-decoration: none;">Return to Login</a>
    <?php elseif ($isResetStep && $validTokenRecord): ?>
        <!-- Set New Password Form -->
        <div class="info-box">
            <strong>🔑 Choose a New Password</strong><br>
            Setting new password for account: <strong><?php echo e($validTokenRecord['username']); ?></strong><br>
            Password must be at least 6 characters.
        </div>

        <form method="post" class="login-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="token" value="<?php echo e($token); ?>">
            <input type="password" name="new_password" placeholder="New Password (min. 6 characters)" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" class="btn">UPDATE PASSWORD</button>
        </form>
        <a href="login.php" class="btn-back">← Back to Login</a>
    <?php else: ?>
        <!-- Request Password Reset Form -->
        <?php if (!$mailEnabled): ?>
            <div class="info-box" style="background-color: #fff3cd; color: #856404; border-color: #ffeeba;">
                <strong>ℹ️ Password Reset Information</strong><br>
                Self-service password resets are currently disabled. To reset your student password, please visit the library circulation desk or contact your system administrator.
            </div>
            <a href="login.php" class="btn-back">← Back to Login</a>
        <?php else: ?>
            <div class="info-box">
                <strong>🔐 Account Recovery</strong><br>
                Enter your Student Number or registered email address. We will send a secure password reset link to your email.
            </div>

            <form method="post" class="login-form">
                <?php echo csrf_field(); ?>
                <input type="text" name="identifier" placeholder="Student Number or Email" 
                       value="<?php echo isset($_POST['identifier']) ? e($_POST['identifier']) : ''; ?>" required>
                <button type="submit" class="btn">SEND RESET LINK</button>
            </form>
            <a href="login.php" class="btn-back">← Back to Login</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
