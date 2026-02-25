<?php
/**
 * Serve File from Garage S3 Storage
 * 
 * Proxies files from Garage S3 to the browser via pre-signed URLs (redirect)
 * or direct streaming (proxy mode). This replaces local file serving since
 * no files are stored inside the Arctic_Wolves directory.
 *
 * Usage: serve_file.php?path=profiles/photo.jpg
 *        serve_file.php?path=videos/coach/video.mp4&video=1
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';

// Basic authentication check — must be logged in
session_start();
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$objectKey = $_GET['path'] ?? '';
$isVideo   = !empty($_GET['video']);

if (empty($objectKey)) {
    http_response_code(400);
    exit('Missing path parameter');
}

// Sanitize: prevent path traversal
$objectKey = str_replace(['..', "\0"], '', $objectKey);
$objectKey = ltrim($objectKey, '/');

// Generate a pre-signed URL and redirect the client to it
$url = getGarageFileUrl($pdo, $objectKey, 3600, $isVideo);

if ($url) {
    header('Location: ' . $url, true, 302);
    header('Cache-Control: private, max-age=3500');
    exit;
}

// Fallback: try to serve from local file system (backward compatibility)
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : realpath(__DIR__);
$local_path = $project_root . '/' . $objectKey;

if (file_exists($local_path) && is_file($local_path) && strpos(realpath($local_path), realpath($project_root)) === 0) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $local_path);
    finfo_close($finfo);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($local_path));
    header('Cache-Control: public, max-age=86400');
    readfile($local_path);
    exit;
}

http_response_code(404);
exit('File not found');
?>
