<?php
/**
 * API v1 - Video Endpoints
 * Provides video management access for ACVideoReview and ACWolvesAPP.
 *
 * Endpoints:
 *   GET    /v1/videos              - List videos (filterable)
 *   GET    /v1/videos/{id}         - Get video details
 *   POST   /v1/videos/{id}/review  - Submit a video review
 *   PUT    /v1/videos/{id}         - Update video notes
 *   DELETE /v1/videos/{id}         - Delete a video
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$video_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

// Route
if ($method === 'GET' && !$video_id) {
    handleListVideos($auth);
} elseif ($method === 'GET' && $video_id && !$action) {
    handleGetVideo($auth, (int) $video_id);
} elseif ($method === 'POST' && $video_id && $action === 'review') {
    handleReviewVideo($auth, (int) $video_id);
} elseif ($method === 'PUT' && $video_id && !$action) {
    handleUpdateVideo($auth, (int) $video_id);
} elseif ($method === 'DELETE' && $video_id && !$action) {
    handleDeleteVideo($auth, (int) $video_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Video endpoint not found']);
}

/**
 * GET /v1/videos
 * List videos with optional filters.
 */
function handleListVideos($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_videos')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    // Build filters
    $where = [];
    $params = [];

    // Role-based access: athletes see only their own videos
    if ($auth['user_role'] === 'athlete') {
        $where[] = 'v.athlete_id = ?';
        $params[] = $auth['user_id'];
    } elseif ($auth['user_role'] === 'parent') {
        $where[] = 'v.athlete_id IN (SELECT athlete_id FROM parent_athlete_relationships WHERE parent_id = ? UNION SELECT athlete_id FROM managed_athletes WHERE parent_id = ?)';
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
    } elseif (in_array($auth['user_role'], ['coach', 'coach_plus', 'health_coach', 'team_coach'])) {
        // Coaches see videos for their athletes or videos they've been assigned to
        $where[] = '(v.coach_id = ? OR v.athlete_id IN (SELECT id FROM users WHERE assigned_coach_id = ?))';
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
    }
    // Admins see all videos

    // Optional filters
    if (!empty($_GET['athlete_id'])) {
        $where[] = 'v.athlete_id = ?';
        $params[] = (int) $_GET['athlete_id'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'v.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['video_category'])) {
        $where[] = 'v.video_category = ?';
        $params[] = $_GET['video_category'];
    }
    if (!empty($_GET['video_type'])) {
        $where[] = 'v.video_type = ?';
        $params[] = $_GET['video_type'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        // Count total
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM videos v $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        // Fetch videos
        $stmt = $pdo->prepare("
            SELECT v.id, v.athlete_id, v.coach_id, v.title, v.description, v.video_url,
                   v.thumbnail_url, v.upload_date, v.video_type, v.video_category,
                   v.drill_id, v.session_id, v.rep_number,
                   v.game_date, v.team_played_on, v.opponent_team,
                   v.status, v.coach_notes, v.athlete_notes, v.reviewed_at,
                   v.created_at, v.updated_at,
                   a.first_name AS athlete_first_name, a.last_name AS athlete_last_name,
                   c.first_name AS coach_first_name, c.last_name AS coach_last_name
            FROM videos v
            LEFT JOIN users a ON v.athlete_id = a.id
            LEFT JOIN users c ON v.coach_id = c.id
            $where_sql
            ORDER BY v.upload_date DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decrypt names
        foreach ($videos as &$video) {
            $video['athlete_name'] = trim(
                FieldEncryption::decrypt($video['athlete_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($video['athlete_last_name'] ?? '')
            );
            $video['coach_name'] = trim(
                FieldEncryption::decrypt($video['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($video['coach_last_name'] ?? '')
            );
            unset($video['athlete_first_name'], $video['athlete_last_name']);
            unset($video['coach_first_name'], $video['coach_last_name']);
        }
        unset($video);

        logApiAccess('list_videos', "Listed videos (page $page)", $auth['user_id']);
        paginatedResponse($videos, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API VIDEOS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/videos/{id}
 */
function handleGetVideo($auth, $video_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_videos')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   a.first_name AS athlete_first_name, a.last_name AS athlete_last_name, a.email AS athlete_email,
                   c.first_name AS coach_first_name, c.last_name AS coach_last_name
            FROM videos v
            LEFT JOIN users a ON v.athlete_id = a.id
            LEFT JOIN users c ON v.coach_id = c.id
            WHERE v.id = ?
        ");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) {
            apiResponse(404, ['success' => false, 'error' => 'Video not found']);
        }

        // Access control
        if (!canAccessVideo($auth, $video)) {
            apiResponse(403, ['success' => false, 'error' => 'Access denied']);
        }

        // Decrypt names
        $video['athlete_name'] = trim(
            FieldEncryption::decrypt($video['athlete_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($video['athlete_last_name'] ?? '')
        );
        $video['coach_name'] = trim(
            FieldEncryption::decrypt($video['coach_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($video['coach_last_name'] ?? '')
        );
        unset($video['athlete_first_name'], $video['athlete_last_name'], $video['athlete_email']);
        unset($video['coach_first_name'], $video['coach_last_name']);
        // Remove internal paths
        unset($video['nextcloud_path'], $video['local_path'], $video['is_uploaded_to_cloud']);

        logApiAccess('get_video', "Viewed video ID: $video_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $video]);
    } catch (PDOException $e) {
        error_log('[API VIDEOS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/videos/{id}/review
 */
function handleReviewVideo($auth, $video_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'review_videos')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $coach_notes = trim($body['coach_notes'] ?? $body['notes'] ?? '');

    if (empty($coach_notes)) {
        apiResponse(400, ['success' => false, 'error' => 'Review notes are required']);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE videos v
            LEFT JOIN users u ON v.athlete_id = u.id
            SET v.status = 'reviewed',
                v.coach_id = ?,
                v.coach_notes = ?,
                v.reviewed_at = NOW()
            WHERE v.id = ? AND (v.coach_id = ? OR v.coach_id IS NULL OR u.assigned_coach_id = ?)
        ");
        $stmt->execute([$auth['user_id'], $coach_notes, $video_id, $auth['user_id'], $auth['user_id']]);

        if ($stmt->rowCount() === 0) {
            apiResponse(404, ['success' => false, 'error' => 'Video not found or access denied']);
        }

        logApiAccess('review_video', "Reviewed video ID: $video_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Video reviewed successfully']);
    } catch (PDOException $e) {
        error_log('[API VIDEOS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/videos/{id}
 */
function handleUpdateVideo($auth, $video_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_videos') && !hasApiPermission($auth, 'upload_videos')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();

    try {
        // Verify access
        $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND (coach_id = ? OR athlete_id = ?)");
        $stmt->execute([$video_id, $auth['user_id'], $auth['user_id']]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) {
            apiResponse(404, ['success' => false, 'error' => 'Video not found or access denied']);
        }

        $coach_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
        $updated = false;
        if (in_array($auth['user_role'], $coach_roles) && isset($body['coach_notes'])) {
            $stmt = $pdo->prepare("UPDATE videos SET coach_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([trim($body['coach_notes']), $video_id]);
            $updated = true;
        } elseif (isset($body['athlete_notes'])) {
            $stmt = $pdo->prepare("UPDATE videos SET athlete_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([trim($body['athlete_notes']), $video_id]);
            $updated = true;
        }

        if (!$updated) {
            apiResponse(400, ['success' => false, 'error' => 'No updatable fields provided. Send coach_notes or athlete_notes.']);
        }

        logApiAccess('update_video', "Updated video ID: $video_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Video updated successfully']);
    } catch (PDOException $e) {
        error_log('[API VIDEOS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * DELETE /v1/videos/{id}
 */
function handleDeleteVideo($auth, $video_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_videos')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND (coach_id = ? OR athlete_id = ?)");
        $stmt->execute([$video_id, $auth['user_id'], $auth['user_id']]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) {
            apiResponse(404, ['success' => false, 'error' => 'Video not found or access denied']);
        }

        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$video_id]);

        logApiAccess('delete_video', "Deleted video ID: $video_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Video deleted successfully']);
    } catch (PDOException $e) {
        error_log('[API VIDEOS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * Check if a user can access a specific video based on their role.
 */
function canAccessVideo($auth, $video) {
    if ($auth['user_role'] === 'admin') {
        return true;
    }

    $user_id = $auth['user_id'];

    // Coach or athlete directly associated
    if ($video['athlete_id'] == $user_id || $video['coach_id'] == $user_id) {
        return true;
    }

    // Coach assigned to the athlete
    global $pdo;
    $stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
    $stmt->execute([$video['athlete_id']]);
    $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($athlete && $athlete['assigned_coach_id'] == $user_id) {
        return true;
    }

    // Parent of the athlete
    if ($auth['user_role'] === 'parent') {
        $stmt = $pdo->prepare("SELECT 1 FROM parent_athlete_relationships WHERE parent_id = ? AND athlete_id = ?");
        $stmt->execute([$user_id, $video['athlete_id']]);
        if ($stmt->fetch()) {
            return true;
        }
        // Also check managed_athletes as fallback
        $stmt2 = $pdo->prepare("SELECT 1 FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
        $stmt2->execute([$user_id, $video['athlete_id']]);
        if ($stmt2->fetch()) {
            return true;
        }
    }

    return false;
}
