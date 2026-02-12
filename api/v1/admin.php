<?php
/**
 * API v1 - Admin Endpoints
 * Provides admin panel data for ACWolvesAPP.
 *
 * Endpoints:
 *   GET /v1/admin/users          - List all users (admin)
 *   GET /v1/admin/audit-logs     - List audit logs
 *   GET /v1/admin/system-health  - System health status
 *   GET /v1/admin/permissions    - List permissions
 *   GET /v1/admin/settings       - Get system settings
 *   PUT /v1/admin/settings       - Update system settings
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;

// All admin endpoints require admin role
if ($auth['user_role'] !== 'admin') {
    apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
}

if ($method === 'GET' && $action === 'users') {
    handleAdminListUsers($auth);
} elseif ($method === 'GET' && $action === 'audit-logs') {
    handleAuditLogs($auth);
} elseif ($method === 'GET' && $action === 'system-health') {
    handleSystemHealth($auth);
} elseif ($method === 'GET' && $action === 'permissions') {
    handlePermissions($auth);
} elseif ($method === 'GET' && $action === 'settings') {
    handleGetSettings($auth);
} elseif ($method === 'PUT' && $action === 'settings') {
    handleUpdateSettings($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Admin endpoint not found. Use: users, audit-logs, system-health, permissions, settings']);
}

/**
 * GET /v1/admin/users
 */
function handleAdminListUsers($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if (!empty($_GET['role'])) {
        $where[] = 'u.role = ?';
        $params[] = $_GET['role'];
    }
    if (isset($_GET['is_active'])) {
        $where[] = 'u.is_active = ?';
        $params[] = (int) $_GET['is_active'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.is_active,
                   u.is_verified, u.position, u.primary_arena, u.profile_image, u.created_at
            FROM users u
            $where_sql
            ORDER BY u.created_at DESC
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

        logApiAccess('admin_list_users', "Listed users (page $page)", $auth['user_id']);
        paginatedResponse($users, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API ADMIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/admin/audit-logs
 */
function handleAuditLogs($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs");
        $count_stmt->execute();
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT al.id, al.user_id, al.action_type, al.table_name, al.record_id,
                   al.action, al.description, al.ip_address, al.created_at,
                   u.first_name, u.last_name, u.email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['user_name'] = trim(
                FieldEncryption::decrypt($log['first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($log['last_name'] ?? '')
            );
            unset($log['first_name'], $log['last_name']);
        }
        unset($log);

        logApiAccess('admin_audit_logs', "Listed audit logs (page $page)", $auth['user_id']);
        paginatedResponse($logs, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API ADMIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/admin/system-health
 */
function handleSystemHealth($auth) {
    global $pdo, $db_connected;

    $health = [
        'status' => 'healthy',
        'database' => $db_connected ? 'connected' : 'disconnected',
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d\TH:i:s\Z'),
    ];

    try {
        // Table counts
        $tables = ['users', 'sessions', 'teams', 'bookings', 'videos', 'notifications'];
        $counts = [];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $counts[$table] = (int) $stmt->fetchColumn();
        }
        $health['record_counts'] = $counts;

        // Disk usage (uploads directory)
        $upload_dir = __DIR__ . '/../../uploads';
        if (is_dir($upload_dir)) {
            $health['uploads_dir'] = 'exists';
        } else {
            $health['uploads_dir'] = 'missing';
        }
    } catch (PDOException $e) {
        $health['status'] = 'degraded';
        $health['error'] = 'Some health checks failed';
    }

    logApiAccess('admin_system_health', 'Viewed system health', $auth['user_id']);
    apiResponse(200, ['success' => true, 'data' => $health]);
}

/**
 * GET /v1/admin/permissions
 */
function handlePermissions($auth) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT id, permission_name, description, category
            FROM permissions
            ORDER BY category, permission_name
        ");
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('admin_permissions', 'Listed permissions', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $permissions]);
    } catch (PDOException $e) {
        error_log('[API ADMIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/admin/settings
 */
function handleGetSettings($auth) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type, description FROM system_settings ORDER BY setting_key");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }

        logApiAccess('admin_get_settings', 'Viewed system settings', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $result]);
    } catch (PDOException $e) {
        error_log('[API ADMIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/admin/settings
 */
function handleUpdateSettings($auth) {
    global $pdo;

    $body = getJsonBody();

    if (empty($body)) {
        apiResponse(400, ['success' => false, 'error' => 'No settings provided']);
    }

    try {
        $updated = 0;
        foreach ($body as $key => $value) {
            // Only allow updating existing settings
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([(string) $value, (string) $key]);
            $updated += $stmt->rowCount();
        }

        logApiAccess('admin_update_settings', "Updated $updated settings", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => "$updated settings updated"]);
    } catch (PDOException $e) {
        error_log('[API ADMIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
