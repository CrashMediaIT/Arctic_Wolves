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

// Admin-only – include the primary session role (same approach as dashboard.php)
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$roles = [$userRole];
try {
    $roleStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $roleStmt->execute([$userId]);
    $extraRoles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($extraRoles) {
        $roles = array_unique(array_merge($roles, $extraRoles));
    }
} catch (PDOException $e) {
    // user_roles table may not exist yet
}
if (!in_array('admin', $roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// ── Action routing ────────────────────────────────────────────────────
// Multipart actions: initiate, presign_part, complete, abort
// Transcode actions: transcode, transcode_status, delete_original
// Default (no action): single presigned PUT URL for small files
$action = $_POST['action'] ?? '';

if (in_array($action, ['initiate', 'presign_part', 'complete', 'abort'], true)) {
    $tcfg = loadTestRustFSConfig($pdo);

    switch ($action) {
        case 'initiate':     handleTestMultipartInitiate($tcfg); break;
        case 'presign_part': handleTestMultipartPresignPart($tcfg); break;
        case 'complete':     handleTestMultipartComplete($tcfg); break;
        case 'abort':        handleTestMultipartAbort($tcfg); break;
    }
    exit;
}

if (in_array($action, ['transcode', 'transcode_status', 'delete_original', 'retry_transcode'], true)) {
    $tcfg = loadTestRustFSConfig($pdo);

    switch ($action) {
        case 'transcode':        handleTestTranscode($pdo, $tcfg); break;
        case 'transcode_status': handleTestTranscodeStatus($pdo); break;
        case 'delete_original':  handleTestDeleteOriginal($tcfg); break;
        case 'retry_transcode':  handleTestRetryTranscode($pdo, $tcfg); break;
    }
    exit;
}

// ── Input validation (default: single presigned PUT) ──────────────────
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
        . '<AllowedMethod>POST</AllowedMethod>'
        . '<AllowedMethod>DELETE</AllowedMethod>'
        . '<AllowedMethod>HEAD</AllowedMethod>'
        . '<AllowedHeader>*</AllowedHeader>'
        . '<ExposeHeader>ETag</ExposeHeader>'
        . '<MaxAgeSeconds>3600</MaxAgeSeconds>'
        . '</CORSRule>'
        . '</CORSConfiguration>';

    // Content-MD5 is required by S3 spec for PutBucketCors
    $contentMd5 = base64_encode(md5($corsXml, true));

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
        'content-md5'           => $contentMd5,
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
            'Content-MD5: ' . $contentMd5,
            'Authorization: ' . $authHeader,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $bodyHash,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("Video test CORS setup curl error: $curlErr");
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        error_log("Video test CORS setup failed: HTTP $httpCode — " . substr($response, 0, 200));
    }
}

// ═══════════════════════════════════════════════════════════════════════
//  Multipart upload helpers (standalone – no library imports)
// ═══════════════════════════════════════════════════════════════════════

/**
 * Load and validate RustFS configuration from database.
 */
