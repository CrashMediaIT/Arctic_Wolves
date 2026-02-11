<?php
/**
 * API v1 - User / Profile Endpoints
 *
 * Endpoints:
 *   GET /v1/users/me           - Get current user's profile
 *   GET /v1/users/{id}         - Get a user's profile (admin/coach only)
 *   GET /v1/users              - List users (admin/coach only)
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$user_id_param = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && $user_id_param === 'me') {
    handleGetProfile($auth);
} elseif ($method === 'GET' && !$user_id_param) {
    handleListUsers($auth);
} elseif ($method === 'GET' && $user_id_param && !$action) {
    handleGetUser($auth, (int) $user_id_param);
} else {
    apiResponse(404, ['success' => false, 'error' => 'User endpoint not found']);
}

/**
 * GET /v1/users/me
 */
function handleGetProfile($auth) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT id, email, first_name, last_name, role, phone, position,
                   primary_arena, profile_image, created_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            apiResponse(404, ['success' => false, 'error' => 'User not found']);
        }

        $user['first_name'] = FieldEncryption::decrypt($user['first_name'] ?? '');
        $user['last_name'] = FieldEncryption::decrypt($user['last_name'] ?? '');
        $user['phone'] = FieldEncryption::decrypt($user['phone'] ?? '');
        $user['name'] = trim($user['first_name'] . ' ' . $user['last_name']);

        logApiAccess('get_profile', "Viewed own profile", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $user]);
    } catch (PDOException $e) {
        error_log('[API USERS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/users
 */
function handleListUsers($auth) {
    global $pdo;

    // Only coaches and admins can list users
    $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $allowed)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = ['u.is_active = 1'];
    $params = [];

    if (!empty($_GET['role'])) {
        $where[] = 'u.role = ?';
        $params[] = $_GET['role'];
    }

    // Non-admin coaches can only see their athletes
    if ($auth['user_role'] !== 'admin') {
        $where[] = '(u.assigned_coach_id = ? OR u.id = ?)';
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.position,
                   u.primary_arena, u.profile_image, u.created_at
            FROM users u
            $where_sql
            ORDER BY u.last_name, u.first_name
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['first_name'] = FieldEncryption::decrypt($user['first_name'] ?? '');
            $user['last_name'] = FieldEncryption::decrypt($user['last_name'] ?? '');
            $user['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        }
        unset($user);

        logApiAccess('list_users', "Listed users (page $page)", $auth['user_id']);
        paginatedResponse($users, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API USERS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/users/{id}
 */
function handleGetUser($auth, $target_user_id) {
    global $pdo;

    // Users can view their own profile; coaches/admins can view their athletes
    if ($target_user_id !== $auth['user_id']) {
        $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
        if (!in_array($auth['user_role'], $allowed)) {
            apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
        }
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, email, first_name, last_name, role, phone, position,
                   primary_arena, profile_image, created_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$target_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            apiResponse(404, ['success' => false, 'error' => 'User not found']);
        }

        $user['first_name'] = FieldEncryption::decrypt($user['first_name'] ?? '');
        $user['last_name'] = FieldEncryption::decrypt($user['last_name'] ?? '');
        $user['phone'] = FieldEncryption::decrypt($user['phone'] ?? '');
        $user['name'] = trim($user['first_name'] . ' ' . $user['last_name']);

        logApiAccess('get_user', "Viewed user ID: $target_user_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $user]);
    } catch (PDOException $e) {
        error_log('[API USERS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
