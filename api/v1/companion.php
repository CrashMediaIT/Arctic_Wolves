<?php
/**
 * API v1 - Companion Server Webhook Endpoints
 * Receives callbacks from the Video Companion Server when transcoding
 * jobs complete (or fail) so the main application can update the
 * database with the final HLS file locations.
 *
 * Endpoints:
 *   POST /v1/companion/callback  - Transcode completion callback
 *
 * Authentication: X-API-Key header matching gameplan_companion_api_key
 */

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../error_logger.php';

$method   = $GLOBALS['api_method'];
$segments = $GLOBALS['api_segments'] ?? [];

$sub_resource = $segments[0] ?? null;

// Authenticate the companion using the shared API key
function authenticateCompanion(): bool {
    global $pdo;
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($key)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gameplan_companion_api_key' LIMIT 1");
        $stmt->execute();
        $stored_key = $stmt->fetchColumn();
        return $stored_key && hash_equals($stored_key, $key);
    } catch (Exception $e) {
        return false;
    }
}

if ($sub_resource === 'callback' && $method === 'POST') {
    handleCompanionCallback();
} else {
    apiResponse(404, ['success' => false, 'error' => 'Companion endpoint not found. Use POST /v1/companion/callback']);
}

/**
 * POST /v1/companion/callback
 * Receives transcode job results from the companion server.
 *
 * Expected JSON body:
 *   job_id           - Companion job UUID
 *   video_id         - Main app video row ID (if provided when triggering)
 *   status           - "completed" or "failed"
 *   hls_manifest     - S3 key to master.m3u8 (on success)
 *   hls_segments_path - S3 prefix containing HLS segments (on success)
 *   variants         - Dict of label → playlist S3 key (on success)
 *   source_key       - Original source S3 key
 *   error            - Error message (on failure)
 */
function handleCompanionCallback(): void {
    global $pdo;

    if (!authenticateCompanion()) {
        apiResponse(401, ['success' => false, 'error' => 'Unauthorized']);
    }

    $body = getJsonBody();
    $job_id    = $body['job_id'] ?? '';
    $video_id  = $body['video_id'] ?? null;
    $status    = $body['status'] ?? '';

    if (empty($status)) {
        apiResponse(400, ['success' => false, 'error' => 'status is required']);
    }

    // Try to find the video row by video_id first, then by hls_job_id
    $vid = null;
    if ($video_id) {
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $video_id]);
        $vid = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$vid && $job_id) {
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE hls_job_id = ? LIMIT 1");
        $stmt->execute([$job_id]);
        $vid = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$vid) {
        ErrorLogger::warning("Companion callback: no matching video for job_id=$job_id video_id=$video_id");
        apiResponse(404, ['success' => false, 'error' => 'Video record not found']);
    }

    $db_video_id = (int) $vid['id'];

    try {
        if ($status === 'completed') {
            $hls_manifest     = $body['hls_manifest'] ?? '';
            $hls_segments     = $body['hls_segments_path'] ?? '';
            $variants         = $body['variants'] ?? [];

            // Build the media proxy URL for the master playlist
            $hls_url = '';
            if ($hls_manifest) {
                $hls_url = "api/media.php?key=" . rawurlencode($hls_manifest);
            }

            $stmt = $pdo->prepare("
                UPDATE videos
                SET hls_status        = 'ready',
                    hls_master_url    = :manifest,
                    hls_segments_path = :segments,
                    hls_url           = :hls_url
                WHERE id = :id
            ");
            $stmt->execute([
                ':manifest' => $hls_manifest,
                ':segments' => $hls_segments,
                ':hls_url'  => $hls_url,
                ':id'       => $db_video_id,
            ]);

            ErrorLogger::info("HLS transcode completed for video $db_video_id (job $job_id)");
            apiResponse(200, ['success' => true, 'message' => 'Video updated to ready']);

        } elseif ($status === 'failed') {
            $error = $body['error'] ?? 'Unknown error';

            $stmt = $pdo->prepare("UPDATE videos SET hls_status = 'failed' WHERE id = ?");
            $stmt->execute([$db_video_id]);

            ErrorLogger::error("HLS transcode failed for video $db_video_id (job $job_id): $error");
            apiResponse(200, ['success' => true, 'message' => 'Video marked as failed']);

        } else {
            apiResponse(400, ['success' => false, 'error' => 'Invalid status. Must be completed or failed.']);
        }
    } catch (Exception $e) {
        ErrorLogger::error("Companion callback error: " . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