function loadTestRustFSConfig($pdo) {
    $settingKeys = [
        'rustfs_endpoint', 'rustfs_public_endpoint', 'rustfs_access_key',
        'rustfs_secret_key', 'rustfs_bucket', 'rustfs_region',
        'rustfs_use_ssl', 'rustfs_path_style',
    ];
    $placeholders = implode(',', array_fill(0, count($settingKeys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($settingKeys);
    $raw = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw[$row['setting_key']] = $row['setting_value'];
    }
    if (!empty($raw['rustfs_secret_key']) && function_exists('decryptPassword')) {
        $dec = decryptPassword($raw['rustfs_secret_key']);
        if (!empty($dec)) $raw['rustfs_secret_key'] = $dec;
    }
    if (empty($raw['rustfs_endpoint']) || empty($raw['rustfs_access_key'])
        || empty($raw['rustfs_secret_key']) || empty($raw['rustfs_bucket'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'RustFS is not configured']);
        exit;
    }
    return [
        'endpoint'        => rtrim($raw['rustfs_endpoint'], '/'),
        'public_endpoint' => $raw['rustfs_public_endpoint'] ?? '',
        'bucket'          => $raw['rustfs_bucket'],
        'region'          => $raw['rustfs_region'] ?? 'us-east-1',
        'access_key'      => $raw['rustfs_access_key'],
        'secret_key'      => $raw['rustfs_secret_key'],
        'use_ssl'         => ($raw['rustfs_use_ssl']    ?? '1') === '1',
        'path_style'      => ($raw['rustfs_path_style'] ?? '1') === '1',
    ];
}

/**
 * Resolve the internal object URL, encoded path, and browser-facing host.
 * Returns [signingHost, path, urlScheme, urlHost] where signingHost is used
 * for canonical request signing and urlHost/urlScheme for the final URL.
 */
function resolveTestObjectUrl($cfg, $objectKey) {
    $endpoint = $cfg['endpoint'];
    if (strpos($endpoint, 'http://') !== 0 && strpos($endpoint, 'https://') !== 0) {
        $endpoint = ($cfg['use_ssl'] ? 'https://' : 'http://') . $endpoint;
    }
    if ($cfg['path_style']) {
        $objectUrl = $endpoint . '/' . $cfg['bucket'] . '/' . ltrim($objectKey, '/');
    } else {
        $p      = parse_url($endpoint);
        $scheme = $p['scheme'] ?? ($cfg['use_ssl'] ? 'https' : 'http');
        $host   = $p['host']   ?? $endpoint;
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $objectUrl = $scheme . '://' . $cfg['bucket'] . '.' . $host . $port . '/' . ltrim($objectKey, '/');
    }
    $parsed = parse_url($objectUrl);
    $signingHost = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    $rawPath  = $parsed['path'] ?? '/';
    $segments = array_filter(explode('/', $rawPath), 'strlen');
    $path     = '/' . implode('/', array_map('rawurlencode', $segments));
    if ($rawPath !== '/' && substr($rawPath, -1) === '/') {
        $path .= '/';
    }
    if (!empty($cfg['public_endpoint'])) {
        $pub       = parse_url(rtrim($cfg['public_endpoint'], '/'));
        $urlScheme = $pub['scheme'] ?? 'https';
        $urlHost   = $pub['host'] . (isset($pub['port']) ? ':' . $pub['port'] : '');
    } else {
        $urlScheme = $parsed['scheme'] ?? 'https';
        $urlHost   = $signingHost;
    }
    return [$signingHost, $path, $urlScheme, $urlHost];
}

/**
 * Sign and execute an S3 API request (used for initiate, complete, abort).
 */
function signAndExecTestS3($method, $cfg, $objectKey, $queryString, $body, $extraHeaders = []) {
    list($host, $path, $scheme, ) = resolveTestObjectUrl($cfg, $objectKey);
    // For server-side requests always use the internal endpoint, not public
    $now       = new DateTime('UTC');
    $dateStamp = $now->format('Ymd');
    $amzDate   = $now->format('Ymd\THis\Z');
    $payloadHash = is_string($body) ? hash('sha256', $body) : 'UNSIGNED-PAYLOAD';

    $headersToSign = array_merge([
        'host'                 => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date'          => $amzDate,
    ], $extraHeaders);
    ksort($headersToSign);

    $canonicalHeaders = '';
    $signedList       = [];
    foreach ($headersToSign as $k => $v) {
        $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        $signedList[]      = strtolower($k);
    }
    $signedHeaders = implode(';', $signedList);

    $canonicalRequest = implode("\n", [
        $method, $path, $queryString, $canonicalHeaders, $signedHeaders, $payloadHash,
    ]);

    $credentialScope = $dateStamp . '/' . $cfg['region'] . '/s3/aws4_request';
    $stringToSign    = implode("\n", [
        'AWS4-HMAC-SHA256', $amzDate, $credentialScope, hash('sha256', $canonicalRequest),
    ]);

    $kDate    = hash_hmac('sha256', $dateStamp,      'AWS4' . $cfg['secret_key'], true);
    $kRegion  = hash_hmac('sha256', $cfg['region'],   $kDate,                      true);
    $kService = hash_hmac('sha256', 's3',             $kRegion,                    true);
    $kSigning = hash_hmac('sha256', 'aws4_request',   $kService,                   true);
    $signature = hash_hmac('sha256', $stringToSign,   $kSigning);

    $auth = sprintf(
        'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $cfg['access_key'], $credentialScope, $signedHeaders, $signature
    );

    $url = $scheme . '://' . $host . $path;
    if (!empty($queryString)) $url .= '?' . $queryString;

    $curlHeaders = [
        'Host: '                 . $host,
        'Authorization: '        . $auth,
        'x-amz-date: '          . $amzDate,
        'x-amz-content-sha256: ' . $payloadHash,
    ];
    foreach ($extraHeaders as $k => $v) {
        if (!in_array(strtolower($k), ['host', 'x-amz-content-sha256', 'x-amz-date'])) {
            $curlHeaders[] = $k . ': ' . $v;
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     $curlHeaders);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT,        300);
    $respHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$respHeaders) {
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($header);
    });
    if (is_string($body) && strlen($body) > 0) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return ['http_code' => $httpCode, 'body' => $response, 'error' => $curlErr, 'headers' => $respHeaders];
}

