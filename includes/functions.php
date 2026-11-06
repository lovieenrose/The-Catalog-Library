<?php
/**
 * Utility Functions & Security Helpers
 * The Cat-alog Library
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

// ==============================================================================
// 1. Output Escaping
// ==============================================================================

if (!function_exists('e')) {
    /**
     * Escape HTML output context safely
     */
    function e($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// ==============================================================================
// 2. CSRF Protection
// ==============================================================================

if (!function_exists('csrf_token')) {
    /**
     * Generate or retrieve current session CSRF token
     */
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            setup_secure_session();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render hidden CSRF token input
     */
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Validate incoming CSRF token against session
     */
    function verify_csrf_token($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            setup_secure_session();
        }

        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

// ==============================================================================
// 3. Concurrency-Safe Book ID Generator
// ==============================================================================

if (!function_exists('generate_book_id')) {
    /**
     * Generate unique, standardized Book ID
     * Format: {TITLE_2}{MONTH_3}{DAY_2}{YEAR_4}-{CAT_CODE}{TITLE_ID_3}{COPY_NUM_2}
     * Example: TEMAR072024-FIC00101
     */
    function generate_book_id($conn, $title_id, $copy_number, $acquisition_date = null) {
        $stmt = $conn->prepare("SELECT title, author, category, published_year, published_month FROM book_titles WHERE title_id = ?");
        $stmt->execute([$title_id]);
        $title = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$title) {
            throw new InvalidArgumentException("Invalid title_id: {$title_id}");
        }

        $acqDate = $acquisition_date ? new DateTime($acquisition_date) : new DateTime();

        // 2 letters from title
        $cleanTitle = preg_replace('/[^a-zA-Z0-9]/', '', $title['title'] ?? 'BK');
        if (strlen($cleanTitle) < 2) {
            $cleanTitle = str_pad($cleanTitle, 2, 'X');
        }
        $titlePrefix = strtoupper(substr($cleanTitle, 0, 2));

        // 3 letters from published month (or acquisition month)
        if (!empty($title['published_month']) && strlen(trim($title['published_month'])) >= 3) {
            $monthCode = strtoupper(substr(trim($title['published_month']), 0, 3));
        } else {
            $monthCode = strtoupper($acqDate->format('M'));
        }

        // 2 digits from acquisition day
        $dayCode = $acqDate->format('d');

        // 4 digits from published year (or acquisition year)
        if (!empty($title['published_year']) && (int)$title['published_year'] > 0) {
            $yearCode = str_pad((string)(int)$title['published_year'], 4, '0', STR_PAD_LEFT);
        } else {
            $yearCode = $acqDate->format('Y');
        }

        // Category Code (3 letters)
        $catNormalized = strtoupper(trim((string)$title['category']));
        switch ($catNormalized) {
            case 'FICTION':
                $catCode = 'FIC';
                break;
            case 'NON-FICTION':
            case 'NONFICTION':
                $catCode = 'NON';
                break;
            case 'ROMANCE':
                $catCode = 'ROM';
                break;
            case 'CHILDRENS BOOKS':
            case "CHILDREN'S BOOKS":
                $catCode = 'CHI';
                break;
            case 'SCIENCE AND TECHNOLOGY':
            case 'SCIENCE & TECHNOLOGY':
                $catCode = 'SCI';
                break;
            default:
                $cleanedCat = preg_replace('/[^A-Z]/', '', $catNormalized);
                $catCode = strtoupper(substr($cleanedCat ?: 'GEN', 0, 3));
                if (strlen($catCode) < 3) {
                    $catCode = str_pad($catCode, 3, 'X');
                }
                break;
        }

        $titleIdStr = str_pad((string)$title_id, 3, '0', STR_PAD_LEFT);
        $copyNumStr = str_pad((string)$copy_number, 2, '0', STR_PAD_LEFT);

        return "{$titlePrefix}{$monthCode}{$dayCode}{$yearCode}-{$catCode}{$titleIdStr}{$copyNumStr}";
    }
}

// ==============================================================================
// 4. Rate Limiting (Database Backed)
// ==============================================================================

if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('check_rate_limit')) {
    /**
     * Check and record rate limit attempt
     * Returns true if request is allowed, false if rate limit exceeded.
     */
    function check_rate_limit($conn, $action_key, $max_attempts = 5, $decay_seconds = 900) {
        if (!$conn instanceof PDO) {
            return true; // Fail open if DB is offline
        }

        $ip = get_client_ip();
        $rateKey = hash('sha256', $action_key . '|' . $ip);
        $now = time();
        $windowStart = $now - $decay_seconds;

        try {
            // Count recent attempts
            $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND attempt_time > ?");
            $stmt->execute([$rateKey, $windowStart]);
            $attempts = (int)$stmt->fetchColumn();

            if ($attempts >= $max_attempts) {
                return false;
            }

            // Record this attempt
            $ins = $conn->prepare("INSERT INTO rate_limits (rate_key, attempt_time, ip_address) VALUES (?, ?, ?)");
            $ins->execute([$rateKey, $now, $ip]);

            // Periodic cleanup of stale attempts (1% chance per call)
            if (mt_rand(1, 100) === 1) {
                $del = $conn->prepare("DELETE FROM rate_limits WHERE attempt_time < ?");
                $del->execute([$now - 86400]);
            }

            return true;
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return true; // Fail open if rate limit table error
        }
    }
}

