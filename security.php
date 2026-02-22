<?php
/**
 * Security Functions
 * Provides CSRF protection and security headers for the application
 */

/**
 * Set security headers for all responses
 * Prevents XSS, clickjacking, and other common attacks
 */
function setSecurityHeaders() {
    // Prevent XSS attacks
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    
    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy (aligned with NGINX config)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://maps.googleapis.com https://maps.gstatic.com https://places.googleapis.com; " .
           "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
           "connect-src 'self' wss: https://maps.googleapis.com https://places.googleapis.com; " .
           "worker-src 'self'; " .
           "manifest-src 'self'; " .
           "media-src 'self' blob: mediastream:; " .
           "frame-ancestors 'self';";
    header("Content-Security-Policy: $csp");
}

/**
 * Generate CSRF token and store in session
 * @return string The generated token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper function to safely redirect with CSRF error for non-AJAX requests
 * Validates the referrer URL to prevent open redirect vulnerabilities
 * 
 * @param string $errorCode The error code to append to the redirect URL
 */
function handleCsrfErrorRedirect($errorCode) {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Validate the referrer is from the same host to prevent open redirect
    $isValidReferrer = false;
    if (!empty($referrer)) {
        $parsed = parse_url($referrer);
        if ($parsed !== false && isset($parsed['host'])) {
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            // Only allow redirects to the same host
            if (strcasecmp($parsed['host'], $currentHost) === 0) {
                $isValidReferrer = true;
            }
        }
    }
    
    if ($isValidReferrer && $parsed !== false) {
        // Build safe redirect URL using only the path and adding error parameter
        $path = $parsed['path'] ?? '/dashboard.php';
        $query = isset($parsed['query']) ? $parsed['query'] . '&' : '';
        $redirectUrl = $path . '?' . $query . 'error=' . urlencode($errorCode);
    } else {
        // Fallback to dashboard with error
        $redirectUrl = 'dashboard.php?error=' . urlencode($errorCode);
    }
    
    header("Location: $redirectUrl");
    exit();
}

/**
 * Check CSRF token and die if invalid
 * Used in process scripts where we want to exit on invalid token
 * Handles both AJAX and regular form submissions appropriately
 */
function checkCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Detect if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Check if token exists in POST
    if (!isset($_POST['csrf_token'])) {
        http_response_code(403);
        if ($isAjax) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'CSRF token missing. Please refresh and try again.'
            ]));
        } else {
            handleCsrfErrorRedirect('csrf_token_missing');
        }
    }
    
    // Validate token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        if ($isAjax) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'Invalid CSRF token. Please refresh and try again.'
            ]));
        } else {
            handleCsrfErrorRedirect('csrf_token_invalid');
        }
    }
}

/**
 * Get HTML input field with CSRF token
 * @return string HTML input field
 */
function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token value
 * @return string The current CSRF token
 */
function getCSRFToken() {
    return generateCSRFToken();
}

/**
 * Sanitize input to prevent XSS
 * @param string $input The input to sanitize
 * @return string The sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if user is authenticated
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Check if user has specific role
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has role, false otherwise
 */
function hasRole($roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isAuthenticated()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Require authentication or redirect
 * @param string $redirect_url URL to redirect to if not authenticated
 */
function requireAuth($redirect_url = 'login.php') {
    if (!isAuthenticated()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Require specific role or deny access
 * @param string|array $roles Required role(s)
 * @param bool $json Whether to return JSON response (true) or redirect (false)
 */
function requireRole($roles, $json = false) {
    if (!hasRole($roles)) {
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'Access denied. Insufficient permissions.'
            ]));
        } else {
            http_response_code(403);
            die('Access denied. Insufficient permissions.');
        }
    }
}

/**
 * Log security event
 * @param string $event_type Type of security event
 * @param string $description Description of the event
 * @param array $context Additional context
 */
function logSecurityEvent($event_type, $description, $context = []) {
    global $pdo;
    
    if (!isset($pdo) || !$pdo) {
        return; // Can't log if no database connection
    }
    
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $context_json = json_encode($context);
        
        $stmt = $pdo->prepare("
            INSERT INTO security_logs 
            (user_id, event_type, description, ip_address, user_agent, context, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $event_type,
            $description,
            $ip_address,
            $user_agent,
            $context_json
        ]);
    } catch (Exception $e) {
        // Silent fail - don't break application if logging fails
        error_log("Security logging failed: " . $e->getMessage());
    }
}

