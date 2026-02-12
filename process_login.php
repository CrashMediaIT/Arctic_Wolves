<?php
// Production Login Handler
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';

/**
 * Record login attempt in login_history table
 */
function recordLoginHistory($pdo, $user_id, $status, $failure_reason = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_history (user_id, login_time, ip_address, user_agent, login_status, failure_reason, last_activity)
            VALUES (?, NOW(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $status,
            $failure_reason,
            $status === 'success' ? date('Y-m-d H:i:s') : null
        ]);
    } catch (PDOException $e) {
        error_log("Failed to record login history: " . $e->getMessage());
    }
}

// Check database connection
if (!$db_connected || $pdo === null) {
    ErrorLogger::error("Database connection failed during login", ['error' => $db_error ?? 'Unknown']);
    $_SESSION['login_error'] = "Database connection error. Please contact support.";
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token for POST requests
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['login_error'] = "Invalid request. Please refresh and try again.";
        header("Location: login.php");
        exit();
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both email and password.";
        header("Location: login.php");
        exit();
    }

    try {
        // Query user
        $sql = "SELECT id, first_name, last_name, password, role, is_verified, force_pass_change FROM users WHERE email = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Decrypt PII fields
            $user['first_name'] = FieldEncryption::decrypt($user['first_name']);
            $user['last_name'] = FieldEncryption::decrypt($user['last_name']);
            // Check if account is verified/enabled
            if (isset($user['is_verified']) && $user['is_verified'] === 0) {
                $_SESSION['login_error'] = "Your account has been disabled. Please contact an administrator for assistance.";
                ErrorLogger::security("Login attempt for disabled account", ['email' => $email]);
                recordLoginHistory($pdo, $user['id'], 'blocked', 'Account disabled');
                header("Location: login.php");
                exit();
            }

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if 2FA is enabled for this user
                $has2FA = false;
                try {
                    $stmt2fa = $pdo->prepare("SELECT is_enabled FROM two_factor_auth WHERE user_id = ? AND is_enabled = 1");
                    $stmt2fa->execute([$user['id']]);
                    $has2FA = (bool)$stmt2fa->fetchColumn();
                } catch (PDOException $e) {
                    // Table may not exist yet, continue without 2FA
                }
                
                if ($has2FA) {
                    // Store pending 2FA state - don't complete login yet
                    session_regenerate_id(true);
                    $_SESSION['2fa_pending'] = true;
                    $_SESSION['2fa_pending_user_id'] = $user['id'];
                    $_SESSION['2fa_pending_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['2fa_pending_user_role'] = $user['role'];
                    $_SESSION['2fa_pending_user_email'] = $email;
                    
                    // Temporarily set user_id for CSRF to work on verify page
                    $_SESSION['user_id'] = $user['id'];
                    
                    recordLoginHistory($pdo, $user['id'], 'success');
                    header("Location: verify_2fa.php");
                    exit();
                }
                
                // Successful login (no 2FA)
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $email;
                $_SESSION['logged_in'] = true;

                // Log successful login
                ErrorLogger::security("Successful login", [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'role' => $user['role']
                ]);
                recordLoginHistory($pdo, $user['id'], 'success');

                // Check if password change is required (for coach-created accounts)
                if (isset($user['force_pass_change']) && $user['force_pass_change'] === 1) {
                    header("Location: force_change_password.php");
                    exit();
                }

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Invalid password
                $_SESSION['login_error'] = "Invalid email or password.";
                ErrorLogger::security("Failed login attempt - invalid password", ['email' => $email]);
                recordLoginHistory($pdo, $user['id'], 'failed', 'Invalid password');
                header("Location: login.php");
                exit();
            }
        } else {
            // User not found
            $_SESSION['login_error'] = "Invalid email or password.";
            ErrorLogger::security("Failed login attempt - user not found", ['email' => $email]);
            header("Location: login.php");
            exit();
        }

    } catch (PDOException $e) {
        ErrorLogger::error("Database error during login", [
            'error' => $e->getMessage(),
            'email' => $email
        ]);
        $_SESSION['login_error'] = "An error occurred. Please try again later.";
        header("Location: login.php");
        exit();
    }
} else {
    // Not a POST request
    header("Location: login.php");
    exit();
}
?>
