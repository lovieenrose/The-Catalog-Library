<?php
/**
 * Database Connection & PDO Configuration
 * The Cat-alog Library
 *
 * DO NOT call session_start() here.
 */

require_once __DIR__ . '/config.php';

$host       = env('DB_HOST', '127.0.0.1');
$port       = env('DB_PORT', '8889');
$db         = env('DB_NAME', env('DB_DATABASE', 'the-catalog-library'));
$user       = env('DB_USER', env('DB_USERNAME', 'root'));
$pass       = env('DB_PASS', env('DB_PASSWORD', 'root'));
$socket     = env('DB_SOCKET');
$charset    = 'utf8mb4';
$sslCa      = env('DB_SSL_CA');
$sslVerify  = env('DB_SSL_VERIFY', true);

// Construct DSN
if (!empty($socket) && file_exists($socket)) {
    $dsn = "mysql:unix_socket={$socket};dbname={$db};charset={$charset}";
} elseif (!empty($port)) {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
} else {
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
}

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

// Configure SSL if specified (e.g., for AWS RDS, PlanetScale, Cloud SQL)
if (!empty($sslCa) && file_exists($sslCa)) {
    $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    if ($sslVerify === false) {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

$conn = null;
$db_connected = false;

try {
    $conn = new PDO($dsn, $user, $pass, $pdoOptions);
    $db_connected = true;
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $db_connected = false;

    // If script explicitly allows failure (e.g. index.php homepage check), do not terminate
    if (isset($allow_db_failure) && $allow_db_failure === true) {
        $conn = null;
    } else {
        // Return a clean, generic response without leaking credentials or paths
        http_response_code(503);
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "Database connection error. Check server logs.\n");
            exit(1);
        } else {
            die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Unavailable - The Cat-alog Library</title>
    <style>
        body { font-family: sans-serif; background: #fff5f2; color: #4a2c11; text-align: center; padding: 50px 20px; }
        .box { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #e0b4a4; }
        h1 { color: #d35400; font-size: 24px; margin-bottom: 10px; }
        p { line-height: 1.6; color: #666; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🐾 System Temporarily Unavailable</h1>
        <p>The library database service is currently unreachable. Our team has been notified.</p>
        <p><small>Please try again in a few moments, or check your configuration.</small></p>
    </div>
</body>
</html>');
        }
    }
}
