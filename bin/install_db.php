<?php
/**
 * Database Installation & Migration CLI Tool
 * The Cat-alog Library
 *
 * Usage:
 *   php bin/install_db.php [--with-demo]
 */

require_once __DIR__ . '/../includes/config.php';

$host       = env('DB_HOST', '127.0.0.1');
$port       = env('DB_PORT', '8889');
$db         = env('DB_NAME', env('DB_DATABASE', 'the-catalog-library'));
$user       = env('DB_USER', env('DB_USERNAME', 'root'));
$pass       = env('DB_PASS', env('DB_PASSWORD', 'root'));
$socket     = env('DB_SOCKET');
$charset    = 'utf8mb4';
$sslCa      = env('DB_SSL_CA');
$sslVerify  = env('DB_SSL_VERIFY', true);

echo "========================================================\n";
echo "🐾 THE CAT-ALOG LIBRARY - DATABASE INSTALLER\n";
echo "========================================================\n\n";

if (!empty($socket) && file_exists($socket)) {
    $serverDsn = "mysql:unix_socket={$socket};charset={$charset}";
    $dbDsn     = "mysql:unix_socket={$socket};dbname={$db};charset={$charset}";
} elseif (!empty($port)) {
    $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
    $dbDsn     = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
} else {
    $serverDsn = "mysql:host={$host};charset={$charset}";
    $dbDsn     = "mysql:host={$host};dbname={$db};charset={$charset}";
}

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if (!empty($sslCa) && file_exists($sslCa)) {
    $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    if ($sslVerify === false) {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

try {
    $dbConn = null;
    // Try connecting directly to target database first (safe for cloud providers with restricted user permissions)
    try {
        echo "Attempting direct connection to database `{$db}`...\n";
        $dbConn = new PDO($dbDsn, $user, $pass, $pdoOptions);
        echo "Connected to database `{$db}`.\n";
    } catch (PDOException $e) {
        // Direct connection failed; attempt to connect to server and create database
        echo "Direct connection to `{$db}` not ready: " . $e->getMessage() . "\n";
        echo "Attempting to create database `{$db}` on server...\n";
        $serverConn = new PDO($serverDsn, $user, $pass, $pdoOptions);
        $serverConn->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Database `{$db}` ensured.\n";
        $dbConn = new PDO($dbDsn, $user, $pass, $pdoOptions);
    }

    $schemaFile = __DIR__ . '/../sql/library_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found at: {$schemaFile}");
    }

    echo "Executing schema from sql/library_schema.sql...\n";
    $schemaSql = file_get_contents($schemaFile);

    // Split on DELIMITER statements or execute as a whole if possible
    // Note: PDO exec doesn't parse DELIMITER statements natively, so we separate triggers or handle them cleanly
    executeMultiQuery($dbConn, $schemaSql);

    echo "✅ Database schema installed successfully!\n\n";

    // Check if demo data requested
    $withDemo = in_array('--with-demo', $argv ?? []);
    if ($withDemo) {
        $demoFile = __DIR__ . '/../sql/demo_data.sql';
        if (file_exists($demoFile)) {
            echo "Loading demo data from sql/demo_data.sql...\n";
            $demoSql = file_get_contents($demoFile);
            executeMultiQuery($dbConn, $demoSql);
            echo "✅ Demo data loaded successfully!\n\n";
        }
    }

    // Verify tables
    $tables = $dbConn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Installed Tables (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }
    echo "\nDone!\n";

} catch (Exception $e) {
    echo "❌ Database Installation Error: " . $e->getMessage() . "\n";
    exit(1);
}

function executeMultiQuery(PDO $pdo, string $sql) {
    // Strip comments
    $lines = explode("\n", $sql);
    $cleanLines = [];
    $inDelimiter = false;
    $currentDelimiter = ';';
    $buffer = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^--/', $trimmed) || preg_match('/^\/\*/', $trimmed)) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $currentDelimiter = trim($matches[1]);
            continue;
        }

        $buffer .= $line . "\n";

        // Check if buffer ends with delimiter
        if ($currentDelimiter === ';' && substr(rtrim($buffer), -1) === ';') {
            $statement = trim($buffer);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
            $buffer = '';
        } elseif ($currentDelimiter !== ';' && substr(rtrim($buffer), -strlen($currentDelimiter)) === $currentDelimiter) {
            $statement = trim(substr(rtrim($buffer), 0, -strlen($currentDelimiter)));
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
            $buffer = '';
        }
    }

    if (!empty(trim($buffer))) {
        $pdo->exec(trim($buffer));
    }
}

