<?php
/**
 * Process Switch Athlete - Handles parent "View As" athlete switching
 * Sets the session variable so the parent can view their child's data.
 */

require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$athlete_id = isset($_POST['athlete_id']) ? intval($_POST['athlete_id']) : 0;

// If athlete_id is 0 or matches the parent's own ID, reset to parent's own view
if ($athlete_id === 0 || $athlete_id === $user_id) {
    $_SESSION['viewing_athlete_id'] = null;
    unset($_SESSION['viewing_athlete_id']);
    echo json_encode(['success' => true, 'message' => 'Switched to own view']);
    exit;
}

// Verify the parent has a relationship with this athlete
try {
    // Check parent_athlete_relationships first
    $stmt = $pdo->prepare("
        SELECT 1 FROM parent_athlete_relationships 
        WHERE parent_id = ? AND athlete_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $athlete_id]);
    $has_access = $stmt->rowCount() > 0;

    if (!$has_access) {
        // Also check managed_athletes as fallback
        $stmt2 = $pdo->prepare("
            SELECT 1 FROM managed_athletes 
            WHERE parent_id = ? AND athlete_id = ?
            LIMIT 1
        ");
        $stmt2->execute([$user_id, $athlete_id]);
        $has_access = $stmt2->rowCount() > 0;
    }
} catch (PDOException $e) {
    error_log("Switch athlete permission check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

if (!$has_access) {
    // If user is admin, allow switching to any athlete
    $user_role = $_SESSION['user_role'] ?? '';
    $user_roles_list = [$user_role];
    try {
        $rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
        $rolesStmt->execute([$user_id]);
        $extraRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($extraRoles) {
            $user_roles_list = array_unique(array_merge($user_roles_list, $extraRoles));
        }
    } catch (PDOException $e) {
        // user_roles table may not exist yet
    }

    if (!in_array('admin', $user_roles_list) && !in_array('coach', $user_roles_list)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to view this athlete']);
        exit;
    }
}

// Verify the athlete exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$athlete_id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Athlete not found']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Switch athlete user check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Set the session variable
$_SESSION['viewing_athlete_id'] = $athlete_id;

echo json_encode(['success' => true, 'message' => 'Switched to athlete view', 'athlete_id' => $athlete_id]);
