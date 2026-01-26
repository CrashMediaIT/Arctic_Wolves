<?php
// Production Login Handler
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/error_logger.php';

// Check database connection
if (!$db_connected || $pdo === null) {
    ErrorLogger::error("Database connection failed during login", ['error' => $db_error ?? 'Unknown']);
    $_SESSION['login_error'] = "Database connection error. Please contact support.";
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
            // Check if account is verified/enabled
            if (isset($user['is_verified']) && $user['is_verified'] === 0) {
                $_SESSION['login_error'] = "Your account has been disabled. Please contact an administrator for assistance.";
                ErrorLogger::security("Login attempt for disabled account", ['email' => $email]);
                header("Location: login.php");
                exit();
            }

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Successful login
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
