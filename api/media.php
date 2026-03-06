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
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/rustfs_storage.php';

// Handle CORS preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Range');
    header('Access-Control-Max-Age: 86400');
    exit;
}

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
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode(['error' => 'Missing required parameter: key']);
    exit;
}

// ── Security: prevent path traversal and control characters ─────────────
if (strpos($object_key, '..') !== false || preg_match('/[\x00-\x1f]/', $object_key)) {
    http_response_code(400);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

// ── Load RustFS settings ────────────────────────────────────────────────
if (!$db_connected || !$pdo) {
    http_response_code(503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

$rustfs = getRustFSSettings($pdo);

if (!isRustFSConfigured($rustfs)) {
    http_response_code(503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
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
// Support HTTP Range requests for progressive video playback
$range_header = $_SERVER['HTTP_RANGE'] ?? '';

if ($range_header) {
    // ---------- Range request (video seeking / progressive play) ----------
    // First, get the object size via a HEAD request
    $head_url = $url;
    $head_signed = signRustFSRequest(
        'HEAD',
        $head_url,
        [],
        '',
        $rustfs['rustfs_access_key'],
        $rustfs['rustfs_secret_key'],
        $region
    );
    $head_headers = [
        'Authorization: ' . $head_signed['Authorization'],
        'x-amz-date: ' . $head_signed['x-amz-date'],
        'x-amz-content-sha256: ' . $head_signed['x-amz-content-sha256'],
    ];

    $ch_head = curl_init($head_url);
    curl_setopt($ch_head, CURLOPT_NOBODY, true);
    curl_setopt($ch_head, CURLOPT_HTTPHEADER, $head_headers);
    curl_setopt($ch_head, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_head, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch_head, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch_head, CURLOPT_FOLLOWLOCATION, true);

    $head_resp_headers = [];
    curl_setopt($ch_head, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$head_resp_headers) {
        $len = strlen($header);
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $head_resp_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return $len;
    });

    curl_exec($ch_head);
    $head_code = curl_getinfo($ch_head, CURLINFO_HTTP_CODE);
    curl_close($ch_head);

    if ($head_code !== 200) {
        http_response_code(502);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        _media_cors_headers();
        echo json_encode(['error' => 'Storage error']);
        exit;
    }

    $total_size = (int)($head_resp_headers['content-length'] ?? 0);
    $s3_content_type = $head_resp_headers['content-type'] ?? null;

    if ($total_size <= 0) {
        http_response_code(502);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        _media_cors_headers();
        echo json_encode(['error' => 'Unable to determine file size']);
        exit;
    }

    // Parse Range header (e.g. bytes=0-999)
    $range_start = 0;
    $range_end = $total_size - 1;
    if (preg_match('/bytes=(\d+)-(\d*)/', $range_header, $m)) {
        $range_start = (int)$m[1];
        if ($m[2] !== '') {
            $range_end = (int)$m[2];
        }
    }

    if ($range_start > $range_end || $range_start >= $total_size) {
        http_response_code(416);
        header("Content-Range: bytes */$total_size");
        exit;
    }

    // Fetch the requested byte range from RustFS
    $range_signed = signRustFSRequest(
        'GET',
        $url,
        [],
        '',
        $rustfs['rustfs_access_key'],
        $rustfs['rustfs_secret_key'],
        $region
    );
    $range_curl_headers = [
        'Authorization: ' . $range_signed['Authorization'],
        'x-amz-date: ' . $range_signed['x-amz-date'],
        'x-amz-content-sha256: ' . $range_signed['x-amz-content-sha256'],
        "Range: bytes=$range_start-$range_end",
    ];

    // Determine content type
    $ct = $s3_content_type;
    if (empty($ct) || $ct === 'application/octet-stream') {
        $ext = strtolower(pathinfo($object_key_clean, PATHINFO_EXTENSION));
        $mime_map = [
            'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg',  'png'  => 'image/png',
            'gif'  => 'image/gif',   'webp' => 'image/webp',  'svg'  => 'image/svg+xml',
            'mp4'  => 'video/mp4',   'webm' => 'video/webm',  'mov'  => 'video/quicktime',
            'avi'  => 'video/x-msvideo', 'pdf' => 'application/pdf',
            'm3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t',
        ];
        $ct = $mime_map[$ext] ?? 'application/octet-stream';
    }

    $content_length = $range_end - $range_start + 1;
    http_response_code(206);
    header('Content-Type: ' . $ct);
    header('Content-Length: ' . $content_length);
    header("Content-Range: bytes $range_start-$range_end/$total_size");
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Range');
    header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');

    // Stream directly to output
    $ch_range = curl_init($url);
    curl_setopt($ch_range, CURLOPT_HTTPHEADER, $range_curl_headers);
    curl_setopt($ch_range, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_range, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch_range, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch_range, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch_range, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch_range, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
        echo $data;
        if (ob_get_level()) ob_flush();
        flush();
        return strlen($data);
    });
    curl_exec($ch_range);
    curl_close($ch_range);
    exit;
}

// ---------- Full request (non-range) ----------

// Helper: common CORS + cache headers
function _media_cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Range');
    header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');
}

// Common MIME map shared by both streaming and buffered paths
$mime_map = [
    'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg',  'png'  => 'image/png',
    'gif'  => 'image/gif',   'webp' => 'image/webp',  'svg'  => 'image/svg+xml',
    'mp4'  => 'video/mp4',   'webm' => 'video/webm',  'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo', 'pdf' => 'application/pdf',
    'json' => 'application/json', 'txt' => 'text/plain',
    'm3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t',
];

$m3u8_ext = strtolower(pathinfo($object_key_clean, PATHINFO_EXTENSION));

// ── Streaming path for non-m3u8 files (e.g. .ts segments, images) ───────
// Pipes S3 response directly to the client without buffering the entire body
// in PHP memory. Reduces latency and memory usage for large HLS segments.
if ($m3u8_ext !== 'm3u8') {
    $guessed_ct = $mime_map[$m3u8_ext] ?? 'application/octet-stream';

    $stream_resp_headers = [];
    $stream_headers_sent = false;
    $stream_error        = false;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // generous for large .ts segments on slow links
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

    // Capture S3 response headers
    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
        function ($ch, $header) use (&$stream_resp_headers) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $stream_resp_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        }
    );

    // Stream body chunks directly to the client
    curl_setopt($ch, CURLOPT_WRITEFUNCTION,
        function ($ch, $data) use (
            &$stream_resp_headers, &$stream_headers_sent, &$stream_error,
            $guessed_ct, $object_key_clean
        ) {
            if (!$stream_headers_sent) {
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($code === 404) {
                    http_response_code(404);
                    header('Content-Type: application/json');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    _media_cors_headers();
                    echo json_encode(['error' => 'File not found']);
                    $stream_error = true;
                    return -1;
                }
                if ($code !== 200 && $code !== 0) {
                    error_log("media.php stream proxy: RustFS returned HTTP $code for key=$object_key_clean");
                    http_response_code(502);
                    header('Content-Type: application/json');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    _media_cors_headers();
                    echo json_encode(['error' => 'Storage error']);
                    $stream_error = true;
                    return -1;
                }

                // Determine content type from S3 or guess from extension
                $ct = $stream_resp_headers['content-type'] ?? null;
                if (empty($ct) || $ct === 'application/octet-stream') {
                    $ct = $guessed_ct;
                }

                header('Content-Type: ' . $ct);
                if (isset($stream_resp_headers['content-length'])) {
                    header('Content-Length: ' . $stream_resp_headers['content-length']);
                }
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=86400');
                header('X-Content-Type-Options: nosniff');
                _media_cors_headers();

                $stream_headers_sent = true;
            }

            echo $data;
            if (ob_get_level()) ob_flush();
            flush();
            return strlen($data);
        }
    );

    curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!$stream_headers_sent && !$stream_error) {
        error_log("media.php stream proxy error for key=$object_key_clean: $curl_error");
        http_response_code(502);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        _media_cors_headers();
        echo json_encode(['error' => 'Storage connection error']);
    }
    exit;
}

