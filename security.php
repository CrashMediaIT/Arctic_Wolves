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
           "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
           "connect-src 'self'; " .
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
 * Check CSRF token and die if invalid
 * Used in process scripts where we want to exit on invalid token
 */
function checkCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if token exists in POST
    if (!isset($_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'error' => 'CSRF token missing. Please refresh and try again.'
        ]));
    }
    
    // Validate token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'error' => 'Invalid CSRF token. Please refresh and try again.'
        ]));
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
