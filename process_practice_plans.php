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
if ($action === 'save_plan') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    $plan_id = !empty($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $total_duration = !empty($_POST['total_duration']) ? intval($_POST['total_duration']) : 60;
    $age_group = trim($_POST['age_group'] ?? '');
    $focus_area = trim($_POST['focus_area'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $drills = isset($_POST['drills']) ? json_decode($_POST['drills'], true) : [];
    
    if (empty($title)) {
        header("Location: dashboard.php?page=practice_plans&error=title_required");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($plan_id) {
            // Update existing plan
            $stmt = $pdo->prepare("
                UPDATE practice_plans SET 
                    title = ?, description = ?, total_duration = ?,
                    age_group = ?, focus_area = ?, is_public = ?,
                    updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $title, $description, $total_duration, $age_group, 
                $focus_area, $is_public, $plan_id, $user_id
            ]);
            
            // Delete old drills
            $pdo->prepare("DELETE FROM practice_plan_drills WHERE plan_id = ?")->execute([$plan_id]);
        } else {
            // Insert new plan
            $share_token = null;
            if (hasPermission($pdo, $user_id, $user_role, 'share_practice_plans')) {
                $share_token = generateShareToken();
            }
            
            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO practice_plans (
                    title, description, total_duration, age_group, focus_area,
                    category_id, is_public, share_token, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $total_duration, $age_group,
                $focus_area, $category_id, $is_public, $share_token, $user_id
            ]);
            $plan_id = $pdo->lastInsertId();
        }
        
        // Insert drills
        if (!empty($drills) && is_array($drills)) {
            $drill_stmt = $pdo->prepare("
                INSERT INTO practice_plan_drills (plan_id, drill_id, order_index, duration_minutes, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($drills as $index => $drill) {
                $drill_stmt->execute([
                    $plan_id,
                    $drill['drill_id'],
                    $index,
                    $drill['duration'] ?? null,
                    $drill['notes'] ?? null
                ]);
            }
        }
        
        $pdo->commit();
        header("Location: dashboard.php?page=practice_plans&status=plan_saved");
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
if ($action === 'delete_plan') {
    requirePermission($pdo, $user_id, $user_role, 'delete_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    
    try {
        $pdo->prepare("DELETE FROM practice_plans WHERE id = ? AND created_by = ?")->execute([$plan_id, $user_id]);
        header("Location: dashboard.php?page=practice_plans&status=plan_deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=practice_plans&error=delete_failed");
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