// ── Buffered path for m3u8 playlists (needs URL rewriting) ──────────────
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

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
    header('Cache-Control: no-store, no-cache, must-revalidate');
    _media_cors_headers();
    echo json_encode(['error' => 'Storage connection error']);
    exit;
}

if ($http_code === 404) {
    http_response_code(404);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    _media_cors_headers();
    echo json_encode(['error' => 'File not found']);
    exit;
}

if ($http_code !== 200) {
    error_log("media.php proxy: RustFS returned HTTP $http_code for key=$object_key_clean");
    http_response_code(502);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    _media_cors_headers();
    echo json_encode(['error' => 'Storage error']);
    exit;
}

// ── Rewrite relative URLs in HLS playlists ──────────────────────────────
// HLS .m3u8 files contain relative paths to variant playlists and segments.
// Because we serve them through a query-string proxy (media.php?key=…) the
// browser/HLS.js would resolve those relatives against /api/ rather than the
// S3 directory the manifest lives in.  Fix by rewriting every relative
// reference to go back through this proxy.
$base_dir = dirname($object_key_clean);
$lines = explode("\n", $body);
$rewritten = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || (isset($trimmed[0]) && $trimmed[0] === '#')) {
        $rewritten[] = $line;
        continue;
    }
    if (preg_match('#^https?://#', $trimmed)) {
        $rewritten[] = $line;
        continue;
    }
    $resolved_key = $base_dir . '/' . $trimmed;
    $rewritten[] = 'media.php?key=' . rawurlencode($resolved_key);
}
$body = implode("\n", $rewritten);

// ── Send response ───────────────────────────────────────────────────────
$content_type = 'application/vnd.apple.mpegurl';
header('Content-Type: ' . $content_type);
header('Content-Length: ' . strlen($body));
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
_media_cors_headers();

echo $body;