/**
 * Action: Initiate a multipart upload.
 */
function handleTestMultipartInitiate($cfg) {
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
        'mp4'  => 'video/mp4',  'mkv' => 'video/x-matroska',
        'mov'  => 'video/quicktime', 'avi' => 'video/x-msvideo', 'webm' => 'video/webm',
    ];
    $allowedMimes = array_merge(array_values($mimeMap), ['video/avi', 'video/matroska']);
    if (!in_array($fileType, $allowedMimes, true)) {
        $fileType = $mimeMap[$ext] ?? 'application/octet-stream';
    }

    $objectKey = 'Images/videos/test/' . uniqid('vtest_', true) . '_' . time() . '.' . $ext;

    ensureCors($cfg['endpoint'], $cfg['bucket'], $cfg['region'],
               $cfg['access_key'], $cfg['secret_key'], $cfg['use_ssl'], $cfg['path_style']);

    $result = signAndExecTestS3('POST', $cfg, $objectKey, 'uploads=', '', [
        'content-type' => $fileType,
    ]);

    if ($result['http_code'] !== 200) {
        error_log("Video test multipart initiate failed: HTTP {$result['http_code']} body=" . substr($result['body'], 0, 500));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Failed to initiate multipart upload: HTTP ' . $result['http_code']]);
        exit;
    }
    if (!preg_match('/<UploadId>([^<]+)<\/UploadId>/', $result['body'], $m)) {
        error_log("Video test multipart initiate: no UploadId in response: " . substr($result['body'], 0, 500));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Could not parse UploadId from RustFS response']);
        exit;
    }
    error_log("Video test multipart: initiated upload_id={$m[1]} key=$objectKey size=$fileSize");

    echo json_encode([
        'success'      => true,
        'upload_id'    => $m[1],
        'object_key'   => $objectKey,
        'content_type' => $fileType,
    ]);
    exit;
}

/**
 * Action: Generate a presigned URL for one part of a multipart upload.
 */
