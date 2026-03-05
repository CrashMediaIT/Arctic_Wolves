<?php
/**
 * Image Helper - Validates image paths/URLs
 * 
 * All images are stored in RustFS S3 and served via URLs.
 * No local file storage or restoration.
 */

require_once __DIR__ . '/../cloud_config.php';

/**
 * Validate that an image path/URL is safe
 */
function isValidImagePath($path) {
    if (empty($path)) return false;
    if (preg_match('#^https?://#', $path)) return true;
    // Accept media proxy URLs
    if (strpos($path, 'api/media.php') !== false) return true;
    return false;
}

/**
 * No-op — kept for backward compatibility of function signature.
 */
function tryRestoreFromPersistent($local_path, $subfolder, $filename = null, $pdo = null) {
    return false;
}

/**
 * Return the profile image path if it's a valid URL or proxy URL.
 */
function resolveProfileImage($pdo, $user_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return resolveRustfsUrl($pdo, $path);
    if (strpos($path, 'api/media.php') !== false) return $path;
    return null;
}

/**
 * Return the evaluation media path if it's a valid URL or proxy URL.
 */
function resolveEvaluationMedia($pdo, $media_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return resolveRustfsUrl($pdo, $path);
    if (strpos($path, 'api/media.php') !== false) return $path;
    return null;
}

/**
 * Return the drill image path if it's a valid URL or proxy URL.
 */
function resolveDrillImage($pdo, $drill_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return resolveRustfsUrl($pdo, $path);
    if (strpos($path, 'api/media.php') !== false) return $path;
    return null;
}

/**
 * Resolve any stored media URL so the browser can load it.
 *
 * RustFS buckets are typically private.  Direct URLs won't work from the
 * browser because the GET request is unauthenticated.  This function
 * converts direct RustFS URLs to the local media-proxy endpoint which
 * authenticates on the server side and streams the object.
 *
 * Already-proxy URLs and non-RustFS URLs are returned unchanged.
 *
 * @param PDO|null $pdo   Database connection (used to look up RustFS base URL)
 * @param string   $url   Stored URL / path (from the database column)
 * @return string|null     Browser-loadable URL, or null if empty
 */
function resolveRustfsUrl($pdo, $url) {
    if (empty($url)) return null;

    // Already a proxy URL — return as-is
    if (strpos($url, 'api/media.php') !== false) {
        return $url;
    }

    // Not an http(s) URL — nothing to proxy
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    // Determine the RustFS base URL so we can detect and convert
    static $rustfs_base = null;
    static $rustfs_checked = false;

    if (!$rustfs_checked && $pdo) {
        $rustfs_checked = true;
        try {
            $rustfs = getRustFSSettings($pdo);
            if (isRustFSConfigured($rustfs)) {
                $rustfs_base = getRustFSBaseUrl($rustfs);
            }
        } catch (\Throwable $e) {
            // silently fall through — we'll return the raw URL
        }
    }

    if ($rustfs_base && strpos($url, $rustfs_base) === 0) {
        // Extract the object key from the full URL
        $object_key = substr($url, strlen($rustfs_base));
        $object_key = ltrim($object_key, '/');
        return 'api/media.php?key=' . rawurlencode($object_key);
    }

    // Not a recognised RustFS URL — return as-is
    return $url;
}

/**
 * Get the preferred playback URL for a video.
 *
 * When HLS transcoding is complete (hls_status='ready') the original source
 * file has been deleted by the companion, so we must serve the HLS manifest.
 * Otherwise fall back to the original video_url (or file_path for
 * vr_video_sources rows which use that column name instead).
 *
 * @param array $video  Video row from the database (must include video_url or file_path, hls_url, hls_status)
 * @return string  The URL to use for playback (video_url/file_path or hls_url)
 */
function getPreferredVideoUrl($video) {
    if (($video['hls_status'] ?? '') === 'ready' && !empty($video['hls_url'])) {
        return $video['hls_url'];
    }
    return $video['video_url'] ?? $video['file_path'] ?? '';
}

/**
 * Derive an HLS fallback URL from a video's original proxy URL.
 *
 * When the companion transcodes a video it writes HLS segments to a
 * predictable path based on the original file name:
 *   Original: Images/videos/…/athlete_video_xxx.mkv
 *   HLS:      Images/videos/…/athlete_video_xxx/hls/master.m3u8
 *
 * This function replicates that convention so the player can attempt
 * HLS playback even if the companion callback never updated hls_url
 * in the database (e.g. callback routing / reverse-proxy issues).
 *
 * @param string $video_url  The original video proxy URL (api/media.php?key=…)
 * @return string  Derived HLS proxy URL, or empty string if not derivable
 */
function deriveHlsFallbackUrl($video_url) {
    if (empty($video_url)) return '';

    // Only derive for media-proxy URLs with a recognised video extension
    if (preg_match('#api/media\.php\?key=(.+)$#', $video_url, $m)) {
        $encoded_key = $m[1];
        $object_key  = rawurldecode($encoded_key);

        // Strip the video extension to get the base name
        if (preg_match('#\.(mp4|mkv|mov|avi|webm)$#i', $object_key)) {
            $base = preg_replace('#\.[^.]+$#', '', $object_key);
            return 'api/media.php?key=' . rawurlencode($base . '/hls/master.m3u8');
        }
    }
    return '';
}
