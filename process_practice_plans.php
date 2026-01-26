<?php
/**
 * Process Practice Plan Operations
 * Handles CRUD operations for practice plans
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Security check - must be logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Set security headers
setSecurityHeaders();

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';

// =========================================================
// CREATE/UPDATE PRACTICE PLAN
// =========================================================
if ($action === 'save_plan' || $action === 'create' || $action === 'update') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    $plan_id = !empty($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $name = trim($_POST['title'] ?? $_POST['practice_title'] ?? $_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? $_POST['practice_goals'] ?? '');
    $drills = isset($_POST['drills']) ? json_decode($_POST['drills'], true) : [];
    
    if (empty($name)) {
        header("Location: dashboard.php?page=practice_plans&error=title_required");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($plan_id) {
            // Update existing plan - use columns from schema (name, description, version)
            $stmt = $pdo->prepare("
                UPDATE practice_plans SET 
                    name = ?, description = ?, version = version + 1,
                    updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $name, $description, $plan_id, $user_id
            ]);
            
            // Delete old drills
            $pdo->prepare("DELETE FROM practice_plan_drills WHERE practice_plan_id = ?")->execute([$plan_id]);
        } else {
            // Insert new plan - use columns from schema (name, description, created_by)
            $stmt = $pdo->prepare("
                INSERT INTO practice_plans (name, description, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $user_id]);
            $plan_id = $pdo->lastInsertId();
        }
        
        // Insert drills
        if (!empty($drills) && is_array($drills)) {
            $drill_stmt = $pdo->prepare("
                INSERT INTO practice_plan_drills (practice_plan_id, drill_id, drill_order, duration_minutes, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($drills as $index => $drill) {
                $drill_id = is_numeric($drill['id'] ?? $drill['drill_id'] ?? 0) ? intval($drill['id'] ?? $drill['drill_id']) : null;
                if ($drill_id) {
                    $drill_stmt->execute([
                        $plan_id,
                        $drill_id,
                        $index,
                        $drill['duration'] ?? null,
                        $drill['notes'] ?? null
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        // Redirect based on action - create goes back to practice_create, update/save_plan goes to practice_plans
        if ($action === 'create') {
            header("Location: dashboard.php?page=practice_create&status=plan_created&plan_id=$plan_id");
        } else {
            header("Location: dashboard.php?page=practice_plans&status=plan_saved");
        }
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: dashboard.php?page=practice_plans&error=save_failed");
        exit();
    }
}

// =========================================================
// DELETE PRACTICE PLAN
// =========================================================
if ($action === 'delete_plan' || $action === 'delete') {
    requirePermission($pdo, $user_id, $user_role, 'delete_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    
    try {
        $pdo->prepare("DELETE FROM practice_plans WHERE id = ? AND created_by = ?")->execute([$plan_id, $user_id]);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Practice plan deleted successfully']);
            exit();
        }
        
        header("Location: dashboard.php?page=practice_library&status=plan_deleted");
        exit();
    } catch (PDOException $e) {
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete practice plan']);
            exit();
        }
        
        header("Location: dashboard.php?page=practice_library&error=delete_failed");
        exit();
    }
}

// =========================================================
// GENERATE/REGENERATE SHARE TOKEN
// =========================================================
if ($action === 'generate_share_token') {
    requirePermission($pdo, $user_id, $user_role, 'share_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    $share_token = generateShareToken();
    
    try {
        $stmt = $pdo->prepare("UPDATE practice_plans SET share_token = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$share_token, $plan_id, $user_id]);
        header("Location: dashboard.php?page=practice_plans&status=token_generated&plan_id=$plan_id");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=practice_plans&error=token_failed");
        exit();
    }
}

// =========================================================
// REMOVE SHARE TOKEN
// =========================================================
if ($action === 'remove_share_token') {
    requirePermission($pdo, $user_id, $user_role, 'share_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE practice_plans SET share_token = NULL WHERE id = ? AND created_by = ?");
        $stmt->execute([$plan_id, $user_id]);
        header("Location: dashboard.php?page=practice_plans&status=token_removed");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=practice_plans&error=token_failed");
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=practice_plans");
exit();
