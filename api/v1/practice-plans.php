<?php
/**
 * API v1 - Practice Plan Endpoints
 * Provides practice plan management for ACWolvesAPP.
 *
 * Endpoints:
 *   GET    /v1/practice-plans          - List practice plans
 *   GET    /v1/practice-plans/{id}     - Get practice plan details
 *   POST   /v1/practice-plans          - Create a practice plan
 *   PUT    /v1/practice-plans/{id}     - Update a practice plan
 *   DELETE /v1/practice-plans/{id}     - Delete a practice plan
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$plan_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$plan_id) {
    handleListPlans($auth);
} elseif ($method === 'GET' && $plan_id && !$action) {
    handleGetPlan($auth, (int) $plan_id);
} elseif ($method === 'POST' && !$plan_id) {
    handleCreatePlan($auth);
} elseif ($method === 'PUT' && $plan_id && !$action) {
    handleUpdatePlan($auth, (int) $plan_id);
} elseif ($method === 'DELETE' && $plan_id && !$action) {
    handleDeletePlan($auth, (int) $plan_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Practice plan endpoint not found']);
}

/**
 * GET /v1/practice-plans
 */
function handleListPlans($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if (!empty($_GET['age_group'])) {
        $where[] = 'pp.age_group = ?';
        $params[] = $_GET['age_group'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM practice_plans pp $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT pp.id, pp.name, pp.title, pp.description, pp.focus_area, pp.age_group,
                   pp.duration_minutes, pp.difficulty_level, pp.created_by, pp.created_at,
                   u.first_name AS creator_first_name, u.last_name AS creator_last_name
            FROM practice_plans pp
            LEFT JOIN users u ON pp.created_by = u.id
            $where_sql
            ORDER BY pp.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($plans as &$plan) {
            $plan['created_by_name'] = trim(
                FieldEncryption::decrypt($plan['creator_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($plan['creator_last_name'] ?? '')
            );
            unset($plan['creator_first_name'], $plan['creator_last_name']);
        }
        unset($plan);

        logApiAccess('list_practice_plans', "Listed practice plans (page $page)", $auth['user_id']);
        paginatedResponse($plans, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API PRACTICE PLANS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/practice-plans/{id}
 */
function handleGetPlan($auth, $plan_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT pp.*, u.first_name AS creator_first_name, u.last_name AS creator_last_name
            FROM practice_plans pp
            LEFT JOIN users u ON pp.created_by = u.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            apiResponse(404, ['success' => false, 'error' => 'Practice plan not found']);
        }

        $plan['created_by_name'] = trim(
            FieldEncryption::decrypt($plan['creator_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($plan['creator_last_name'] ?? '')
        );
        unset($plan['creator_first_name'], $plan['creator_last_name']);

        // Get associated drills
        $drill_stmt = $pdo->prepare("
            SELECT d.id, d.title, d.description, ppd.drill_order, ppd.duration_minutes, ppd.notes
            FROM practice_plan_drills ppd
            INNER JOIN drills d ON ppd.drill_id = d.id
            WHERE ppd.practice_plan_id = ?
            ORDER BY ppd.drill_order
        ");
        $drill_stmt->execute([$plan_id]);
        $plan['drills'] = $drill_stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('get_practice_plan', "Viewed practice plan ID: $plan_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $plan]);
    } catch (PDOException $e) {
        error_log('[API PRACTICE PLANS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/practice-plans
 */
function handleCreatePlan($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $name = trim($body['title'] ?? $body['name'] ?? '');

    if (empty($name)) {
        apiResponse(400, ['success' => false, 'error' => 'title is required']);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO practice_plans (name, title, description, focus_area, age_group, duration_minutes, difficulty_level, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $name,
            trim($body['description'] ?? ''),
            $body['focus_area'] ?? null,
            $body['age_group'] ?? null,
            (int) ($body['duration'] ?? $body['duration_minutes'] ?? 60),
            $body['difficulty_level'] ?? 'intermediate',
            $auth['user_id'],
        ]);
        $new_id = (int) $pdo->lastInsertId();

        logApiAccess('create_practice_plan', "Created practice plan ID: $new_id", $auth['user_id']);
        apiResponse(201, [
            'success' => true,
            'message' => 'Practice plan created successfully',
            'data' => ['id' => $new_id],
        ]);
    } catch (PDOException $e) {
        error_log('[API PRACTICE PLANS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/practice-plans/{id}
 */
function handleUpdatePlan($auth, $plan_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();

    try {
        $stmt = $pdo->prepare("SELECT id, created_by FROM practice_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            apiResponse(404, ['success' => false, 'error' => 'Practice plan not found']);
        }

        $updates = [];
        $params = [];

        foreach (['name', 'title', 'description', 'focus_area', 'age_group', 'difficulty_level'] as $field) {
            if (isset($body[$field])) {
                $updates[] = "$field = ?";
                $params[] = $body[$field];
            }
        }
        if (isset($body['duration']) || isset($body['duration_minutes'])) {
            $updates[] = 'duration_minutes = ?';
            $params[] = (int) ($body['duration'] ?? $body['duration_minutes']);
        }

        if (empty($updates)) {
            apiResponse(400, ['success' => false, 'error' => 'No updatable fields provided']);
        }

        $params[] = $plan_id;
        $stmt = $pdo->prepare("UPDATE practice_plans SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);

        logApiAccess('update_practice_plan', "Updated practice plan ID: $plan_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Practice plan updated successfully']);
    } catch (PDOException $e) {
        error_log('[API PRACTICE PLANS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * DELETE /v1/practice-plans/{id}
 */
function handleDeletePlan($auth, $plan_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_drills')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM practice_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        if (!$stmt->fetch()) {
            apiResponse(404, ['success' => false, 'error' => 'Practice plan not found']);
        }

        $stmt = $pdo->prepare("DELETE FROM practice_plans WHERE id = ?");
        $stmt->execute([$plan_id]);

        logApiAccess('delete_practice_plan', "Deleted practice plan ID: $plan_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Practice plan deleted successfully']);
    } catch (PDOException $e) {
        error_log('[API PRACTICE PLANS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
