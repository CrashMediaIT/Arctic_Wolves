<?php
/**
 * RustFS Media Proxy
 *
 * Serves files stored in RustFS S3 through the application server.
 * The browser hits this endpoint; it authenticates to RustFS, downloads the
 * object, and streams it back with proper Content-Type and caching headers.
 *
 * Usage:
 *   /api/media.php?key=Images/profiles/avatar.jpg
 *
 * The object key can also be passed via PATH_INFO for cleaner URLs:
 *   /api/media.php/Images/profiles/avatar.jpg
 */

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../lib/rustfs_storage.php';

// ── Resolve object key ──────────────────────────────────────────────────
$object_key = '';

if (!empty($_GET['key'])) {
    $object_key = $_GET['key'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $object_key = ltrim($_SERVER['PATH_INFO'], '/');
}

$object_key = ltrim($object_key, '/');

if ($object_key === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameter: key']);
    exit;
}

// ── Security: prevent path traversal and control characters ─────────────
if (strpos($object_key, '..') !== false || preg_match('/[\x00-\x1f]/', $object_key)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

// ── Load RustFS settings ────────────────────────────────────────────────
if (!$db_connected || !$pdo) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

$rustfs = getRustFSSettings($pdo);

if (!isRustFSConfigured($rustfs)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Storage not configured']);
    exit;
}

// ── Build the authenticated GET request to RustFS ───────────────────────
$object_key_clean = ltrim($object_key, '/');
$url = getRustFSPublicUrl($rustfs, $object_key_clean);
$region = $rustfs['rustfs_region'] ?? 'us-east-1';

$signed = signRustFSRequest(
    'GET',
    $url,
    [],
    '',
    $rustfs['rustfs_access_key'],
    $rustfs['rustfs_secret_key'],
    $region
);

$curl_headers = [
    'Authorization: ' . $signed['Authorization'],
    'x-amz-date: ' . $signed['x-amz-date'],
    'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
];

// ── Fetch and stream ────────────────────────────────────────────────────
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
// Matches existing RustFS storage pattern (self-signed certs in internal networks)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Capture response headers for content-type
$response_headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$response_headers) {
    $len = strlen($header);
    $parts = explode(':', $header, 2);
    if (count($parts) === 2) {
        $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
    }
    return $len;
});

$body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if (!empty($curl_error)) {
    error_log("media.php proxy error for key=$object_key_clean: $curl_error");
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Storage connection error']);
    exit;
}

if ($http_code === 404) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

if ($http_code !== 200) {
    error_log("media.php proxy: RustFS returned HTTP $http_code for key=$object_key_clean");
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Storage error']);
    exit;
}

// ── Determine content type ──────────────────────────────────────────────
$content_type = $response_headers['content-type'] ?? null;

if (empty($content_type) || $content_type === 'application/octet-stream') {
    // Guess from extension
    $ext = strtolower(pathinfo($object_key_clean, PATHINFO_EXTENSION));
    $mime_map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'pdf'  => 'application/pdf',
        'json' => 'application/json',
        'txt'  => 'text/plain',
    ];
    $content_type = $mime_map[$ext] ?? 'application/octet-stream';
}

// ── Send response ───────────────────────────────────────────────────────
header('Content-Type: ' . $content_type);
header('Content-Length: ' . strlen($body));
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
header('X-Content-Type-Options: nosniff');

// Allow CORS for same-site subdomains
header('Access-Control-Allow-Origin: *');

echo $body;
