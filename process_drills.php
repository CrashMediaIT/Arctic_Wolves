<?php
/**
 * Process Drill Operations
 * Handles CRUD operations for drills, categories, and drill management
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
// CREATE/UPDATE DRILL
// =========================================================
if ($action === 'save_drill') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
    $drill_id = !empty($_POST['drill_id']) ? intval($_POST['drill_id']) : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $diagram_data = trim($_POST['diagram_data'] ?? '');
    $duration = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
    $skill_level = $_POST['skill_level'] ?? 'all';
    $age_group = trim($_POST['age_group'] ?? '');
    $equipment = trim($_POST['equipment_needed'] ?? '');
    $coaching_points = trim($_POST['coaching_points'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    if (empty($title)) {
        header("Location: dashboard.php?page=drills&error=title_required");
        exit();
    }
    
    try {
        if ($drill_id) {
            // Update existing drill
            $stmt = $pdo->prepare("
                UPDATE drills SET 
                    title = ?, description = ?, category_id = ?, diagram_data = ?,
                    duration_minutes = ?, skill_level = ?, age_group = ?,
                    equipment_needed = ?, coaching_points = ?, video_url = ?,
                    updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $title, $description, $category_id, $diagram_data, $duration,
                $skill_level, $age_group, $equipment, $coaching_points, $video_url,
                $drill_id, $user_id
            ]);
            
            // Delete old tags
            $pdo->prepare("DELETE FROM drill_tags WHERE drill_id = ?")->execute([$drill_id]);
        } else {
            // Insert new drill
            $stmt = $pdo->prepare("
                INSERT INTO drills (
                    title, description, category_id, diagram_data, duration_minutes,
                    skill_level, age_group, equipment_needed, coaching_points, video_url,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $category_id, $diagram_data, $duration,
                $skill_level, $age_group, $equipment, $coaching_points, $video_url,
                $user_id
            ]);
            $drill_id = $pdo->lastInsertId();
        }
        
        // Insert tags
        if (!empty($tags) && is_array($tags)) {
            $tag_stmt = $pdo->prepare("INSERT INTO drill_tags (drill_id, tag) VALUES (?, ?)");
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tag_stmt->execute([$drill_id, $tag]);
                }
            }
        }
        
        header("Location: dashboard.php?page=drills&status=drill_saved");
        exit();
        
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=save_failed");
        exit();
    }
}

// =========================================================
// DELETE DRILL
// =========================================================
if ($action === 'delete_drill') {
    requirePermission($pdo, $user_id, $user_role, 'delete_drills');
    
    $drill_id = intval($_POST['drill_id']);
    
    try {
        $pdo->prepare("DELETE FROM drills WHERE id = ? AND created_by = ?")->execute([$drill_id, $user_id]);
        header("Location: dashboard.php?page=drills&status=drill_deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=delete_failed");
        exit();
    }
}

// =========================================================
// CREATE CATEGORY (Admin Only)
// =========================================================
if ($action === 'create_category') {
    requirePermission($pdo, $user_id, $user_role, 'manage_drill_categories');
    
    $name = trim($_POST['category_name']);
    $description = trim($_POST['category_description'] ?? '');
    
    if (empty($name)) {
        header("Location: dashboard.php?page=drills&error=category_name_required");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO drill_categories (name, description, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $user_id]);
        header("Location: dashboard.php?page=drills&status=category_created");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header("Location: dashboard.php?page=drills&error=category_exists");
        } else {
            header("Location: dashboard.php?page=drills&error=category_failed");
        }
        exit();
    }
}

// =========================================================
// DELETE CATEGORY (Admin Only)
// =========================================================
if ($action === 'delete_category') {
    requirePermission($pdo, $user_id, $user_role, 'manage_drill_categories');
    
    $category_id = intval($_POST['category_id']);
    
    try {
        $pdo->prepare("DELETE FROM drill_categories WHERE id = ?")->execute([$category_id]);
        header("Location: dashboard.php?page=drills&status=category_deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=category_delete_failed");
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=drills");
exit();