/**
 * Rate limiting check
 * @param string $key Rate limit key (e.g., 'login_attempt')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return bool True if within limits, false if exceeded
 */
function checkRateLimit($key, $max_attempts = 5, $time_window = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $rate_key = "rate_limit_{$key}";
    $now = time();
    
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = ['attempts' => 0, 'reset_time' => $now + $time_window];
    }
    
    $rate_data = $_SESSION[$rate_key];
    
    // Reset if time window has passed
    if ($now >= $rate_data['reset_time']) {
        $_SESSION[$rate_key] = ['attempts' => 0, 'reset_time' => $now + $time_window];
        $rate_data = $_SESSION[$rate_key];
    }
    
    // Check if limit exceeded
    if ($rate_data['attempts'] >= $max_attempts) {
        return false;
    }
    
    // Increment attempt counter
    $_SESSION[$rate_key]['attempts']++;
    
    return true;
}

/**
 * Clean and validate file upload
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array ['success' => bool, 'error' => string, 'filename' => string]
 */
function validateFileUpload($file, $allowed_types = [], $max_size = 10485760) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1048576;
        return ['success' => false, 'error' => "File too large. Maximum size: {$max_mb}MB"];
    }
    
    // Check MIME type if specified
    if (!empty($allowed_types)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_types)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
    }
    
    // Generate safe filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    return [
        'success' => true,
        'filename' => $safe_filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name']
    ];
}

/**
 * Check if user has a specific permission
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $user_role User role
 * @param string $permission Permission key to check
 * @return bool True if user has permission
 */
function hasPermission($pdo, $user_id, $user_role, $permission) {
    // Admin has all permissions
    if ($user_role === 'admin') {
        return true;
    }
    
    // Define default permissions based on roles
    $role_permissions = [
        'coach' => [
            'create_drills',
            'delete_drills',
            'create_practice_plans',
            'delete_practice_plans',
            'share_practice_plans',
            'import_from_ihs',
            'manage_athletes',
            'view_athlete_progress',
            'manage_drill_categories',
            'manage_sessions'
        ],
        'health_coach' => [
            'create_drills',
            'delete_drills',
            'create_practice_plans',
            'delete_practice_plans',
            'share_practice_plans',
            'import_from_ihs',
            'manage_athletes',
            'view_athlete_progress',
            'manage_drill_categories',
            'manage_sessions'
        ],
        'team_coach' => [
            'view_team_roster',
            'view_athlete_progress'
        ],
        'athlete' => [
            'view_own_progress',
            'view_drills'
        ],
        'parent' => [
            'view_child_progress'
        ]
    ];
    
    // Check role-based permissions first
    if (isset($role_permissions[$user_role]) && in_array($permission, $role_permissions[$user_role])) {
        return true;
    }
    
    // Check database for custom permissions (if tables exist)
    try {
        // Check user-specific permissions
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.permission_name = ? AND up.is_granted = 1
        ");
        $stmt->execute([$user_id, $permission]);
        if ($stmt->fetch()) {
            return true;
        }
        
        // Check role permissions
        $stmt = $pdo->prepare("
            SELECT 1 FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role = ? AND p.permission_name = ?
        ");
        $stmt->execute([$user_role, $permission]);
        if ($stmt->fetch()) {
            return true;
        }
    } catch (PDOException $e) {
        // If permission tables don't exist, fall back to role-based permissions
        error_log("Permission check database error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Require permission or deny access
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $user_role User role
 * @param string $permission Permission key required
 * @param bool $json Whether to return JSON response
 */
function requirePermission($pdo, $user_id, $user_role, $permission, $json = false) {
    if (!hasPermission($pdo, $user_id, $user_role, $permission)) {
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'Access denied. You do not have permission to perform this action.'
            ]));
        } else {
            header("Location: dashboard.php?error=permission_denied");
            exit();
        }
    }
}

/**
 * Check if the current IP is allowed to access POS systems.
 * Admins are always allowed. Non-admin users are restricted to
 * IP addresses listed in the pos_allowed_ips table.
 * If no IPs are configured in the table, all users are allowed (open access).
 * Returns true if the table doesn't exist yet (graceful degradation).
 *
 * @param PDO $pdo Database connection
 * @param string $user_role User role
 * @return bool True if access is allowed, false otherwise
 */
