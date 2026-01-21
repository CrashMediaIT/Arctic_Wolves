<?php
/**
 * Process Manage Athletes Actions
 * Handles adding, creating, and removing athletes from parent management
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is a parent
if ($_SESSION['user_role'] !== 'parent') {
    header("Location: dashboard.php?error=permission_denied");
    exit();
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_athlete':
            // Link existing athlete account to parent
            $athlete_email = trim($_POST['athlete_email'] ?? '');
            $relationship = trim($_POST['relationship'] ?? 'Parent');
            
            if (empty($athlete_email)) {
                header("Location: dashboard.php?page=manage_athletes&error=invalid_data");
                exit();
            }
            
            // Check if athlete exists
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ? AND role = 'athlete'");
            $stmt->execute([$athlete_email]);
            $athlete = $stmt->fetch();
            
            if (!$athlete) {
                logSecurityEvent($pdo, 'athlete_link_failed', "Parent $user_id attempted to link non-existent athlete: $athlete_email", $user_id);
                header("Location: dashboard.php?page=manage_athletes&error=athlete_not_found");
                exit();
            }
            
            // Check if already managed
            $check_stmt = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
            $check_stmt->execute([$user_id, $athlete['id']]);
            
            if ($check_stmt->fetch()) {
                header("Location: dashboard.php?page=manage_athletes&error=already_managed");
                exit();
            }
            
            // Add to managed athletes
            $insert_stmt = $pdo->prepare("
                INSERT INTO managed_athletes (parent_id, athlete_id, relationship, can_book, can_view_stats)
                VALUES (?, ?, ?, 1, 1)
            ");
            $insert_stmt->execute([$user_id, $athlete['id'], $relationship]);
            
            // Log security event
            logSecurityEvent($pdo, 'athlete_linked', "Parent $user_id linked athlete {$athlete['id']}", $user_id);
            
            // Notify athlete
            $parent_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $parent_stmt->execute([$user_id]);
            $parent = $parent_stmt->fetch();
            
            if ($parent) {
                createNotification(
                    $pdo,
                    $athlete['id'],
                    'account',
                    'Parent Account Linked',
                    $parent['first_name'] . ' ' . $parent['last_name'] . ' has linked your account as a managed athlete',
                    'dashboard.php?page=profile'
                );
            }
            
            header("Location: dashboard.php?page=manage_athletes&success=athlete_added");
            exit();
            
        case 'create_athlete':
            // Create new athlete account and link to parent
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $birth_date = $_POST['birth_date'] ?? null;
            $position = trim($_POST['position'] ?? '');
            $relationship = trim($_POST['relationship'] ?? 'Parent');
            
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email)) {
                header("Location: dashboard.php?page=manage_athletes&error=invalid_data");
                exit();
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header("Location: dashboard.php?page=manage_athletes&error=invalid_data");
                exit();
            }
            
            // Check if email already exists
            $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->execute([$email]);
            
            if ($check_email->fetch()) {
                header("Location: dashboard.php?page=manage_athletes&error=email_exists");
                exit();
            }
            
            // Generate random password
            $random_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
            
            // Create athlete account
            $create_stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change)
                VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1)
            ");
            $create_stmt->execute([
                $first_name,
                $last_name,
                $email,
                $hashed_password,
                $position ?: null,
                $birth_date ?: null
            ]);
            
            $athlete_id = $pdo->lastInsertId();
            
            // Link to parent
            $link_stmt = $pdo->prepare("
                INSERT INTO managed_athletes (parent_id, athlete_id, relationship, can_book, can_view_stats)
                VALUES (?, ?, ?, 1, 1)
            ");
            $link_stmt->execute([$user_id, $athlete_id, $relationship]);
            
            // Log security event
            logSecurityEvent($pdo, 'athlete_created', "Parent $user_id created athlete account $athlete_id", $user_id);
            
            // Send welcome email with credentials
            require_once __DIR__ . '/mailer.php';
            $parent_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $parent_stmt->execute([$user_id]);
            $parent = $parent_stmt->fetch();
            
            sendEmail($email, 'welcome', [
                'name' => $first_name,
                'email' => $email,
                'password' => $random_password,
                'parent_name' => $parent ? $parent['first_name'] . ' ' . $parent['last_name'] : 'Your parent'
            ]);
            
            // Create notification for new athlete
            createNotification(
                $pdo,
                $athlete_id,
                'account',
                'Welcome to Crash Hockey',
                'Your account has been created. Please check your email for login credentials.',
                'dashboard.php?page=profile',
                false // Don't send email notification since we already sent welcome email
            );
            
            header("Location: dashboard.php?page=manage_athletes&success=athlete_created");
            exit();
            
        case 'remove_athlete':
            // Remove athlete from parent's managed list
            $managed_id = intval($_POST['managed_id'] ?? 0);
            
            if ($managed_id == 0) {
                header("Location: dashboard.php?page=manage_athletes&error=invalid_data");
                exit();
            }
            
            // Verify ownership
            $verify_stmt = $pdo->prepare("SELECT athlete_id FROM managed_athletes WHERE id = ? AND parent_id = ?");
            $verify_stmt->execute([$managed_id, $user_id]);
            $managed = $verify_stmt->fetch();
            
            if (!$managed) {
                logSecurityEvent($pdo, 'athlete_removal_denied', "Parent $user_id attempted to remove managed athlete $managed_id without permission", $user_id);
                header("Location: dashboard.php?page=manage_athletes&error=permission_denied");
                exit();
            }
            
            // Remove from managed athletes
            $delete_stmt = $pdo->prepare("DELETE FROM managed_athletes WHERE id = ? AND parent_id = ?");
            $delete_stmt->execute([$managed_id, $user_id]);
            
            // Log security event
            logSecurityEvent($pdo, 'athlete_removed', "Parent $user_id removed athlete {$managed['athlete_id']} from managed list", $user_id);
            
            header("Location: dashboard.php?page=manage_athletes&success=athlete_removed");
            exit();
            
        default:
            header("Location: dashboard.php?page=manage_athletes&error=invalid_action");
            exit();
    }
    
} catch (PDOException $e) {
    error_log("Database error in process_manage_athletes.php: " . $e->getMessage());
    header("Location: dashboard.php?page=manage_athletes&error=database_error");
    exit();
} catch (Exception $e) {
    error_log("Error in process_manage_athletes.php: " . $e->getMessage());
    header("Location: dashboard.php?page=manage_athletes&error=unknown_error");
    exit();
}
