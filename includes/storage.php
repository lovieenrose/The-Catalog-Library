<?php
/**
 * Persistent Object Storage Service (Local / S3 / Cloudflare R2)
 * The Cat-alog Library
 */

require_once __DIR__ . '/config.php';

class S3StorageClient {
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;
    private ?string $publicUrl;

    public function __construct(
        string $bucket,
        string $accessKey,
        string $secretKey,
        string $region = 'us-east-1',
        ?string $endpoint = null,
        ?string $publicUrl = null
    ) {
        $this->bucket    = trim($bucket);
        $this->accessKey = trim($accessKey);
        $this->secretKey = trim($secretKey);
        $this->region    = !empty($region) ? trim($region) : 'us-east-1';
        $this->endpoint  = !empty($endpoint) ? rtrim($endpoint, '/') : '';
        $this->publicUrl = !empty($publicUrl) ? rtrim($publicUrl, '/') : null;
    }

    /**
     * Upload an object to S3 / Cloudflare R2
     */
    public function putObject(string $key, string $body, string $contentType): bool {
        $key = ltrim($key, '/');
        $headers = [
            'Content-Type' => $contentType,
        ];

        $response = $this->sendRequest('PUT', $key, $headers, $body);
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    /**
     * Delete an object from S3 / Cloudflare R2
     */
    public function deleteObject(string $key): bool {
        $key = ltrim($key, '/');
        $response = $this->sendRequest('DELETE', $key);
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    /**
     * Check if an object exists
     */
    public function headObject(string $key): bool {
        $key = ltrim($key, '/');
        $response = $this->sendRequest('HEAD', $key);
        return $response['status'] === 200;
    }

    /**
     * Get public URL for an object key
     */
    public function getPublicUrl(string $key): string {
        $key = ltrim($key, '/');
        if (!empty($this->publicUrl)) {
            return $this->publicUrl . '/' . $key;
        }

        if (!empty($this->endpoint)) {
            // Path-style endpoint e.g. R2 or MinIO
            return $this->endpoint . '/' . $this->bucket . '/' . $key;
        }

        // Standard AWS S3 virtual-host format
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";
    }

    /**
     * Send an AWS Signature Version 4 (SigV4) signed request
     */
    private function sendRequest(string $method, string $key, array $headers = [], string $body = ''): array {
        $service = 's3';
        $host = '';
        $uriPath = '';

        if (!empty($this->endpoint)) {
            $parsed = parse_url($this->endpoint);
            $host = $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');
            $basePath = !empty($parsed['path']) ? rtrim($parsed['path'], '/') : '';
            $uriPath = $basePath . '/' . $this->bucket . '/' . $key;
            $scheme = $parsed['scheme'] ?? 'https';
            $url = "{$scheme}://{$host}{$uriPath}";
        } else {
            // Virtual-hosted AWS S3
            $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
            $uriPath = '/' . $key;
            $url = "https://{$host}{$uriPath}";
        }

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $payloadHash = hash('sha256', $body);

        $headers['Host'] = $host;
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;

        // Canonical headers
        $canonicalHeadersArr = [];
        foreach ($headers as $k => $v) {
            $canonicalHeadersArr[strtolower(trim($k))] = trim($v);
        }
        ksort($canonicalHeadersArr);

        $canonicalHeadersStr = '';
        $signedHeadersArr = [];
        foreach ($canonicalHeadersArr as $k => $v) {
            $canonicalHeadersStr .= $k . ':' . $v . "\n";
            $signedHeadersArr[] = $k;
        }
        $signedHeadersStr = implode(';', $signedHeadersArr);

        // Canonical request
        // Encode URI path safely according to RFC 3986
        $encodedUriPath = '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($uriPath, '/'))));
        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $encodedUriPath,
            '', // query string
            $canonicalHeadersStr,
            $signedHeadersStr,
            $payloadHash
        ]);

        // String to sign
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        // Signing key calculation
        $kSecret  = 'AWS4' . $this->secretKey;
        $kDate    = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        // Signature
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Authorization header
        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";
        $headers['Authorization'] = $authHeader;

        // Execute cURL
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("S3StorageClient curl error: {$curlError} for {$method} {$url}");
        } elseif ($httpCode >= 400) {
            error_log("S3StorageClient error HTTP {$httpCode} for {$method} {$url}: {$responseBody}");
        }

        return [
            'status' => $httpCode,
            'body'   => $responseBody,
            'error'  => $curlError
        ];
    }
}

/**
 * Storage Helper Factory
 */
function get_s3_storage_client(): ?S3StorageClient {
    static $client = null;
    if ($client !== null) {
        return $client;
    }

    $bucket = env('S3_BUCKET');
    $key    = env('S3_KEY');
    $secret = env('S3_SECRET');

    if (empty($bucket) || empty($key) || empty($secret)) {
        return null;
    }

    $client = new S3StorageClient(
        $bucket,
        $key,
        $secret,
        env('S3_REGION', 'us-east-1'),
        env('S3_ENDPOINT'),
        env('S3_PUBLIC_URL')
    );

    return $client;
}