function handleTestMultipartPresignPart($cfg) {
    $objectKey  = $_POST['object_key']  ?? '';
    $uploadId   = $_POST['upload_id']   ?? '';
    $partNumber = filter_input(INPUT_POST, 'part_number', FILTER_VALIDATE_INT);

    if ($objectKey === '' || $uploadId === '' || !$partNumber || $partNumber < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key, upload_id, and part_number are required']);
        exit;
    }
    if (strpos($objectKey, 'Images/videos/test/vtest_') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid object key']);
        exit;
    }

    list($signingHost, $path, $urlScheme, $urlHost) = resolveTestObjectUrl($cfg, $objectKey);

    $expires   = 3600;
    $now       = new DateTime('UTC');
    $dateStamp = $now->format('Ymd');
    $amzDate   = $now->format('Ymd\THis\Z');

    $credentialScope = $dateStamp . '/' . $cfg['region'] . '/s3/aws4_request';
    $credential      = $cfg['access_key'] . '/' . $credentialScope;

    // All query-string params (including partNumber + uploadId) in alphabetical order
    $qsParams = [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => $credential,
        'X-Amz-Date'          => $amzDate,
        'X-Amz-Expires'       => (string)$expires,
        'X-Amz-SignedHeaders'  => 'host',
        'partNumber'           => (string)$partNumber,
        'uploadId'             => $uploadId,
    ];
    ksort($qsParams);
    $canonicalQS = http_build_query($qsParams, '', '&', PHP_QUERY_RFC3986);

    // For presigned URLs the browser sends the request, so sign with the
    // browser-facing host (public endpoint when configured).
    $canonicalHeaders = 'host:' . $urlHost . "\n";
    $signedHeaders    = 'host';
    $payloadHash      = 'UNSIGNED-PAYLOAD';

    $canonicalRequest = implode("\n", [
        'PUT', $path, $canonicalQS, $canonicalHeaders, $signedHeaders, $payloadHash,
    ]);
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256', $amzDate, $credentialScope, hash('sha256', $canonicalRequest),
    ]);

    $kDate    = hash_hmac('sha256', $dateStamp,      'AWS4' . $cfg['secret_key'], true);
    $kRegion  = hash_hmac('sha256', $cfg['region'],   $kDate,                      true);
    $kService = hash_hmac('sha256', 's3',             $kRegion,                    true);
    $kSigning = hash_hmac('sha256', 'aws4_request',   $kService,                   true);
    $signature = hash_hmac('sha256', $stringToSign,   $kSigning);

    $presignedUrl = $urlScheme . '://' . $urlHost . $path
        . '?' . $canonicalQS
        . '&X-Amz-Signature=' . $signature;

    echo json_encode([
        'success'       => true,
        'presigned_url' => $presignedUrl,
        'part_number'   => $partNumber,
    ]);
    exit;
}

/**
 * Action: Complete a multipart upload.
 */
function handleTestMultipartComplete($cfg) {
    $objectKey = $_POST['object_key'] ?? '';
    $uploadId  = $_POST['upload_id']  ?? '';
    $partsJson = $_POST['parts']      ?? '';

    if ($objectKey === '' || $uploadId === '' || $partsJson === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key, upload_id, and parts are required']);
        exit;
    }
    if (strpos($objectKey, 'Images/videos/test/vtest_') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid object key']);
        exit;
    }

    $parts = json_decode($partsJson, true);
    if (!is_array($parts) || empty($parts)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parts data']);
        exit;
    }

    $xml = '<CompleteMultipartUpload>';
    foreach ($parts as $p) {
        $partNum = (int)($p['PartNumber'] ?? 0);
        $etag    = $p['ETag'] ?? '';
        if ($partNum < 1 || $etag === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each part must have PartNumber and ETag']);
            exit;
        }
        $xml .= '<Part><PartNumber>' . $partNum . '</PartNumber><ETag>"'
              . htmlspecialchars($etag, ENT_XML1, 'UTF-8') . '"</ETag></Part>';
    }
    $xml .= '</CompleteMultipartUpload>';

    $qs     = 'uploadId=' . rawurlencode($uploadId);
    $result = signAndExecTestS3('POST', $cfg, $objectKey, $qs, $xml, [
        'content-type' => 'application/xml',
    ]);

    if ($result['http_code'] !== 200 || strpos($result['body'], '<Error>') !== false) {
        error_log("Video test multipart complete failed: HTTP {$result['http_code']} body=" . substr($result['body'], 0, 500));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'CompleteMultipartUpload failed: HTTP ' . $result['http_code']]);
        exit;
    }

    error_log("Video test multipart: COMPLETE key=$objectKey parts=" . count($parts) . " upload_id=$uploadId");
    echo json_encode(['success' => true, 'object_key' => $objectKey]);
    exit;
}

/**
 * Action: Abort a multipart upload (cleanup).
 */
