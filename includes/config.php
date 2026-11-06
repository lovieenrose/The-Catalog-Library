<?php
/**
 * Application Configuration & Environment Loader
 * The Cat-alog Library
 */

if (!function_exists('load_env_file')) {
    /**
     * Parse and load .env file into environment
     */
    function load_env_file($path) {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignore comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Key-value pair
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);

            // Strip surrounding quotes
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last = substr($val, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }

            // Do not overwrite existing system/server environment variables
            if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Automatically load .env from project root if it exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    load_env_file($envFile);
}

if (!function_exists('env')) {
    /**
     * Retrieve an environment variable with fallback
     */
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null || $value === false) {
            return $default;
        }

        // Parse boolean and special values
        $lower = strtolower(trim((string)$value));
        switch ($lower) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}

// Configure error reporting and logging
$appEnv = env('APP_ENV', 'production');
$appDebug = env('APP_DEBUG', false);

if ($appDebug || $appEnv === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
ini_set('log_errors', '1');

// Configure secure session parameters before any session starts
if (!function_exists('setup_secure_session')) {
    function setup_secure_session() {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // If database session driver is requested, register custom session handler
        if (env('SESSION_DRIVER') === 'database' || env('VERCEL')) {
            require_once __DIR__ . '/session_handler.php';
            register_database_session_handler();
        }

        session_start();
    }
}

