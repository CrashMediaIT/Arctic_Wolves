<?php
/**
 * API v1 - Athlete Endpoints
 * Provides athlete management for ACWolvesAPP.
 *
 * Endpoints:
 *   GET  /v1/athletes          - List athletes
 *   GET  /v1/athletes/{id}     - Get athlete details
 *   POST /v1/athletes          - Create an athlete
 *   PUT  /v1/athletes/{id}     - Update an athlete
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$athlete_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$athlete_id) {
    handleListAthletes($auth);
} elseif ($method === 'GET' && $athlete_id && !$action) {
    handleGetAthlete($auth, (int) $athlete_id);
} elseif ($method === 'POST' && !$athlete_id) {
    handleCreateAthlete($auth);
} elseif ($method === 'PUT' && $athlete_id && !$action) {
    handleUpdateAthlete($auth, (int) $athlete_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Athlete endpoint not found']);
}

/**
 * GET /v1/athletes
 */
function handleListAthletes($auth) {
    global $pdo;

    $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $allowed)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = ["u.role = 'athlete'", 'u.is_active = 1'];
    $params = [];

    // Non-admin coaches see only their assigned athletes
    if ($auth['user_role'] !== 'admin') {
        $where[] = '(u.assigned_coach_id = ? OR u.id IN (SELECT athlete_id FROM team_roster tr INNER JOIN teams t ON tr.team_id = t.id WHERE t.coach_id = ? OR t.assistant_coach_id = ?))';
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
        $params[] = $auth['user_id'];
    }

    if (!empty($_GET['team_id'])) {
        $where[] = 'u.id IN (SELECT athlete_id FROM team_roster WHERE team_id = ?)';
        $params[] = (int) $_GET['team_id'];
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.position,
                   u.birth_date, u.primary_arena, u.profile_image, u.created_at,
                   t.id AS team_id, t.name AS team_name
            FROM users u
            LEFT JOIN team_roster tr ON u.id = tr.athlete_id
            LEFT JOIN teams t ON tr.team_id = t.id AND t.is_active = 1
            $where_sql
            GROUP BY u.id
            ORDER BY u.last_name, u.first_name
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($athletes as &$athlete) {
            $athlete['first_name'] = FieldEncryption::decrypt($athlete['first_name'] ?? '');
            $athlete['last_name'] = FieldEncryption::decrypt($athlete['last_name'] ?? '');
            $athlete['birth_date'] = FieldEncryption::decrypt($athlete['birth_date'] ?? '');
            $athlete['name'] = trim($athlete['first_name'] . ' ' . $athlete['last_name']);
        }
        unset($athlete);

        logApiAccess('list_athletes', "Listed athletes (page $page)", $auth['user_id']);
        paginatedResponse($athletes, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API ATHLETES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/athletes/{id}
 */
function handleGetAthlete($auth, $athlete_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.position,
                   u.birth_date, u.phone, u.primary_arena, u.profile_image, u.created_at,
                   t.id AS team_id, t.name AS team_name
            FROM users u
            LEFT JOIN team_roster tr ON u.id = tr.athlete_id
            LEFT JOIN teams t ON tr.team_id = t.id AND t.is_active = 1
            WHERE u.id = ? AND u.role = 'athlete'
        ");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$athlete) {
            apiResponse(404, ['success' => false, 'error' => 'Athlete not found']);
        }

        // Access control
        if ($auth['user_role'] === 'athlete' && $auth['user_id'] !== $athlete_id) {
            apiResponse(403, ['success' => false, 'error' => 'Access denied']);
        }

        $athlete['first_name'] = FieldEncryption::decrypt($athlete['first_name'] ?? '');
        $athlete['last_name'] = FieldEncryption::decrypt($athlete['last_name'] ?? '');
        $athlete['birth_date'] = FieldEncryption::decrypt($athlete['birth_date'] ?? '');
        $athlete['phone'] = FieldEncryption::decrypt($athlete['phone'] ?? '');
        $athlete['name'] = trim($athlete['first_name'] . ' ' . $athlete['last_name']);

        logApiAccess('get_athlete', "Viewed athlete ID: $athlete_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $athlete]);
    } catch (PDOException $e) {
        error_log('[API ATHLETES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/athletes
 */
function handleCreateAthlete($auth) {
    global $pdo;

    $allowed = ['admin', 'coach', 'coach_plus'];
    if (!in_array($auth['user_role'], $allowed)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $first_name = trim($body['firstName'] ?? $body['first_name'] ?? '');
    $last_name = trim($body['lastName'] ?? $body['last_name'] ?? '');

    if (empty($email) || empty($first_name) || empty($last_name)) {
        apiResponse(400, ['success' => false, 'error' => 'email, firstName, and lastName are required']);
    }

    try {
        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            apiResponse(409, ['success' => false, 'error' => 'Email already in use']);
        }

        $encrypted_first = FieldEncryption::encrypt($first_name);
        $encrypted_last = FieldEncryption::encrypt($last_name);
        $temp_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, first_name, last_name, role, position, assigned_coach_id, is_active, is_verified, created_by_coach_id)
            VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1, ?)
        ");
        $stmt->execute([
            $email,
            $temp_password,
            $encrypted_first,
            $encrypted_last,
            $body['position'] ?? null,
            $auth['user_id'],
            $auth['user_id'],
        ]);
        $new_id = (int) $pdo->lastInsertId();

        logApiAccess('create_athlete', "Created athlete ID: $new_id", $auth['user_id']);
        apiResponse(201, [
            'success' => true,
            'message' => 'Athlete created successfully',
            'data' => ['id' => $new_id, 'email' => $email],
        ]);
    } catch (PDOException $e) {
        error_log('[API ATHLETES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/athletes/{id}
 */
function handleUpdateAthlete($auth, $athlete_id) {
    global $pdo;

    $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $allowed) && $auth['user_id'] !== $athlete_id) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'athlete'");
        $stmt->execute([$athlete_id]);
        if (!$stmt->fetch()) {
            apiResponse(404, ['success' => false, 'error' => 'Athlete not found']);
        }

        $updates = [];
        $params = [];

        if (isset($body['position'])) {
            $updates[] = 'position = ?';
            $params[] = $body['position'];
        }
        if (isset($body['firstName']) || isset($body['first_name'])) {
            $updates[] = 'first_name = ?';
            $params[] = FieldEncryption::encrypt($body['firstName'] ?? $body['first_name']);
        }
        if (isset($body['lastName']) || isset($body['last_name'])) {
            $updates[] = 'last_name = ?';
            $params[] = FieldEncryption::encrypt($body['lastName'] ?? $body['last_name']);
        }
        if (isset($body['primary_arena'])) {
            $updates[] = 'primary_arena = ?';
            $params[] = $body['primary_arena'];
        }

        if (empty($updates)) {
            apiResponse(400, ['success' => false, 'error' => 'No updatable fields provided']);
        }

        $params[] = $athlete_id;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);

        logApiAccess('update_athlete', "Updated athlete ID: $athlete_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'message' => 'Athlete updated successfully']);
    } catch (PDOException $e) {
        error_log('[API ATHLETES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