function handleTestMultipartAbort($cfg) {
    $objectKey = $_POST['object_key'] ?? '';
    $uploadId  = $_POST['upload_id']  ?? '';

    if ($objectKey === '' || $uploadId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key and upload_id are required']);
        exit;
    }
    if (strpos($objectKey, 'Images/videos/test/vtest_') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid object key']);
        exit;
    }

    $qs = 'uploadId=' . rawurlencode($uploadId);
    signAndExecTestS3('DELETE', $cfg, $objectKey, $qs, '');

    error_log("Video test multipart: aborted upload_id=$uploadId key=$objectKey");
    echo json_encode(['success' => true]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════
//  Transcode helpers (trigger companion, poll status, delete original)
// ═══════════════════════════════════════════════════════════════════════

/**
 * Action: Trigger HLS transcode via companion app.
 */
function handleTestTranscode($pdo, $cfg) {
    $objectKey = $_POST['object_key'] ?? '';

    if ($objectKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key is required']);
        exit;
    }
    if (strpos($objectKey, 'Images/videos/test/vtest_') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid object key']);
        exit;
    }

    // Load companion settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key', 'gameplan_app_url')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'] ?? '';
    }
    $companionUrl = $settings['gameplan_companion_url'] ?? '';
    $companionKey = $settings['gameplan_companion_api_key'] ?? '';
    $appUrl       = $settings['gameplan_app_url'] ?? '';

    if (empty($companionUrl)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Companion server is not configured. Set companion URL in Gameplan Settings.']);
        exit;
    }

    $companionUrl = rtrim($companionUrl, '/');

    // Build output prefix: same directory as source, named after source file, /hls subfolder
    $hlsPrefix    = pathinfo($objectKey, PATHINFO_FILENAME);
    $hlsDir       = pathinfo($objectKey, PATHINFO_DIRNAME);
    $outputPrefix = $hlsDir . '/' . $hlsPrefix . '/hls';

    // Build callback URL
    $callbackUrl = '';
    if (!empty($appUrl)) {
        $callbackUrl = rtrim($appUrl, '/') . '/api/v1/companion/callback';
    }

    $payload = json_encode([
        'source_key'      => $objectKey,
        'output_prefix'   => $outputPrefix,
        'delete_original' => false, // We handle deletion ourselves after verifying
        'callback_url'    => $callbackUrl,
    ]);

    $ch = curl_init($companionUrl . '/api/hls');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $companionKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("Video test transcode trigger curl error: $curlErr");
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Could not reach companion server: ' . $curlErr]);
        exit;
    }

    if ($httpCode !== 202) {
        $body = $response ? substr($response, 0, 500) : '(empty)';
        error_log("Video test transcode trigger failed: HTTP $httpCode — $body");
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Companion returned HTTP ' . $httpCode]);
        exit;
    }

    $data = json_decode($response, true) ?: [];
    $jobId = $data['id'] ?? '';

    // Store job tracking in session so we can poll status
    $_SESSION['vt_transcode_job'] = [
        'job_id'        => $jobId,
        'object_key'    => $objectKey,
        'output_prefix' => $outputPrefix,
        'companion_url' => $companionUrl,
        'companion_key' => $companionKey,
        'started_at'    => time(),
    ];

    error_log("Video test transcode: triggered job_id=$jobId key=$objectKey output=$outputPrefix");

    echo json_encode([
        'success'       => true,
        'job_id'        => $jobId,
        'output_prefix' => $outputPrefix,
    ]);
    exit;
}

/**
 * Action: Poll companion for transcode job status.
 */
function handleTestTranscodeStatus($pdo) {
    $job = $_SESSION['vt_transcode_job'] ?? null;
    $jobId = $_POST['job_id'] ?? ($job['job_id'] ?? '');

    if (empty($job) && empty($jobId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No transcode job in progress']);
        exit;
    }

    // Load companion settings if not in session
    $companionUrl = $job['companion_url'] ?? '';
    $companionKey = $job['companion_key'] ?? '';
    $outputPrefix = $job['output_prefix'] ?? '';

    if (empty($companionUrl)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key')");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'gameplan_companion_url') $companionUrl = rtrim($row['setting_value'] ?? '', '/');
            if ($row['setting_key'] === 'gameplan_companion_api_key') $companionKey = $row['setting_value'] ?? '';
        }
    }

    if (empty($companionUrl) || empty($jobId)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Companion not configured or no job ID']);
        exit;
    }

    // GET /api/job/<job_id> on the companion
    $ch = curl_init($companionUrl . '/api/job/' . rawurlencode($jobId));
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $companionKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Companion unreachable: ' . $curlErr]);
        exit;
    }

    $data = json_decode($response, true) ?: [];
    $status = $data['status'] ?? 'unknown';

    $result = [
        'success'       => true,
        'status'        => $status,
        'output_prefix' => $outputPrefix,
    ];

    // Always forward the job log if present (for both success and failure)
    if (isset($data['log']) && is_array($data['log'])) {
        $result['log'] = $data['log'];
    }

    if ($status === 'completed') {
        $hlsManifest = $data['hls_manifest'] ?? ($outputPrefix . '/master.m3u8');
        $result['hls_url'] = 'api/media.php?key=' . rawurlencode($hlsManifest);
        $result['hls_manifest'] = $hlsManifest;
        // Clean up session tracking
        unset($_SESSION['vt_transcode_job']);
    } elseif ($status === 'failed') {
        $result['error'] = $data['error'] ?? 'Unknown error';
        unset($_SESSION['vt_transcode_job']);
    }

    echo json_encode($result);
    exit;
}

