<?php
/**
 * API v1 - Session Endpoints
 *
 * Endpoints:
 *   GET /v1/sessions          - List sessions (filterable)
 *   GET /v1/sessions/{id}     - Get session details
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$session_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$session_id) {
    handleListSessions($auth);
} elseif ($method === 'GET' && $session_id && !$action) {
    handleGetSession($auth, (int) $session_id);
} elseif ($method === 'GET' && $session_id && $action === 'athletes') {
    handleGetSessionAthletes($auth, (int) $session_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Session endpoint not found']);
}

/**
 * GET /v1/sessions
 */
function handleListSessions($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_sessions')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 's.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 's.session_date >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 's.session_date <= ?';
        $params[] = $_GET['date_to'];
    }
    if (!empty($_GET['coach_id'])) {
        $where[] = 's.coach_id = ?';
        $params[] = (int) $_GET['coach_id'];
    }
    if (!empty($_GET['team_id'])) {
        $where[] = 's.team_id = ?';
        $params[] = (int) $_GET['team_id'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions s $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.description, s.session_date, s.session_time,
                   s.duration_minutes, s.price, s.max_participants, s.age_group,
                   s.skill_level, s.team_id, s.coach_id, s.arena, s.city,
                   s.session_type, s.status, s.created_at,
                   c.first_name AS coach_first_name, c.last_name AS coach_last_name
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            $where_sql
            ORDER BY s.session_date DESC, s.session_time DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessions as &$session) {
            $session['coach_name'] = trim(
                FieldEncryption::decrypt($session['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($session['coach_last_name'] ?? '')
            );
            unset($session['coach_first_name'], $session['coach_last_name']);
        }
        unset($session);

        logApiAccess('list_sessions', "Listed sessions (page $page)", $auth['user_id']);
        paginatedResponse($sessions, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API SESSIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/sessions/{id}
 */
function handleGetSession($auth, $session_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_sessions')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.first_name AS coach_first_name, c.last_name AS coach_last_name
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            apiResponse(404, ['success' => false, 'error' => 'Session not found']);
        }

        $session['coach_name'] = trim(
            FieldEncryption::decrypt($session['coach_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($session['coach_last_name'] ?? '')
        );
        unset($session['coach_first_name'], $session['coach_last_name']);

        logApiAccess('get_session', "Viewed session ID: $session_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $session]);
    } catch (PDOException $e) {
        error_log('[API SESSIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/sessions/{id}/athletes
 */
function handleGetSessionAthletes($auth, $session_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_sessions')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.position
            FROM bookings b
            INNER JOIN users u ON b.user_id = u.id
            WHERE b.session_id = ? AND b.status IN ('confirmed', 'completed')
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$session_id]);
        $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($athletes as &$athlete) {
            $athlete['first_name'] = FieldEncryption::decrypt($athlete['first_name'] ?? '');
            $athlete['last_name'] = FieldEncryption::decrypt($athlete['last_name'] ?? '');
            $athlete['name'] = trim($athlete['first_name'] . ' ' . $athlete['last_name']);
        }
        unset($athlete);

        apiResponse(200, ['success' => true, 'data' => $athletes]);
    } catch (PDOException $e) {
        error_log('[API SESSIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
