<?php
/**
 * API v1 - Team Endpoints
 *
 * Endpoints:
 *   GET /v1/teams              - List teams
 *   GET /v1/teams/{id}         - Get team details
 *   GET /v1/teams/{id}/roster  - Get team roster
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$team_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$team_id) {
    handleListTeams($auth);
} elseif ($method === 'GET' && $team_id && !$action) {
    handleGetTeam($auth, (int) $team_id);
} elseif ($method === 'GET' && $team_id && $action === 'roster') {
    handleGetTeamRoster($auth, (int) $team_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Team endpoint not found']);
}

/**
 * GET /v1/teams
 */
function handleListTeams($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_teams')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = ['t.is_active = 1'];
    $params = [];

    if (!empty($_GET['division'])) {
        $where[] = 't.division = ?';
        $params[] = $_GET['division'];
    }
    if (!empty($_GET['season'])) {
        $where[] = 't.season = ?';
        $params[] = $_GET['season'];
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM teams t $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT t.id, t.name, t.age_group, t.skill_level, t.division, t.season,
                   t.coach_id, t.assistant_coach_id, t.is_active, t.created_at,
                   c.first_name AS coach_first_name, c.last_name AS coach_last_name,
                   ac.first_name AS asst_first_name, ac.last_name AS asst_last_name
            FROM teams t
            LEFT JOIN users c ON t.coach_id = c.id
            LEFT JOIN users ac ON t.assistant_coach_id = ac.id
            $where_sql
            ORDER BY t.name
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($teams as &$team) {
            $team['coach_name'] = trim(
                FieldEncryption::decrypt($team['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($team['coach_last_name'] ?? '')
            );
            $team['assistant_coach_name'] = trim(
                FieldEncryption::decrypt($team['asst_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($team['asst_last_name'] ?? '')
            );
            unset($team['coach_first_name'], $team['coach_last_name']);
            unset($team['asst_first_name'], $team['asst_last_name']);
        }
        unset($team);

        logApiAccess('list_teams', "Listed teams (page $page)", $auth['user_id']);
        paginatedResponse($teams, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API TEAMS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/teams/{id}
 */
function handleGetTeam($auth, $team_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_teams')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   c.first_name AS coach_first_name, c.last_name AS coach_last_name,
                   ac.first_name AS asst_first_name, ac.last_name AS asst_last_name
            FROM teams t
            LEFT JOIN users c ON t.coach_id = c.id
            LEFT JOIN users ac ON t.assistant_coach_id = ac.id
            WHERE t.id = ?
        ");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$team) {
            apiResponse(404, ['success' => false, 'error' => 'Team not found']);
        }

        $team['coach_name'] = trim(
            FieldEncryption::decrypt($team['coach_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($team['coach_last_name'] ?? '')
        );
        $team['assistant_coach_name'] = trim(
            FieldEncryption::decrypt($team['asst_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($team['asst_last_name'] ?? '')
        );
        unset($team['coach_first_name'], $team['coach_last_name']);
        unset($team['asst_first_name'], $team['asst_last_name']);

        logApiAccess('get_team', "Viewed team ID: $team_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $team]);
    } catch (PDOException $e) {
        error_log('[API TEAMS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/teams/{id}/roster
 */
function handleGetTeamRoster($auth, $team_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_teams')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.position, u.role
            FROM team_roster tr
            INNER JOIN users u ON tr.user_id = u.id
            WHERE tr.team_id = ?
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$team_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($members as &$member) {
            $member['first_name'] = FieldEncryption::decrypt($member['first_name'] ?? '');
            $member['last_name'] = FieldEncryption::decrypt($member['last_name'] ?? '');
            $member['name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        }
        unset($member);

        logApiAccess('get_team_roster', "Viewed roster for team ID: $team_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $members]);
    } catch (PDOException $e) {
        error_log('[API TEAMS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
