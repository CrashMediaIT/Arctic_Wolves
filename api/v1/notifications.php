<?php
/**
 * API v1 - Notification Endpoints
 *
 * Endpoints:
 *   GET  /v1/notifications           - List user's notifications
 *   PUT  /v1/notifications/{id}      - Mark notification as read
 *   PUT  /v1/notifications/read-all  - Mark all notifications as read
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$notification_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$notification_id) {
    handleListNotifications($auth);
} elseif ($method === 'PUT' && $notification_id === 'read-all') {
    handleMarkAllRead($auth);
} elseif ($method === 'PUT' && $notification_id) {
    handleMarkRead($auth, (int) $notification_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Notification endpoint not found']);
}

/**
 * GET /v1/notifications
 */
function handleListNotifications($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_notifications')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;
    $unread_only = ($_GET['unread'] ?? '') === '1';

    $where = ['n.user_id = ?'];
    $params = [$auth['user_id']];

    if ($unread_only) {
        $where[] = 'n.is_read = 0';
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT n.id, n.notification_type, n.title, n.message, n.link_url,
                   n.is_read, n.created_at
            FROM notifications n
            $where_sql
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unread count
        $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unread_stmt->execute([$auth['user_id']]);
        $unread_count = (int) $unread_stmt->fetchColumn();

        logApiAccess('list_notifications', "Listed notifications (page $page)", $auth['user_id']);
        apiResponse(200, [
            'success' => true,
            'data'    => $notifications,
            'unread_count' => $unread_count,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
                'pages'    => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
            ],
        ]);
    } catch (PDOException $e) {
        error_log('[API NOTIFICATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/notifications/{id}
 */
function handleMarkRead($auth, $notification_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $auth['user_id']]);

        if ($stmt->rowCount() === 0) {
            apiResponse(404, ['success' => false, 'error' => 'Notification not found']);
        }

        apiResponse(200, ['success' => true, 'message' => 'Notification marked as read']);
    } catch (PDOException $e) {
        error_log('[API NOTIFICATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/notifications/read-all
 */
function handleMarkAllRead($auth) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$auth['user_id']]);
        $count = $stmt->rowCount();

        logApiAccess('mark_all_read', "Marked $count notifications as read", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => "$count notifications marked as read"]);
    } catch (PDOException $e) {
        error_log('[API NOTIFICATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
