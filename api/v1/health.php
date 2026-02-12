<?php
/**
 * API v1 - Health Endpoints
 * Provides nutrition and workout data for ACWolvesAPP.
 *
 * Endpoints:
 *   GET /v1/health/nutrition/{athleteId}   - Get nutrition plans for athlete
 *   GET /v1/health/workouts/{athleteId}    - Get workout plans for athlete
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;
$target_id = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && $action === 'nutrition' && $target_id) {
    handleGetNutrition($auth, (int) $target_id);
} elseif ($method === 'GET' && $action === 'workouts' && $target_id) {
    handleGetWorkouts($auth, (int) $target_id);
} elseif ($method === 'GET' && $action === 'nutrition' && !$target_id) {
    // Default to own nutrition if no athlete specified
    handleGetNutrition($auth, $auth['user_id']);
} elseif ($method === 'GET' && $action === 'workouts' && !$target_id) {
    handleGetWorkouts($auth, $auth['user_id']);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Health endpoint not found. Use: nutrition/{athleteId}, workouts/{athleteId}']);
}

/**
 * GET /v1/health/nutrition/{athleteId}
 */
function handleGetNutrition($auth, $athlete_id) {
    global $pdo;

    // Access control
    if ($auth['user_role'] === 'athlete' && $auth['user_id'] !== $athlete_id) {
        apiResponse(403, ['success' => false, 'error' => 'Access denied']);
    }

    try {
        // Get active nutrition assignments for this athlete
        $stmt = $pdo->prepare("
            SELECT ana.id AS assignment_id, ana.status, ana.start_date, ana.notes AS assignment_notes,
                   np.id, np.name, np.title, np.description, np.target_calories,
                   np.target_protein_g, np.target_carbs_g, np.target_fat_g, np.created_at,
                   u.first_name AS coach_first_name, u.last_name AS coach_last_name
            FROM athlete_nutrition_assignments ana
            INNER JOIN nutrition_plans np ON ana.nutrition_plan_id = np.id
            LEFT JOIN users u ON ana.assigned_by = u.id
            WHERE ana.athlete_id = ?
            ORDER BY ana.assigned_date DESC
        ");
        $stmt->execute([$athlete_id]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($plans as &$plan) {
            $plan['assigned_by_name'] = trim(
                FieldEncryption::decrypt($plan['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($plan['coach_last_name'] ?? '')
            );
            unset($plan['coach_first_name'], $plan['coach_last_name']);
        }
        unset($plan);

        logApiAccess('get_nutrition', "Viewed nutrition for athlete ID: $athlete_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $plans]);
    } catch (PDOException $e) {
        error_log('[API HEALTH ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/health/workouts/{athleteId}
 */
function handleGetWorkouts($auth, $athlete_id) {
    global $pdo;

    // Access control
    if ($auth['user_role'] === 'athlete' && $auth['user_id'] !== $athlete_id) {
        apiResponse(403, ['success' => false, 'error' => 'Access denied']);
    }

    try {
        // Get active workout assignments for this athlete
        $stmt = $pdo->prepare("
            SELECT awa.id AS assignment_id, awa.status, awa.start_date, awa.notes AS assignment_notes,
                   wp.id, wp.name, wp.description, wp.duration_weeks, wp.total_workouts,
                   wp.difficulty_level, wp.created_at,
                   u.first_name AS coach_first_name, u.last_name AS coach_last_name
            FROM athlete_workout_assignments awa
            INNER JOIN workout_plans wp ON awa.workout_plan_id = wp.id
            LEFT JOIN users u ON awa.assigned_by = u.id
            WHERE awa.athlete_id = ?
            ORDER BY awa.assigned_date DESC
        ");
        $stmt->execute([$athlete_id]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($plans as &$plan) {
            $plan['assigned_by_name'] = trim(
                FieldEncryption::decrypt($plan['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($plan['coach_last_name'] ?? '')
            );
            unset($plan['coach_first_name'], $plan['coach_last_name']);
        }
        unset($plan);

        logApiAccess('get_workouts', "Viewed workouts for athlete ID: $athlete_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $plans]);
    } catch (PDOException $e) {
        error_log('[API HEALTH ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
