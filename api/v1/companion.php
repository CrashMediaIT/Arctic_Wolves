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

// db_config.php calls ErrorLogger::setDatabase() only if ErrorLogger is
// already loaded.  Because error_logger.php is required *after* db_config,
// the call is skipped and logs never reach the error_logs DB table — making
// companion callback events invisible in the admin Error Logs UI.
// Re-apply the database connection now that both are loaded.
if (isset($pdo) && $pdo instanceof PDO) {
    ErrorLogger::setDatabase($pdo);
}

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
    } catch (\Throwable $e) {
        return false;
    }
}

if ($sub_resource === 'callback' && $method === 'POST') {
    try {
        handleCompanionCallback();
    } catch (\Throwable $e) {
        // Catch any unhandled error (TypeError from null $pdo, missing
        // DB columns, etc.) so the response is always valid JSON.
        ErrorLogger::error("Companion callback unhandled error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        apiResponse(500, ['success' => false, 'confirmed' => false, 'error' => 'Internal server error', 'rows_affected' => 0]);
    }
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
        return; // defense-in-depth: apiResponse calls exit, but be explicit
    }

    $body = getJsonBody();
    $job_id    = $body['job_id'] ?? '';
    $video_id  = $body['video_id'] ?? null;
    $source_id = $body['source_id'] ?? null;
    $status    = $body['status'] ?? '';

    // Log the incoming callback for diagnostics (visible in admin Error Logs)
    $safe_job = preg_replace('/[^a-zA-Z0-9-]/', '', substr($job_id, 0, 64)) ?: '(empty)';
    $safe_vid = $video_id !== null ? (int) $video_id : 'null';
    $safe_src = $source_id !== null ? (int) $source_id : 'null';
    ErrorLogger::info("Companion callback received: job_id=$safe_job video_id=$safe_vid source_id=$safe_src status=$status");

    if (empty($status)) {
        apiResponse(400, ['success' => false, 'error' => 'status is required']);
        return;
    }

    // Determine which table to update: vr_video_sources (if source_id) or videos
    $table = null;       // 'videos' or 'vr_video_sources'
    $db_record_id = null;

    // Check vr_video_sources first if source_id is provided
    if ($source_id) {
        $stmt = $pdo->prepare("SELECT id FROM vr_video_sources WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $source_id]);
        $src = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($src) {
            $table = 'vr_video_sources';
            $db_record_id = (int) $src['id'];
        }
    }

    // Fall back to videos table by video_id, then by hls_job_id
    if (!$table && $video_id) {
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $video_id]);
        $vid = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vid) {
            $table = 'videos';
            $db_record_id = (int) $vid['id'];
        }
    }
    if (!$table && $job_id) {
        // Try videos table by hls_job_id
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE hls_job_id = ? LIMIT 1");
        $stmt->execute([$job_id]);
        $vid = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vid) {
            $table = 'videos';
            $db_record_id = (int) $vid['id'];
        }
        // Try vr_video_sources table by hls_job_id
        if (!$table) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM vr_video_sources WHERE hls_job_id = ? LIMIT 1");
                $stmt->execute([$job_id]);
                $src = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($src) {
                    $table = 'vr_video_sources';
                    $db_record_id = (int) $src['id'];
                }
            } catch (PDOException $e) {
                // hls_job_id column may not exist yet — non-fatal
            }
        }
    }

    if (!$table || !$db_record_id) {
        // Sanitize log values to prevent log injection
        $safe_job = preg_replace('/[^a-zA-Z0-9-]/', '', substr($job_id, 0, 64));
        $safe_vid = $video_id !== null ? (int) $video_id : 'null';
        $safe_src = $source_id !== null ? (int) $source_id : 'null';
        ErrorLogger::warning("Companion callback: no matching record for job_id=$safe_job video_id=$safe_vid source_id=$safe_src");
        apiResponse(404, ['success' => false, 'error' => 'Video record not found']);
        return;
    }

    try {
        if ($status === 'completed') {
            $hls_manifest     = $body['hls_manifest'] ?? '';
            $hls_segments     = $body['hls_segments_path'] ?? '';
            $variants         = $body['variants'] ?? [];
            // Use the explicit hls_status from the companion if provided,
            // otherwise default to 'ready' for completed jobs.
            $hls_status_value = $body['hls_status'] ?? 'ready';

            // Build the media proxy URL for the master playlist
            $hls_url = '';
            if ($hls_manifest) {
                $hls_url = "api/media.php?key=" . rawurlencode($hls_manifest);
            }

            $label = $table === 'vr_video_sources' ? 'source' : 'video';
            ErrorLogger::info("Companion callback: updating $label $db_record_id to hls_status=$hls_status_value hls_url=$hls_url (job $job_id)");

            $stmt = $pdo->prepare("
                UPDATE $table
                SET hls_status        = :hls_status,
                    hls_master_url    = :manifest,
                    hls_segments_path = :segments,
                    hls_url           = :hls_url
                WHERE id = :id
            ");
            $stmt->execute([
                ':hls_status' => $hls_status_value,
                ':manifest' => $hls_manifest,
                ':segments' => $hls_segments,
                ':hls_url'  => $hls_url,
                ':id'       => $db_record_id,
            ]);

            $rows_affected = $stmt->rowCount();
            ErrorLogger::info("HLS transcode completed for $label $db_record_id (job $job_id) — $rows_affected row(s) updated");
            $confirmed = $rows_affected > 0;
            if (!$confirmed) {
                // rowCount() returns changed rows, not matched rows. If the
                // record already has the target hls_status (e.g. callback
                // retry, or values pre-set by the trigger), the UPDATE
                // changes zero rows even though the record exists.  Verify
                // the current state before reporting "not confirmed".
                $check = $pdo->prepare("SELECT hls_status FROM $table WHERE id = ? LIMIT 1");
                $check->execute([$db_record_id]);
                $current = $check->fetch(PDO::FETCH_ASSOC);
                if ($current && $current['hls_status'] === $hls_status_value) {
                    // Record already has the correct status — treat as confirmed
                    $confirmed = true;
                    $rows_affected = 1;
                    ErrorLogger::info("Companion callback: record $label $db_record_id already has hls_status=$hls_status_value — confirmed (idempotent)");
                } else {
                    ErrorLogger::warning("Companion callback: UPDATE affected 0 rows for $label $db_record_id (job $job_id) — record may have been deleted");
                }
            }
            ErrorLogger::info("Companion callback response: confirmed=$confirmed rows_affected=$rows_affected for $label $db_record_id (job $job_id)");
            apiResponse(200, [
                'success'       => true,
                'confirmed'     => $confirmed,
                'message'       => $confirmed ? 'Video updated to ready' : 'No rows updated -- record may have been deleted',
                'rows_affected' => $rows_affected,
            ]);

        } elseif ($status === 'failed') {
            $error = $body['error'] ?? 'Unknown error';

            $stmt = $pdo->prepare("UPDATE $table SET hls_status = 'failed' WHERE id = ?");
            $stmt->execute([$db_record_id]);
            $fail_rows = $stmt->rowCount();

            $label = $table === 'vr_video_sources' ? 'source' : 'video';
            ErrorLogger::error("HLS transcode failed for $label $db_record_id (job $job_id): $error");
            apiResponse(200, ['success' => true, 'confirmed' => $fail_rows > 0, 'message' => 'Video marked as failed', 'rows_affected' => $fail_rows]);

        } else {
            apiResponse(400, ['success' => false, 'error' => 'Invalid status. Must be completed or failed.']);
        }
    } catch (\Throwable $e) {
        ErrorLogger::error("Companion callback error for job $job_id: " . $e->getMessage());
        apiResponse(500, ['success' => false, 'confirmed' => false, 'error' => 'Internal server error', 'rows_affected' => 0]);
    }
}