function checkPOSIPAccess($pdo, $user_role) {
    // Admins are always exempt from IP restrictions
    if ($user_role === 'admin') {
        return true;
    }

    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($client_ip)) {
        return false;
    }

    try {
        // First check if the client IP is explicitly allowed
        $stmt = $pdo->prepare("SELECT 1 FROM pos_allowed_ips WHERE ip_address = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$client_ip]);
        if ($stmt->fetch()) {
            return true;
        }

        // IP not found - check if any IPs are configured at all
        // If none configured, allow open access (table not yet set up)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pos_allowed_ips WHERE is_active = 1");
        $stmt->execute();
        return ((int) $stmt->fetchColumn() === 0);
    } catch (PDOException $e) {
        // If table doesn't exist yet, allow access (graceful degradation)
        error_log("POS IP check error: " . $e->getMessage());
        return true;
    }
}

/**
 * Encrypt a password/token using AES-256-CBC
 * Uses a persistent key file (.nextcloud_key) for key material.
 * This is the single canonical implementation — all files should
 * include security.php instead of defining their own copy.
 *
 * @param string $password Plaintext to encrypt
 * @return string Base64-encoded ciphertext (IV::encrypted)
 */
function encryptPassword($password) {
    $key_file = __DIR__ . '/.nextcloud_key';
    if (!file_exists($key_file)) {
        $key = bin2hex(random_bytes(32));
        file_put_contents($key_file, $key);
        chmod($key_file, 0600);
    } else {
        $key = file_get_contents($key_file);
    }
    
    $key_hash = hash('sha256', $key, true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key_hash, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt a password/token previously encrypted with encryptPassword()
 *
 * @param string $encrypted_data Base64-encoded ciphertext
 * @return string Decrypted plaintext, or empty string on failure
 */
function decryptPassword($encrypted_data) {
    $key_file = __DIR__ . '/.nextcloud_key';
    if (!file_exists($key_file)) {
        return '';
    }
    
    $key = file_get_contents($key_file);
    $key_hash = hash('sha256', $key, true);
    $decoded = base64_decode($encrypted_data, true);
    if ($decoded === false) {
        return '';
    }
    $parts = explode('::', $decoded, 2);
    if (count($parts) === 2) {
        $iv = $parts[0];
        $encrypted = $parts[1];
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key_hash, 0, $iv);
    }
    return '';
}

/**
 * Decrypt a credential value. Returns the decrypted value if the credential
 * is properly encrypted. If decryption fails (value may be plaintext/not yet
 * migrated), logs a warning and returns the original value to avoid breaking
 * functionality. Run ensureCredentialsEncrypted() during setup to migrate
 * any plaintext values.
 *
 * @param string $value The encrypted value from system_settings
 * @return string The decrypted value, or original value if decryption fails
 */
function decryptCredential($value) {
    if (empty($value)) {
        return '';
    }
    
    // Try decryption — if it succeeds, use the decrypted value
    if (function_exists('decryptPassword')) {
        $decrypted = decryptPassword($value);
        if (!empty($decrypted)) {
            return $decrypted;
        }
    }
    
    // Decryption failed — value may be plaintext (not yet migrated)
    // Log a warning but still return the raw value to avoid breaking functionality
    error_log("decryptCredential: Failed to decrypt a credential value. Run setup to encrypt all credentials.");
    return $value;
}

/**
 * List of system_settings keys that must be stored encrypted.
 * Used by ensureCredentialsEncrypted() during setup finalization.
 *
 * @return array List of setting_key values that should be encrypted
 */
function getEncryptedSettingKeys() {
    return [
        'smtp_pass',
        'nextcloud_password',
        'nextcloud_backup_password',
        'paperless_api_token',
        'stripe_publishable_key',
        'stripe_secret_key',
        'google_maps_api_key',
        'github_token',
        'docuseal_api_key',
        'docuseal_webhook_secret',
        'stallion_api_key',
        'stallion_api_secret',
    ];
}

/**
 * Check if a value appears to be encrypted (base64-encoded with :: separator).
 * Encrypted values from encryptPassword() have the format: base64(IV::ciphertext)
 *
 * @param string $value The value to check
 * @return bool True if the value looks encrypted
 */
function isValueEncrypted($value) {
    if (empty($value)) {
        return false;
    }
    
    // Try to base64-decode it
    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        return false;
    }
    
    // Encrypted format is IV::ciphertext after base64 decode
    $parts = explode('::', $decoded, 2);
    if (count($parts) !== 2) {
        return false;
    }
    
    // IV should be 16 bytes for AES-256-CBC
    if (strlen($parts[0]) !== 16) {
        return false;
    }
    
    return true;
}

