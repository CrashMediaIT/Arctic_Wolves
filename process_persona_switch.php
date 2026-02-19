<?php
/**
 * Admin Persona Mode - Role Switching Handler
 * Allows admins to switch between roles for testing purposes
 * Only accessible by users whose actual role is admin
 */

session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/lib/auditor.php';

header('Content-Type: application/json');

// Must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check that user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// The actual role must be admin (either stored original or current)
$actual_role = $_SESSION['persona_original_role'] ?? $_SESSION['user_role'] ?? '';
if ($actual_role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$action = $_POST['action'] ?? '';
$valid_roles = ['admin', 'coach', 'athlete', 'parent', 'health_coach', 'team_coach', 'front_desk_staff'];

if ($action === 'switch_role') {
    $target_role = $_POST['role'] ?? '';
    
    if (!in_array($target_role, $valid_roles)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }
    
    // Store the original admin role if not already stored
    if (!isset($_SESSION['persona_original_role'])) {
        $_SESSION['persona_original_role'] = $_SESSION['user_role'];
        $_SESSION['persona_original_name'] = $_SESSION['user_name'] ?? '';
    }
    
    // Switch the session role
    $_SESSION['user_role'] = $target_role;
    $_SESSION['persona_active'] = ($target_role !== 'admin');
    
    // Log the persona switch
    ErrorLogger::security("Admin persona switch", [
        'user_id' => $_SESSION['user_id'],
        'from_role' => $_SESSION['persona_original_role'],
        'to_role' => $target_role
    ]);
    
    if (isset($pdo) && $pdo) {
        Auditor::log($pdo, $_SESSION['user_id'], 'update', 'users', $_SESSION['user_id'], ['action' => 'Persona switch to ' . $target_role]);
        logSecurityEvent('persona_switch', 'Admin switched persona to ' . $target_role, [
            'target_role' => $target_role
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $target_role === 'admin' ? 'Switched back to Admin' : 'Switched to ' . ucfirst(str_replace('_', ' ', $target_role)) . ' view',
        'role' => $target_role,
        'persona_active' => $target_role !== 'admin'
    ]);
    exit;
}

if ($action === 'exit_persona') {
    // Restore original admin role
    if (isset($_SESSION['persona_original_role'])) {
        $_SESSION['user_role'] = $_SESSION['persona_original_role'];
        unset($_SESSION['persona_original_role']);
        unset($_SESSION['persona_original_name']);
    }
    $_SESSION['persona_active'] = false;
    
    ErrorLogger::security("Admin exited persona mode", [
        'user_id' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Returned to Admin role',
        'role' => 'admin',
        'persona_active' => false
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
