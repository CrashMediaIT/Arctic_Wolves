<?php
/**
 * Video Upload Test – Standalone Backend
 *
 * Self-contained presigned-URL generator for the admin Video Test page.
 * Reads RustFS / S3 settings straight from the database, signs a PUT URL
 * using AWS Signature V4, and returns it to the browser.  The browser then
 * uploads the file directly to RustFS — no PHP proxy, no companion app.
 *
 * This file intentionally does NOT require cloud_config.php,
 * lib/rustfs_storage.php, or process_video.php.
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf_protection.php';

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF check for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

// Admin-only
$userId = $_SESSION['user_id'];
$roleStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
$roleStmt->execute([$userId]);
$roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('admin', $roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────
$fileName = $_POST['file_name'] ?? '';
$fileSize = filter_input(INPUT_POST, 'file_size', FILTER_VALIDATE_INT);
$fileType = $_POST['file_type'] ?? '';

if ($fileName === '' || !$fileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'file_name and file_size are required']);
    exit;
}

if ($fileSize > 10 * 1024 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File exceeds 10 GB limit']);
    exit;
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExt = ['mp4', 'mkv', 'mov', 'avi', 'webm'];
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExt)]);
    exit;
}

$mimeMap = [
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'webm' => 'video/webm',
];
$allowedMimes = array_merge(array_values($mimeMap), ['video/avi']);
if (!in_array($fileType, $allowedMimes, true)) {
    $fileType = $mimeMap[$ext] ?? 'application/octet-stream';
}

// ── Load RustFS settings from database ────────────────────────────────
$settingKeys = [
    'rustfs_endpoint',
    'rustfs_public_endpoint',
    'rustfs_access_key',
    'rustfs_secret_key',
    'rustfs_bucket',
    'rustfs_region',
    'rustfs_use_ssl',
    'rustfs_path_style',
];
$placeholders = implode(',', array_fill(0, count($settingKeys), '?'));
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
$stmt->execute($settingKeys);
$cfg = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cfg[$row['setting_key']] = $row['setting_value'];
}

// Decrypt secret key if the helper exists (security.php provides decryptPassword)
if (!empty($cfg['rustfs_secret_key']) && function_exists('decryptPassword')) {
    $dec = decryptPassword($cfg['rustfs_secret_key']);
    if (!empty($dec)) {
        $cfg['rustfs_secret_key'] = $dec;
    }
}

// Ensure minimum config is present
if (empty($cfg['rustfs_endpoint']) || empty($cfg['rustfs_access_key'])
    || empty($cfg['rustfs_secret_key']) || empty($cfg['rustfs_bucket'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'RustFS is not configured']);
    exit;
}

$endpoint  = rtrim($cfg['rustfs_endpoint'], '/');
$bucket    = $cfg['rustfs_bucket'];
$region    = $cfg['rustfs_region']    ?? 'us-east-1';
$accessKey = $cfg['rustfs_access_key'];
$secretKey = $cfg['rustfs_secret_key'];
$useSsl    = ($cfg['rustfs_use_ssl']    ?? '1') === '1';
$pathStyle = ($cfg['rustfs_path_style'] ?? '1') === '1';

// ── Ensure CORS on the bucket ─────────────────────────────────────────
// Without this the browser's XHR PUT will be blocked by CORS policy.
ensureCors($endpoint, $bucket, $region, $accessKey, $secretKey, $useSsl, $pathStyle);

// ── Build the presigned PUT URL (AWS Signature V4) ────────────────────
$objectKey = 'Images/videos/test/' . uniqid('vtest_', true) . '_' . time() . '.' . $ext;

// Resolve the full object URL
if (strpos($endpoint, 'http://') !== 0 && strpos($endpoint, 'https://') !== 0) {
    $endpoint = ($useSsl ? 'https://' : 'http://') . $endpoint;
}
if ($pathStyle) {
    $objectUrl = $endpoint . '/' . $bucket . '/' . ltrim($objectKey, '/');
} else {
    $parsed = parse_url($endpoint);
    $scheme = $parsed['scheme'] ?? ($useSsl ? 'https' : 'http');
    $host   = $parsed['host']   ?? $endpoint;
    $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $objectUrl = $scheme . '://' . $bucket . '.' . $host . $port . '/' . ltrim($objectKey, '/');
}

$parsed = parse_url($objectUrl);

// URI-encode each path segment (keep '/' unencoded)
$rawPath  = $parsed['path'] ?? '/';
$segments = array_filter(explode('/', $rawPath), 'strlen');
$path     = '/' . implode('/', array_map('rawurlencode', $segments));
if ($rawPath !== '/' && substr($rawPath, -1) === '/') {
    $path .= '/';
}

// If a public (browser-facing) endpoint is configured, swap scheme+host
if (!empty($cfg['rustfs_public_endpoint'])) {
    $pub    = parse_url(rtrim($cfg['rustfs_public_endpoint'], '/'));
    $scheme = $pub['scheme'] ?? 'https';
    $host   = $pub['host'] . (isset($pub['port']) ? ':' . $pub['port'] : '');
} else {
    $scheme = $parsed['scheme'] ?? 'https';
    $host   = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
}

$expires = 3600;
$now       = new DateTime('UTC');
$dateStamp = $now->format('Ymd');
$amzDate   = $now->format('Ymd\THis\Z');

$credentialScope = $dateStamp . '/' . $region . '/s3/aws4_request';
$credential      = $accessKey . '/' . $credentialScope;

// Canonical query-string parameters (alphabetical order)
$qsParams = [
    'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
    'X-Amz-Credential'    => $credential,
    'X-Amz-Date'          => $amzDate,
    'X-Amz-Expires'       => (string)$expires,
    'X-Amz-SignedHeaders'  => 'host',
];
ksort($qsParams);
$canonicalQS = http_build_query($qsParams, '', '&', PHP_QUERY_RFC3986);

// Canonical headers — only 'host' is signed (matches AWS SDK behaviour for presigned PUT)
$canonicalHeaders = 'host:' . $host . "\n";
$signedHeaders    = 'host';
$payloadHash      = 'UNSIGNED-PAYLOAD';

$canonicalRequest = implode("\n", [
    'PUT',
    $path,
    $canonicalQS,
    $canonicalHeaders,
    $signedHeaders,
    $payloadHash,
]);

$stringToSign = implode("\n", [
    'AWS4-HMAC-SHA256',
    $amzDate,
    $credentialScope,
    hash('sha256', $canonicalRequest),
]);

// Derive signing key
$kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
$kRegion  = hash_hmac('sha256', $region,    $kDate,              true);
$kService = hash_hmac('sha256', 's3',       $kRegion,            true);
$kSigning = hash_hmac('sha256', 'aws4_request', $kService,       true);

$signature = hash_hmac('sha256', $stringToSign, $kSigning);

$presignedUrl = $scheme . '://' . $host . $path
    . '?' . $canonicalQS
    . '&X-Amz-Signature=' . $signature;

echo json_encode([
    'success'       => true,
    'presigned_url' => $presignedUrl,
    'object_key'    => $objectKey,
    'content_type'  => $fileType,
]);
exit;

// ═══════════════════════════════════════════════════════════════════════
//  Helper: ensure CORS is set on the bucket so the browser can PUT
// ═══════════════════════════════════════════════════════════════════════
function ensureCors($endpoint, $bucket, $region, $accessKey, $secretKey, $useSsl, $pathStyle) {
    // Ensure scheme
    if (strpos($endpoint, 'http://') !== 0 && strpos($endpoint, 'https://') !== 0) {
        $endpoint = ($useSsl ? 'https://' : 'http://') . $endpoint;
    }

    // Build bucket-level CORS URL
    if ($pathStyle) {
        $url = $endpoint . '/' . $bucket . '/?cors';
    } else {
        $p      = parse_url($endpoint);
        $scheme = $p['scheme'] ?? ($useSsl ? 'https' : 'http');
        $host   = $p['host']   ?? $endpoint;
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $url    = $scheme . '://' . $bucket . '.' . $host . $port . '/?cors';
    }

    $corsXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<CORSConfiguration>'
        . '<CORSRule>'
        . '<AllowedOrigin>*</AllowedOrigin>'
        . '<AllowedMethod>GET</AllowedMethod>'
        . '<AllowedMethod>PUT</AllowedMethod>'
        . '<AllowedMethod>HEAD</AllowedMethod>'
        . '<AllowedHeader>*</AllowedHeader>'
        . '<ExposeHeader>ETag</ExposeHeader>'
        . '<MaxAgeSeconds>3600</MaxAgeSeconds>'
        . '</CORSRule>'
        . '</CORSConfiguration>';

    // Sign the PutBucketCors request (AWS Signature V4, full-body signing)
    $parsed = parse_url($url);
    $host   = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    $query  = $parsed['query'] ?? '';
    $rawPath  = $parsed['path'] ?? '/';
    $segments = array_filter(explode('/', $rawPath), 'strlen');
    $path     = '/' . implode('/', array_map('rawurlencode', $segments));
    if ($rawPath !== '/' && substr($rawPath, -1) === '/') {
        $path .= '/';
    }

    $now       = new DateTime('UTC');
    $dateStamp = $now->format('Ymd');
    $amzDate   = $now->format('Ymd\THis\Z');
    $bodyHash  = hash('sha256', $corsXml);

    $headers = [
        'content-type'          => 'application/xml',
        'host'                  => $host,
        'x-amz-content-sha256' => $bodyHash,
        'x-amz-date'           => $amzDate,
    ];
    ksort($headers);

    $canonicalHeaders = '';
    $signedList       = [];
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= $k . ':' . $v . "\n";
        $signedList[]      = $k;
    }
    $signedHeaders = implode(';', $signedList);

    $canonicalQS = '';
    if (!empty($query)) {
        parse_str($query, $qp);
        ksort($qp);
        $canonicalQS = http_build_query($qp, '', '&', PHP_QUERY_RFC3986);
    }

    $canonicalRequest = implode("\n", [
        'PUT', $path, $canonicalQS, $canonicalHeaders, $signedHeaders, $bodyHash,
    ]);

    $credScope   = $dateStamp . '/' . $region . '/s3/aws4_request';
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256', $amzDate, $credScope, hash('sha256', $canonicalRequest),
    ]);

    $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion  = hash_hmac('sha256', $region,    $kDate,              true);
    $kService = hash_hmac('sha256', 's3',       $kRegion,            true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService,       true);
    $sig      = hash_hmac('sha256', $stringToSign, $kSigning);

    $authHeader = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $credScope
        . ', SignedHeaders=' . $signedHeaders
        . ', Signature=' . $sig;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $corsXml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/xml',
            'Authorization: ' . $authHeader,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $bodyHash,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
    // Best-effort — if it fails the browser PUT will simply show a CORS error
}
