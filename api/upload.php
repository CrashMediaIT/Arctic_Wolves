<?php
/**
 * Streaming Upload Proxy
 *
 * Accepts a raw file body from the browser and streams it directly to
 * RustFS S3 storage.  This acts as a same-origin proxy so the browser
 * never needs to connect to RustFS directly — eliminating CORS and
 * network-reachability issues that prevent presigned URL uploads.
 *
 * Usage (from JavaScript):
 *   fetch('/api/upload.php?key=Images/videos/file.mp4', {
 *       method: 'PUT',
 *       headers: { 'Content-Type': 'video/mp4', 'X-Upload-Token': nonce },
 *       body: file
 *   });
 *
 * Authentication: The caller must provide the X-Upload-Token header whose
 * value matches a nonce stored in the PHP session.  This prevents
 * unauthenticated uploads while still allowing large file streaming
 * without PHP multipart/form-data size limits.
 */

// Allow unlimited execution time for large video uploads
set_time_limit(0);

// Disable output buffering so memory stays low during streaming
while (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/rustfs_storage.php';

// Only allow PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use PUT.']);
    exit;
}

// ── Resolve object key ──────────────────────────────────────────────────
$object_key = $_GET['key'] ?? '';
$object_key = ltrim($object_key, '/');

if ($object_key === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameter: key']);
    exit;
}

// ── Security: prevent path traversal and control characters ─────────────
if (strpos($object_key, '..') !== false || preg_match('/[\x00-\x1f]/', $object_key)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid key']);
    exit;
}

// ── Authenticate via session ────────────────────────────────────────────
session_start();

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Validate upload token (nonce) to prevent CSRF
$upload_token = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
$session_token = $_SESSION['upload_proxy_token'] ?? '';

if (empty($upload_token) || empty($session_token) || !hash_equals($session_token, $upload_token)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid upload token']);
    exit;
}

// ── Validate Content-Length ─────────────────────────────────────────────
$content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($content_length <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Content-Length is required']);
    exit;
}

// 10 GB limit
if ($content_length > 10 * 1024 * 1024 * 1024) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File exceeds 10GB limit']);
    exit;
}

// ── Load RustFS settings ────────────────────────────────────────────────
if (!$db_connected || !$pdo) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

$rustfs = getRustFSSettings($pdo);
if (!isRustFSConfigured($rustfs)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Storage not configured']);
    exit;
}

// ── Stream the upload to RustFS ─────────────────────────────────────────
$content_type = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
$input = fopen('php://input', 'rb');

if ($input === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to open input stream']);
    exit;
}

$result = streamUploadToRustFS($rustfs, $input, $content_length, $object_key, $content_type);
fclose($input);

if ($result['success']) {
    $proxy_url = 'api/media.php?key=' . rawurlencode($object_key);
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'object_key' => $object_key,
        'proxy_url'  => $proxy_url,
    ]);
} else {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'Upload to storage failed: ' . ($result['message'] ?? 'Unknown error'),
    ]);
}
