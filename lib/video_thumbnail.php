<?php
/**
 * Video Thumbnail Generator
 * Extracts a thumbnail frame from a video file and uploads it to RustFS.
 * Requires ffmpeg to be available on the system PATH.
 */

require_once __DIR__ . '/rustfs_storage.php';

/**
 * Check if ffmpeg is available on the system.
 * @return bool
 */
function isFFmpegAvailable() {
    static $available = null;
    if ($available !== null) return $available;
    $output = [];
    $code = -1;
    @exec('ffmpeg -version 2>&1', $output, $code);
    $available = ($code === 0);
    return $available;
}

/**
 * Extract a thumbnail from a video file and upload it to RustFS.
 *
 * @param PDO    $pdo          Database connection (for RustFS settings)
 * @param string $videoPath    Path to the local video file (temp upload)
 * @param string $subfolder    RustFS subfolder for the thumbnail (e.g. 'drills/thumbnails')
 * @param string $baseName     Base filename without extension (e.g. 'personal_drill_abc123')
 * @param float  $timestamp    Seconds into the video to capture (default 1.0)
 * @return array ['success'=>bool, 'thumbnail_url'=>string|null]
 */
function generateVideoThumbnail($pdo, $videoPath, $subfolder, $baseName, $timestamp = 1.0) {
    $result = ['success' => false, 'thumbnail_url' => null];

    if (!isFFmpegAvailable()) {
        error_log("generateVideoThumbnail: ffmpeg not available — skipping thumbnail generation");
        return $result;
    }

    if (!file_exists($videoPath) || !is_readable($videoPath)) {
        error_log("generateVideoThumbnail: video file not accessible: $videoPath");
        return $result;
    }

    $tmpFile = sys_get_temp_dir() . '/' . $baseName . '_thumb.jpg';

    try {
        // Extract a single frame using ffmpeg
        // -ss: seek to timestamp, -frames:v 1: capture one frame
        // -vf scale: resize to max 640px wide, keeping aspect ratio
        $ts = number_format($timestamp, 2, '.', '');
        $cmd = sprintf(
            'ffmpeg -y -ss %s -i %s -frames:v 1 -vf "scale=640:-2" -q:v 3 %s 2>&1',
            escapeshellarg($ts),
            escapeshellarg($videoPath),
            escapeshellarg($tmpFile)
        );

        $output = [];
        $code = -1;
        exec($cmd, $output, $code);

        if ($code !== 0 || !file_exists($tmpFile) || filesize($tmpFile) === 0) {
            // Try again at 0 seconds (video might be shorter than timestamp)
            if ($timestamp > 0) {
                $cmd0 = sprintf(
                    'ffmpeg -y -ss 0 -i %s -frames:v 1 -vf "scale=640:-2" -q:v 3 %s 2>&1',
                    escapeshellarg($videoPath),
                    escapeshellarg($tmpFile)
                );
                exec($cmd0, $output, $code);
            }
        }

        if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
            error_log("generateVideoThumbnail: ffmpeg failed to extract frame. Exit code: $code");
            return $result;
        }

        // Upload thumbnail to RustFS
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            error_log("generateVideoThumbnail: RustFS not configured");
            return $result;
        }

        $thumbContent = file_get_contents($tmpFile);
        $objectKey = 'Images/' . trim($subfolder, '/') . '/' . $baseName . '_thumb.jpg';

        $upload = uploadContentToRustFS($rustfs, $thumbContent, $objectKey, 'image/jpeg');

        if ($upload['success']) {
            $proxyUrl = 'api/media.php?key=' . rawurlencode($objectKey);
            $result['success'] = true;
            $result['thumbnail_url'] = $proxyUrl;
        } else {
            error_log("generateVideoThumbnail: RustFS upload failed: " . ($upload['message'] ?? ''));
        }
    } catch (Exception $e) {
        error_log("generateVideoThumbnail: " . $e->getMessage());
    } finally {
        // Clean up temp file
        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    return $result;
}
