<?php
/**
 * API v1 - Evaluation Endpoints
 * Provides evaluation access for ACWolvesAPP.
 *
 * Endpoints:
 *   GET  /v1/evaluations          - List evaluations
 *   GET  /v1/evaluations/{id}     - Get evaluation details
 *   POST /v1/evaluations          - Create an evaluation
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$eval_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$eval_id) {
    handleListEvaluations($auth);
} elseif ($method === 'GET' && $eval_id && !$action) {
    handleGetEvaluation($auth, (int) $eval_id);
} elseif ($method === 'POST' && !$eval_id) {
    handleCreateEvaluation($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Evaluation endpoint not found']);
}

/**
 * GET /v1/evaluations
 */
function handleListEvaluations($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    // Role-based access
    if ($auth['user_role'] === 'athlete') {
        $where[] = 'ae.athlete_id = ?';
        $params[] = $auth['user_id'];
    } elseif (in_array($auth['user_role'], ['coach', 'coach_plus', 'health_coach', 'team_coach'])) {
        $where[] = '(ae.evaluator_id = ? OR ae.athlete_id IN (SELECT id FROM users WHERE assigned_coach_id = ?))';
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
    }

    if (!empty($_GET['athlete_id'])) {
        $where[] = 'ae.athlete_id = ?';
        $params[] = (int) $_GET['athlete_id'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM athlete_evaluations ae $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ae.id, ae.athlete_id, ae.evaluator_id, ae.skill_id, ae.rating,
                   ae.comments, ae.notes, ae.evaluation_date, ae.status, ae.created_at,
                   a.first_name AS athlete_first_name, a.last_name AS athlete_last_name,
                   e.first_name AS evaluator_first_name, e.last_name AS evaluator_last_name,
                   es.name AS skill_name, ec.name AS category_name
            FROM athlete_evaluations ae
            LEFT JOIN users a ON ae.athlete_id = a.id
            LEFT JOIN users e ON ae.evaluator_id = e.id
            LEFT JOIN eval_skills es ON ae.skill_id = es.id
            LEFT JOIN eval_categories ec ON es.category_id = ec.id
            $where_sql
            ORDER BY ae.evaluation_date DESC, ae.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $evals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($evals as &$eval) {
            $eval['athlete_name'] = trim(
                FieldEncryption::decrypt($eval['athlete_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($eval['athlete_last_name'] ?? '')
            );
            $eval['coach_name'] = trim(
                FieldEncryption::decrypt($eval['evaluator_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($eval['evaluator_last_name'] ?? '')
            );
            unset($eval['athlete_first_name'], $eval['athlete_last_name']);
            unset($eval['evaluator_first_name'], $eval['evaluator_last_name']);
        }
        unset($eval);

        logApiAccess('list_evaluations', "Listed evaluations (page $page)", $auth['user_id']);
        paginatedResponse($evals, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API EVALUATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/evaluations/{id}
 */
function handleGetEvaluation($auth, $eval_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT ae.*, 
                   a.first_name AS athlete_first_name, a.last_name AS athlete_last_name,
                   e.first_name AS evaluator_first_name, e.last_name AS evaluator_last_name,
                   es.name AS skill_name, ec.name AS category_name
            FROM athlete_evaluations ae
            LEFT JOIN users a ON ae.athlete_id = a.id
            LEFT JOIN users e ON ae.evaluator_id = e.id
            LEFT JOIN eval_skills es ON ae.skill_id = es.id
            LEFT JOIN eval_categories ec ON es.category_id = ec.id
            WHERE ae.id = ?
        ");
        $stmt->execute([$eval_id]);
        $eval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$eval) {
            apiResponse(404, ['success' => false, 'error' => 'Evaluation not found']);
        }

        // Access control
        if ($auth['user_role'] === 'athlete' && $eval['athlete_id'] != $auth['user_id']) {
            apiResponse(403, ['success' => false, 'error' => 'Access denied']);
        }

        $eval['athlete_name'] = trim(
            FieldEncryption::decrypt($eval['athlete_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($eval['athlete_last_name'] ?? '')
        );
        $eval['coach_name'] = trim(
            FieldEncryption::decrypt($eval['evaluator_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($eval['evaluator_last_name'] ?? '')
        );
        unset($eval['athlete_first_name'], $eval['athlete_last_name']);
        unset($eval['evaluator_first_name'], $eval['evaluator_last_name']);

        logApiAccess('get_evaluation', "Viewed evaluation ID: $eval_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $eval]);
    } catch (PDOException $e) {
        error_log('[API EVALUATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/evaluations
 */
function handleCreateEvaluation($auth) {
    global $pdo;

    $coach_roles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $coach_roles)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $athlete_id = (int) ($body['athleteId'] ?? $body['athlete_id'] ?? 0);

    if (!$athlete_id) {
        apiResponse(400, ['success' => false, 'error' => 'athleteId is required']);
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'athlete'");
        $stmt->execute([$athlete_id]);
        if (!$stmt->fetch()) {
            apiResponse(404, ['success' => false, 'error' => 'Athlete not found']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO athlete_evaluations (athlete_id, evaluator_id, skill_id, rating, comments, notes, evaluation_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $athlete_id,
            $auth['user_id'],
            !empty($body['skill_id']) ? (int) $body['skill_id'] : null,
            isset($body['rating']) || isset($body['score']) ? (int) ($body['rating'] ?? $body['score']) : null,
            $body['notes'] ?? $body['comments'] ?? null,
            $body['notes'] ?? null,
            $body['date'] ?? $body['evaluation_date'] ?? date('Y-m-d'),
            'completed',
        ]);
        $new_id = (int) $pdo->lastInsertId();

        logApiAccess('create_evaluation', "Created evaluation ID: $new_id for athlete $athlete_id", $auth['user_id']);
        apiResponse(201, [
            'success' => true,
            'message' => 'Evaluation created successfully',
            'data' => ['id' => $new_id],
        ]);
    } catch (PDOException $e) {
        error_log('[API EVALUATIONS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