/**
 * Scan system_settings for credentials that should be encrypted but are stored
 * as plaintext, and encrypt them in-place. Called during setup finalization
 * to ensure all sensitive data is properly encrypted.
 *
 * @param PDO $pdo Database connection
 * @return array Summary of what was encrypted ['migrated' => [...], 'already_encrypted' => [...], 'empty' => [...]]
 */
function ensureCredentialsEncrypted($pdo) {
    $keys = getEncryptedSettingKeys();
    $results = ['migrated' => [], 'already_encrypted' => [], 'empty' => []];
    
    // Fetch all sensitive settings in one query
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $update_stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    
    foreach ($keys as $key) {
        $value = $settings[$key] ?? null;
        
        if (empty($value)) {
            $results['empty'][] = $key;
            continue;
        }
        
        // Check if the value is already encrypted
        if (isValueEncrypted($value)) {
            // Verify it actually decrypts successfully using decryptPassword() directly
            // (not decryptCredential() which would log warnings during migration)
            $decrypted = decryptPassword($value);
            if (!empty($decrypted)) {
                $results['already_encrypted'][] = $key;
                continue;
            }
        }
        
        // Value is plaintext — encrypt it in-place
        $encrypted = encryptPassword($value);
        $update_stmt->execute([$encrypted, $key]);
        $results['migrated'][] = $key;
    }
    
    return $results;
}

/**
 * Check if a user field value appears to be encrypted with FieldEncryption.
 * FieldEncryption uses base64(IV + ciphertext) where IV is 16 bytes for AES-256-CBC.
 * Encrypted values are significantly longer than typical plaintext names/phones.
 *
 * @param string $value The value to check
 * @return bool True if the value appears to be encrypted
 */
function isFieldEncrypted($value) {
    if (empty($value)) {
        return false;
    }
    
    // Try to base64-decode
    $data = base64_decode($value, true);
    if ($data === false) {
        return false;
    }
    
    // AES-256-CBC IV is 16 bytes; encrypted data must be longer than just the IV
    $ivLen = 16;
    if (strlen($data) <= $ivLen) {
        return false;
    }
    
    // If we can successfully decrypt it, it's encrypted
    if (class_exists('FieldEncryption') && FieldEncryption::isConfigured()) {
        $decrypted = FieldEncryption::decrypt($value);
        // If decrypt returns a different value, the original was encrypted
        if ($decrypted !== $value) {
            return true;
        }
    }
    
    return false;
}

/**
 * Scan the users table for PII fields that should be encrypted but are stored
 * as plaintext, and encrypt them in-place. Called during setup finalization
 * to ensure all user data is properly encrypted.
 *
 * @param PDO $pdo Database connection
 * @return array Summary: ['migrated_users' => int, 'already_encrypted' => int, 'fields_checked' => array]
 */
function ensureUserDataEncrypted($pdo) {
    require_once __DIR__ . '/lib/encryption.php';
    
    $results = ['migrated_users' => 0, 'already_encrypted' => 0, 'fields_checked' => FieldEncryption::USER_PII_FIELDS];
    
    if (!FieldEncryption::isConfigured()) {
        error_log("ensureUserDataEncrypted: FieldEncryption not configured, skipping");
        return $results;
    }
    
    $fields = FieldEncryption::USER_PII_FIELDS;
    
    // Fetch all users with non-empty PII fields
    $stmt = $pdo->query("SELECT id, " . implode(', ', $fields) . " FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $update_parts = [];
    foreach ($fields as $field) {
        $update_parts[] = "$field = :$field";
    }
    $update_sql = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    
    foreach ($users as $user) {
        $needs_update = false;
        $params = ['id' => $user['id']];
        
        foreach ($fields as $field) {
            $value = $user[$field] ?? '';
            
            if (empty($value)) {
                $params[$field] = $value;
                continue;
            }
            
            // Check if already encrypted
            if (isFieldEncrypted($value)) {
                $params[$field] = $value; // Keep as-is
            } else {
                // Plaintext — encrypt it
                $params[$field] = FieldEncryption::encrypt($value);
                $needs_update = true;
            }
        }
        
        if ($needs_update) {
            $update_stmt->execute($params);
            $results['migrated_users']++;
        } else {
            $results['already_encrypted']++;
        }
    }
    
    return $results;
}