/**
 * Action: Delete the original uploaded file from S3 after transcode success.
 */
function handleTestDeleteOriginal($cfg) {
    $objectKey = $_POST['object_key'] ?? '';

    if ($objectKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key is required']);
        exit;
    }
    if (strpos($objectKey, 'Images/videos/test/vtest_') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid object key']);
        exit;
    }

    $result = signAndExecTestS3('DELETE', $cfg, $objectKey, '', '');

    if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
        error_log("Video test: deleted original file key=$objectKey");
        echo json_encode(['success' => true]);
    } else {
        error_log("Video test: failed to delete original key=$objectKey HTTP={$result['http_code']}");
        echo json_encode(['success' => false, 'error' => 'Delete failed: HTTP ' . $result['http_code']]);
    }
    exit;
}

/**
 * Action: Retry a failed HLS transcode job.
 */
function handleTestRetryTranscode($pdo, $cfg) {
    $objectKey    = $_POST['object_key'] ?? '';
    $oldJobId     = $_POST['job_id'] ?? '';
    $outputPrefix = $_POST['output_prefix'] ?? '';

    if (empty($objectKey) && empty($oldJobId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'object_key or job_id is required']);
        exit;
    }

    // Load companion settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key', 'gameplan_app_url')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'] ?? '';
    }
    $companionUrl = rtrim($settings['gameplan_companion_url'] ?? '', '/');
    $companionKey = $settings['gameplan_companion_api_key'] ?? '';
    $appUrl       = $settings['gameplan_app_url'] ?? '';

    if (empty($companionUrl)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Companion server is not configured.']);
        exit;
    }

    // Build output prefix if not supplied
    if (empty($outputPrefix) && !empty($objectKey)) {
        $hlsPrefix    = pathinfo($objectKey, PATHINFO_FILENAME);
        $hlsDir       = pathinfo($objectKey, PATHINFO_DIRNAME);
        $outputPrefix = $hlsDir . '/' . $hlsPrefix . '/hls';
    }

    $callbackUrl = '';
    if (!empty($appUrl)) {
        $callbackUrl = rtrim($appUrl, '/') . '/api/v1/companion/callback';
    }

    $payload = json_encode([
        'job_id'          => $oldJobId,
        'source_key'      => $objectKey,
        'output_prefix'   => $outputPrefix,
        'delete_original' => false,
        'callback_url'    => $callbackUrl,
    ]);

    $ch = curl_init($companionUrl . '/api/hls/retry');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $companionKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("Video test retry transcode curl error: $curlErr");
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Could not reach companion server: ' . $curlErr]);
        exit;
    }

    if ($httpCode !== 202) {
        $body = $response ? substr($response, 0, 500) : '(empty)';
        error_log("Video test retry transcode failed: HTTP $httpCode — $body");
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Companion returned HTTP ' . $httpCode]);
        exit;
    }

    $data = json_decode($response, true) ?: [];
    $jobId = $data['id'] ?? '';

    // Store job tracking in session so we can poll status
    $_SESSION['vt_transcode_job'] = [
        'job_id'        => $jobId,
        'object_key'    => $objectKey,
        'output_prefix' => $outputPrefix,
        'companion_url' => $companionUrl,
        'companion_key' => $companionKey,
        'started_at'    => time(),
    ];

    error_log("Video test retry transcode: triggered job_id=$jobId key=$objectKey output=$outputPrefix");

    echo json_encode([
        'success'       => true,
        'job_id'        => $jobId,
        'output_prefix' => $outputPrefix,
    ]);
    exit;
}
