<?php
/**
 * Vercel Serverless Function Front-Controller Router
 * The Cat-alog Library
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl  = parse_url($requestUri);
$path       = rawurldecode($parsedUrl['path'] ?? '/');

// Base directory (project root)
$rootDir = dirname(__DIR__);
chdir($rootDir);

// 1. Security check: Block private directories, configuration, and sensitive files
$blockedPatterns = [
    '#^/(\.env|\.git|\.htaccess)#i',
    '#^/(includes|sql|bin|tests)/#i',
    '#\.(sql|log|ini|sh|bak|conf|yml|yaml|md|json)$#i',
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $path)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden: Access to private system resources is denied.\n";
        exit();
    }
}

// 2. Normalize requested path
$cleanPath = ltrim($path, '/');
if ($cleanPath === '' || $cleanPath === 'index.php') {
    $targetFile = $rootDir . '/index.php';
    $scriptName = '/index.php';
} elseif (preg_match('#^(student|admin)/([a-zA-Z0-9_-]+)(\.php)?$#', $cleanPath, $matches)) {
    $section = $matches[1];
    $action  = $matches[2];
    $targetFile = "{$rootDir}/{$section}/{$action}.php";
    $scriptName = "/{$section}/{$action}.php";
} elseif (preg_match('#^([a-zA-Z0-9_-]+)(\.php)?$#', $cleanPath, $matches)) {
    $page = $matches[1];
    $targetFile = "{$rootDir}/{$page}.php";
    $scriptName = "/{$page}.php";
} elseif (preg_match('#^(assets|uploads)/#', $cleanPath)) {
    // Static asset fallback if not served directly by Vercel Edge CDN
    $assetFile = $rootDir . '/' . $cleanPath;
    if (file_exists($assetFile) && is_file($assetFile)) {
        $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $contentTypes = [
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
        ];
        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
        header("Content-Type: {$contentType}");
        header('Cache-Control: public, max-age=86400');
        readfile($assetFile);
        exit();
    }
    http_response_code(404);
    echo "404 Not Found";
    exit();
} else {
    $targetFile = null;
}

// 3. Check if target PHP script exists
if (!$targetFile || !file_exists($targetFile)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404 Not Found - The Cat-alog Library</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
    echo '<h1>🐾 404 - Page Not Found</h1>';
    echo '<p>The page you requested could not be found.</p>';
    echo '<p><a href="/">Return to Library Home</a></p>';
    echo '</body></html>';
    exit();
}

// 4. Configure environment for target script
$_SERVER['SCRIPT_NAME']     = $scriptName;
$_SERVER['PHP_SELF']        = $scriptName;
$_SERVER['SCRIPT_FILENAME'] = $targetFile;

// Change working directory so relative asset references and includes function consistently
chdir(dirname($targetFile));

// 5. Execute target script
require $targetFile;
