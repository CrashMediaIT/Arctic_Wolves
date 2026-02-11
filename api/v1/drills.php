<?php
/**
 * API v1 - Drill Endpoints
 *
 * Endpoints:
 *   GET /v1/drills          - List drills
 *   GET /v1/drills/{id}     - Get drill details
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$drill_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$drill_id) {
    handleListDrills($auth);
} elseif ($method === 'GET' && $drill_id && !$action) {
    handleGetDrill($auth, (int) $drill_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Drill endpoint not found']);
}

/**
 * GET /v1/drills
 */
function handleListDrills($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if (!empty($_GET['category'])) {
        $where[] = 'd.category = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['difficulty'])) {
        $where[] = 'd.difficulty_level = ?';
        $params[] = $_GET['difficulty'];
    }
    if (!empty($_GET['search'])) {
        $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
        $search_term = '%' . $_GET['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM drills d $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT d.id, d.title, d.description, d.category, d.difficulty_level,
                   d.duration_minutes, d.equipment_needed, d.min_players, d.max_players,
                   d.video_url, d.diagram_url, d.created_at,
                   u.first_name AS creator_first_name, u.last_name AS creator_last_name
            FROM drills d
            LEFT JOIN users u ON d.created_by = u.id
            $where_sql
            ORDER BY d.title
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $drills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($drills as &$drill) {
            $drill['created_by_name'] = trim(
                FieldEncryption::decrypt($drill['creator_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($drill['creator_last_name'] ?? '')
            );
            unset($drill['creator_first_name'], $drill['creator_last_name']);
        }
        unset($drill);

        logApiAccess('list_drills', "Listed drills (page $page)", $auth['user_id']);
        paginatedResponse($drills, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API DRILLS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/drills/{id}
 */
function handleGetDrill($auth, $drill_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.first_name AS creator_first_name, u.last_name AS creator_last_name
            FROM drills d
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$drill_id]);
        $drill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$drill) {
            apiResponse(404, ['success' => false, 'error' => 'Drill not found']);
        }

        $drill['created_by_name'] = trim(
            FieldEncryption::decrypt($drill['creator_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($drill['creator_last_name'] ?? '')
        );
        unset($drill['creator_first_name'], $drill['creator_last_name']);

        logApiAccess('get_drill', "Viewed drill ID: $drill_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $drill]);
    } catch (PDOException $e) {
        error_log('[API DRILLS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