/**
 * Validate and Upload a Book Cover Image
 *
 * @param array $file $_FILES['book_image']
 * @return array ['success' => bool, 'filename' => ?string, 'error' => ?string]
 */
function storage_upload_book_image(array $file): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'No file uploaded or upload error occurred.'];
    }

    $tmpPath  = $file['tmp_name'];
    $fileSize = $file['size'];
    $origName = $file['name'];
    $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // 1. Content size validation (max 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        return ['success' => false, 'filename' => null, 'error' => 'File size must be less than 5MB.'];
    }

    // 2. MIME type binary content inspection
    if (!function_exists('finfo_open')) {
        return ['success' => false, 'filename' => null, 'error' => 'Server fileinfo extension is required.'];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($mimeType, $allowedMimes, true) || !in_array($fileExt, $allowedExts, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Only JPG, PNG, GIF, and WEBP image files are allowed.'];
    }

    // 3. Generate secure, collision-resistant filename
    $safeFilename = bin2hex(random_bytes(10)) . '_' . time() . '.' . $fileExt;

    // 4. Dispatch to configured storage driver
    $driver = strtolower(env('STORAGE_DRIVER', 'local'));

    if ($driver === 's3' || $driver === 'r2') {
        $s3 = get_s3_storage_client();
        if (!$s3) {
            return ['success' => false, 'filename' => null, 'error' => 'Cloud storage credentials are not properly configured.'];
        }

        $body = file_get_contents($tmpPath);
        if ($body === false) {
            return ['success' => false, 'filename' => null, 'error' => 'Failed to read uploaded image.'];
        }

        $s3Key = 'book-images/' . $safeFilename;
        $uploaded = $s3->putObject($s3Key, $body, $mimeType);

        if (!$uploaded) {
            return ['success' => false, 'filename' => null, 'error' => 'Failed to upload image to cloud storage. Check server logs.'];
        }

        return ['success' => true, 'filename' => $safeFilename, 'error' => null];
    }

    // Local filesystem storage
    $uploadDir = dirname(__DIR__) . '/uploads/book-images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . $safeFilename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to save uploaded image to local storage.'];
    }

    return ['success' => true, 'filename' => $safeFilename, 'error' => null];
}

/**
 * Delete a Book Cover Image
 */
function storage_delete_book_image(?string $filename): bool {
    if (empty($filename) || $filename === 'default_book.jpg') {
        return true;
    }

    // Protect against directory traversal
    $filename = basename($filename);

    $driver = strtolower(env('STORAGE_DRIVER', 'local'));
    if ($driver === 's3' || $driver === 'r2') {
        $s3 = get_s3_storage_client();
        if ($s3) {
            $s3->deleteObject('book-images/' . $filename);
        }
    }

    // Always attempt local cleanup if file exists locally
    $localFile = dirname(__DIR__) . '/uploads/book-images/' . $filename;
    if (file_exists($localFile) && is_file($localFile)) {
        @unlink($localFile);
    }

    return true;
}

/**
 * Get the Public URL for a Book Cover Image
 *
 * @param string|null $filename Stored filename or URL
 * @param string $relativePrefix Relative path to web root (e.g., '..' for admin/student, '.' for index)
 * @return string Public URL
 */
function storage_get_book_image_url(?string $filename, string $relativePrefix = '..'): string {
    $fallback = rtrim($relativePrefix, '/') . '/uploads/book-images/default_book.jpg';

    if (empty($filename)) {
        return $fallback;
    }

    // Already a complete URL
    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }

    $cleanName = basename($filename);
    $driver = strtolower(env('STORAGE_DRIVER', 'local'));

    // If S3 driver is active and S3 public URL is configured
    if ($driver === 's3' || $driver === 'r2' || env('S3_PUBLIC_URL')) {
        $s3 = get_s3_storage_client();
        if ($s3) {
            // If the file exists locally (e.g. demo images shipped in repo), prefer local on non-serverless
            $localFile = dirname(__DIR__) . '/uploads/book-images/' . $cleanName;
            if (!env('VERCEL') && file_exists($localFile)) {
                return rtrim($relativePrefix, '/') . '/uploads/book-images/' . $cleanName;
            }
            return $s3->getPublicUrl('book-images/' . $cleanName);
        }
    }

    // Local filesystem check
    $localFile = dirname(__DIR__) . '/uploads/book-images/' . $cleanName;
    if (file_exists($localFile)) {
        return rtrim($relativePrefix, '/') . '/uploads/book-images/' . $cleanName;
    }

    return $fallback;
}