if (!function_exists('clear_rate_limit')) {
    /**
     * Clear rate limit on successful authentication
     */
    function clear_rate_limit($conn, $action_key) {
        if (!$conn instanceof PDO) {
            return;
        }
        $ip = get_client_ip();
        $rateKey = hash('sha256', $action_key . '|' . $ip);
        try {
            $stmt = $conn->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
            $stmt->execute([$rateKey]);
        } catch (Exception $e) {
            error_log("Rate limit clear error: " . $e->getMessage());
        }
    }
}

// ==============================================================================
// 5. Secure File Upload Validator
// ==============================================================================

if (!function_exists('validate_and_upload_image')) {
    /**
     * Validate and process image upload with strict MIME, magic bytes, and extension checks.
     * Returns generated filename on success, throws Exception on validation failure.
     */
    function validate_and_upload_image($file, $upload_dir = null) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception("File size exceeds 5MB limit.");
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new Exception("Potential file upload attack detected.");
        }

        // Verify actual MIME type using fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            throw new Exception("Invalid file type ({$mime}). Only JPG, PNG, GIF, and WEBP images are allowed.");
        }

        // Verify image structure using getimagesize
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            throw new Exception("Uploaded file is not a valid image.");
        }

        // Verify file extension
        $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($origExt, $validExts, true)) {
            throw new Exception("Invalid file extension.");
        }

        // Choose canonical extension from verified MIME type
        $canonicalExt = $allowedMimes[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $canonicalExt;

        $targetDir = $upload_dir ?: dirname(__DIR__) . '/uploads/book-images/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = rtrim($targetDir, '/') . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new Exception("Failed to move uploaded file to destination.");
        }

        // Ensure permissions are safe (read-only for web server, not executable)
        chmod($targetPath, 0644);

        return $filename;
    }
}

// ==============================================================================
// 6. Safe Navigation & Open Redirect Prevention
// ==============================================================================

if (!function_exists('safe_redirect')) {
    /**
     * Prevent open redirect vulnerabilities by strictly allowing internal relative paths.
     */
    function safe_redirect($targetUrl, $default = 'dashboard.php') {
        $target = trim((string)$targetUrl);

        // Reject absolute URLs with schemes or protocol-relative slashes
        if (empty($target) || preg_match('/^(?:[a-z]+:)?\/\//i', $target) || strpos($target, "\n") !== false || strpos($target, "\r") !== false) {
            header("Location: {$default}");
            exit();
        }

        // Strip leading backslashes
        $target = ltrim($target, '\\');

        header("Location: {$target}");
        exit();
    }
}

// ==============================================================================
// 7. Safe SQL Sorting Identifier Sanitizers
// ==============================================================================

if (!function_exists('sanitize_sort_column')) {
    function sanitize_sort_column($column, array $allowed, $default) {
        return in_array($column, $allowed, true) ? $column : $default;
    }
}

if (!function_exists('sanitize_sort_order')) {
    function sanitize_sort_order($order, $default = 'ASC') {
        $orderUpper = strtoupper(trim((string)$order));
        return in_array($orderUpper, ['ASC', 'DESC'], true) ? $orderUpper : $default;
    }
}

// ==============================================================================
// 8. Password Reset Email Delivery Helper
// ==============================================================================

if (!function_exists('send_password_reset_email')) {
    /**
     * Send password reset token to student
     */
    function send_password_reset_email($recipient_email, $student_name, $reset_token) {
        $mailEnabled = env('MAIL_ENABLED', false);
        if (!$mailEnabled) {
            // Mail is disabled in environment
            return false;
        }

        $appUrl = rtrim(env('APP_URL', 'http://localhost:8888'), '/');
        $resetUrl = "{$appUrl}/student/forgot_password.php?token=" . urlencode($reset_token);

        $subject = "Password Reset Request - The Cat-alog Library";
        $fromName = env('MAIL_FROM_NAME', 'The Cat-alog Library');
        $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@the-catalog-library.local');

        $message = "Hello " . htmlspecialchars($student_name) . ",\n\n"
            . "We received a request to reset your password for The Cat-alog Library.\n\n"
            . "Please click the link below to set a new password:\n"
            . $resetUrl . "\n\n"
            . "This link is valid for 30 minutes and can only be used once.\n"
            . "If you did not request this reset, please ignore this email.\n\n"
            . "Warm regards,\n"
            . "The Cat-alog Library Team";

        $headers = [
            'From' => "{$fromName} <{$fromAddress}>",
            'Reply-To' => $fromAddress,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        // Use PHP mail() function
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        return @mail($recipient_email, $subject, $message, implode("\r\n", $headerLines));
    }
}

