<?php
/**
 * API v1 - Authentication Endpoints
 * Provides token-based authentication for external applications.
 *
 * Endpoints:
 *   POST /v1/auth/login       - Authenticate with email/password, returns API key
 *   POST /v1/auth/validate    - Validate an existing API key
 *   POST /v1/auth/logout      - Deactivate an API key
 */

require_once __DIR__ . '/../api_auth.php';

$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? '';

switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            apiResponse(405, ['success' => false, 'error' => 'Method not allowed. Use POST.']);
        }
        handleLogin();
        break;

    case 'validate':
        if ($method !== 'POST' && $method !== 'GET') {
            apiResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
        }
        handleValidate();
        break;

    case 'logout':
        if ($method !== 'POST') {
            apiResponse(405, ['success' => false, 'error' => 'Method not allowed. Use POST.']);
        }
        handleLogout();
        break;

    default:
        apiResponse(404, ['success' => false, 'error' => 'Auth endpoint not found. Use: login, validate, logout']);
}

/**
 * POST /v1/auth/login
 * Authenticate with email and password. Returns an API key for subsequent requests.
 */
function handleLogin() {
    global $pdo, $db_connected;

    if (!$db_connected || !$pdo) {
        apiResponse(503, ['success' => false, 'error' => 'Service unavailable']);
    }

    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $key_name = trim($body['key_name'] ?? 'API Login');

    if (empty($email) || empty($password)) {
        apiResponse(400, ['success' => false, 'error' => 'Email and password are required']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, email, password, first_name, last_name, role, is_active, is_verified
            FROM users WHERE email = ? LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            logApiAccess('login_failed', "Failed API login attempt for: $email");
            apiResponse(401, ['success' => false, 'error' => 'Invalid email or password']);
        }

        if (!$user['is_active'] || !$user['is_verified']) {
            apiResponse(403, ['success' => false, 'error' => 'Account is inactive or unverified']);
        }

        // Generate API key
        $api_key = bin2hex(random_bytes(32));

        // Store the API key
        $stmt = $pdo->prepare("
            INSERT INTO api_keys (user_id, api_key, key_name, permissions, is_active, created_at, expires_at)
            VALUES (?, ?, ?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");

        // Default permissions based on role
        $permissions = getDefaultPermissions($user['role']);

        $stmt->execute([
            $user['id'],
            $api_key,
            $key_name,
            json_encode($permissions),
        ]);

        $first_name = FieldEncryption::decrypt($user['first_name']);
        $last_name = FieldEncryption::decrypt($user['last_name']);

        logApiAccess('login_success', "API login for user ID: {$user['id']}", $user['id']);

        apiResponse(200, [
            'success'  => true,
            'api_key'  => $api_key,
            'user' => [
                'id'    => (int) $user['id'],
                'email' => $user['email'],
                'name'  => trim($first_name . ' ' . $last_name),
                'role'  => $user['role'],
            ],
            'expires_at' => date('Y-m-d\TH:i:s\Z', strtotime('+30 days')),
        ]);
    } catch (PDOException $e) {
        error_log('[API AUTH LOGIN ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST|GET /v1/auth/validate
 * Validate an existing API key and return user info.
 */
function handleValidate() {
    $auth = authenticateApiRequest();

    if (!$auth['authenticated']) {
        apiResponse(401, ['success' => false, 'error' => $auth['error']]);
    }

    apiResponse(200, [
        'success' => true,
        'valid'   => true,
        'user' => [
            'id'    => $auth['user_id'],
            'email' => $auth['user_email'],
            'name'  => $auth['user_name'],
            'role'  => $auth['user_role'],
        ],
        'permissions' => $auth['permissions'],
    ]);
}

/**
 * POST /v1/auth/logout
 * Deactivate the current API key.
 */
function handleLogout() {
    global $pdo;

    $auth = requireApiAuth();

    try {
        $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
        $stmt->execute([$auth['api_key_id']]);

        logApiAccess('logout', "API key deactivated for user ID: {$auth['user_id']}", $auth['user_id']);

        apiResponse(200, ['success' => true, 'message' => 'API key deactivated']);
    } catch (PDOException $e) {
        error_log('[API AUTH LOGOUT ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * Get default API permissions based on user role.
 *
 * @param string $role
 * @return array
 */
function getDefaultPermissions($role) {
    $base = ['read_profile', 'read_notifications'];

    switch ($role) {
        case 'admin':
            return ['*'];
        case 'coach':
        case 'coach_plus':
        case 'health_coach':
        case 'team_coach':
            return array_merge($base, [
                'read_videos', 'write_videos', 'review_videos',
                'read_sessions', 'write_sessions',
                'read_teams', 'read_athletes',
                'read_drills', 'write_drills',
                'read_bookings',
            ]);
        case 'athlete':
            return array_merge($base, [
                'read_videos', 'upload_videos',
                'read_sessions', 'read_bookings', 'write_bookings',
                'read_teams', 'read_drills',
            ]);
        case 'parent':
            return array_merge($base, [
                'read_videos', 'read_sessions', 'read_bookings',
                'read_teams',
            ]);
        default:
            return $base;
    }
}
