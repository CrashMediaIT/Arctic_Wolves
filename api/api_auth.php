<?php
/**
 * API Authentication Middleware
 * Validates API keys and bearer tokens for REST API access.
 * Uses the existing `api_keys` table in the database.
 */

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../lib/encryption.php';

/**
 * Authenticate an API request using API key or bearer token.
 * Checks the Authorization header for:
 *   - Bearer <api_key>
 *   - ApiKey <api_key>
 * Also checks X-API-Key header and ?api_key query parameter.
 *
 * @return array ['authenticated' => bool, 'user_id' => int|null, 'permissions' => array, 'error' => string|null]
 */
function authenticateApiRequest() {
    global $pdo, $db_connected;

    if (!$db_connected || !$pdo) {
        return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'Database unavailable'];
    }

    $api_key = extractApiKey();

    if (empty($api_key)) {
        return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'API key required. Provide via Authorization header (Bearer <key>) or X-API-Key header.'];
    }

    // Validate key format: must be 32-128 hex characters
    if (!preg_match('/^[a-fA-F0-9]{32,128}$/', $api_key)) {
        return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'Invalid API key format'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT ak.id, ak.user_id, ak.permissions, ak.is_active, ak.expires_at,
                   u.role AS user_role, u.is_active AS user_active, u.is_verified, u.email,
                   u.first_name, u.last_name
            FROM api_keys ak
            INNER JOIN users u ON ak.user_id = u.id
            WHERE ak.api_key = ?
            LIMIT 1
        ");
        $stmt->execute([$api_key]);
        $key_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$key_data) {
            return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'Invalid API key'];
        }

        if (!$key_data['is_active']) {
            return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'API key is inactive'];
        }

        if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) {
            return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'API key has expired'];
        }

        if (!$key_data['user_active'] || !$key_data['is_verified']) {
            return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'User account is inactive or unverified'];
        }

        // Parse permissions
        $permissions = [];
        if (!empty($key_data['permissions'])) {
            $decoded = json_decode($key_data['permissions'], true);
            if (is_array($decoded)) {
                $permissions = $decoded;
            }
        }

        // Update last_used timestamp
        $update = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
        $update->execute([$key_data['id']]);

        // Decrypt PII
        $first_name = FieldEncryption::decrypt($key_data['first_name']);
        $last_name = FieldEncryption::decrypt($key_data['last_name']);

        return [
            'authenticated' => true,
            'user_id'       => (int) $key_data['user_id'],
            'user_role'     => $key_data['user_role'],
            'user_email'    => $key_data['email'],
            'user_name'     => trim($first_name . ' ' . $last_name),
            'permissions'   => $permissions,
            'api_key_id'    => (int) $key_data['id'],
            'error'         => null,
        ];
    } catch (PDOException $e) {
        error_log('[API AUTH ERROR] ' . $e->getMessage());
        return ['authenticated' => false, 'user_id' => null, 'permissions' => [], 'error' => 'Authentication service error'];
    }
}

/**
 * Extract the API key from the request headers or query parameters.
 *
 * @return string|null
 */
function extractApiKey() {
    // 1. Authorization header (Bearer or ApiKey)
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!empty($auth_header)) {
        if (stripos($auth_header, 'Bearer ') === 0) {
            return trim(substr($auth_header, 7));
        }
        if (stripos($auth_header, 'ApiKey ') === 0) {
            return trim(substr($auth_header, 7));
        }
    }

    // 2. X-API-Key header
    $x_api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($x_api_key)) {
        return trim($x_api_key);
    }

    // 3. Query parameter (least preferred)
    if (isset($_GET['api_key']) && !empty($_GET['api_key'])) {
        return trim($_GET['api_key']);
    }

    return null;
}

/**
 * Require authentication and halt with 401 if not authenticated.
 *
 * @return array The authenticated context
 */
function requireApiAuth() {
    $auth = authenticateApiRequest();

    if (!$auth['authenticated']) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $auth['error'] ?? 'Unauthorized',
        ]);
        exit;
    }

    return $auth;
}

/**
 * Check if the authenticated user has a specific permission.
 *
 * @param array  $auth       The authentication context from requireApiAuth()
 * @param string $permission The permission to check
 * @return bool
 */
function hasApiPermission($auth, $permission) {
    // Admins have all permissions
    if (($auth['user_role'] ?? '') === 'admin') {
        return true;
    }

    // Check explicit permissions array
    if (in_array($permission, $auth['permissions'] ?? [], true)) {
        return true;
    }

    // Check wildcard
    if (in_array('*', $auth['permissions'] ?? [], true)) {
        return true;
    }

    return false;
}

/**
 * Log an API access event.
 *
 * @param string $action      The API action performed
 * @param string $description Description of the action
 * @param int|null $user_id   User ID
 */
function logApiAccess($action, $description, $user_id = null) {
    global $pdo, $db_connected;

    if (!$db_connected || !$pdo) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, event_type, description, ip_address, user_agent, context, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            'api_' . $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            json_encode(['endpoint' => $_SERVER['REQUEST_URI'] ?? ''])
        ]);
    } catch (PDOException $e) {
        error_log('[API LOG ERROR] ' . $e->getMessage());
    }
}
