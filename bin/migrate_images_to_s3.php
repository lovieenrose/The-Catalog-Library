<?php
/**
 * CLI Tool: Sync Local Book Images to S3 / Cloudflare R2
 * The Cat-alog Library
 *
 * Usage:
 *   php bin/migrate_images_to_s3.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: This script must be run from the command line.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/storage.php';

echo "========================================================\n";
echo "🐾 THE CAT-ALOG LIBRARY - S3/R2 IMAGE MIGRATION TOOL\n";
echo "========================================================\n\n";

$s3 = get_s3_storage_client();
if (!$s3) {
    echo "❌ Error: S3 / Cloudflare R2 credentials are not configured in .env.\n";
    echo "Please set S3_BUCKET, S3_KEY, S3_SECRET, and optionally S3_ENDPOINT.\n";
    exit(1);
}

$imagesDir = dirname(__DIR__) . '/uploads/book-images';
if (!is_dir($imagesDir)) {
    echo "❌ Error: Local images directory does not exist: {$imagesDir}\n";
    exit(1);
}

$files = scandir($imagesDir);
$synced = 0;
$skipped = 0;
$failed = 0;

$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach ($files as $file) {
    if ($file === '.' || $file === '..' || $file === '.DS_Store' || $file === '.htaccess') {
        continue;
    }

    $filePath = $imagesDir . '/' . $file;
    if (!is_file($filePath)) {
        continue;
    }

    $mimeType = $finfo->file($filePath);
    $key = 'book-images/' . $file;

    echo "Checking {$file} ({$mimeType})... ";

    // Check if already in S3
    if ($s3->headObject($key)) {
        echo "Already exists in S3. Skipped.\n";
        $skipped++;
        continue;
    }

    echo "Uploading... ";
    $data = file_get_contents($filePath);
    if ($s3->putObject($key, $data, $mimeType)) {
        echo "✅ Uploaded.\n";
        $synced++;
    } else {
        echo "❌ Upload failed.\n";
        $failed++;
    }
}

echo "\nMigration Summary:\n";
echo "  - Uploaded: {$synced}\n";
echo "  - Skipped:  {$skipped}\n";
echo "  - Failed:   {$failed}\n";
echo "\nDone!\n";

