<?php
/**
 * Process Permissions Operations
 * Handles permission management for roles and users
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Admin only
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Set security headers
setSecurityHeaders();

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';

// =========================================================
// UPDATE ROLE PERMISSIONS
// =========================================================
if ($action === 'update_role_permissions') {
    $perms = $_POST['perms'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Get all permissions
        $all_perms = $pdo->query("SELECT id, permission_key FROM permissions")->fetchAll();
        
        // Process each role
        $roles = ['athlete', 'coach', 'coach_plus']; // Don't update admin
        
        foreach ($roles as $role) {
            foreach ($all_perms as $perm) {
                $is_granted = isset($perms[$role][$perm['permission_key']]) ? 1 : 0;
                
                // Check if permission exists for this role
                $stmt = $pdo->prepare("
                    SELECT id FROM role_permissions 
                    WHERE role = ? AND permission_id = ?
                ");
                $stmt->execute([$role, $perm['id']]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    // Update existing
                    $pdo->prepare("
                        UPDATE role_permissions 
                        SET granted = ? 
                        WHERE role = ? AND permission_id = ?
                    ")->execute([$is_granted, $role, $perm['id']]);
                } else {
                    // Insert new
                    $pdo->prepare("
                        INSERT INTO role_permissions (role, permission_id, granted) 
                        VALUES (?, ?, ?)
                    ")->execute([$role, $perm['id'], $is_granted]);
                }
            }
        }
        
        $pdo->commit();
        
        // Log the change
        logSecurityEvent($pdo, 'permissions_updated', 'Role permissions updated by admin', $user_id);
        
        header("Location: dashboard.php?page=admin_permissions&status=permissions_updated");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: dashboard.php?page=admin_permissions&error=update_failed");
        exit();
    }
}

// =========================================================
// UPDATE USER PERMISSIONS
// =========================================================
if ($action === 'update_user_permissions') {
    $target_user_id = intval($_POST['user_id']);
    $perms = $_POST['perms'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Delete existing user permissions
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$target_user_id]);
        
        // Insert new permissions
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, permission_id, granted, granted_by)
            SELECT ?, p.id, ?, ?
            FROM permissions p
            WHERE p.permission_key = ?
        ");
        
        foreach ($perms as $perm_key => $value) {
            $is_granted = $value === '1' ? 1 : 0;
            $stmt->execute([$target_user_id, $is_granted, $user_id, $perm_key]);
        }
        
        $pdo->commit();
        
        // Log the change
        logSecurityEvent($pdo, 'user_permissions_updated', "User permissions updated for user ID $target_user_id", $user_id);
        
        header("Location: dashboard.php?page=user_permissions&user_id=$target_user_id&status=updated");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: dashboard.php?page=user_permissions&user_id=$target_user_id&error=update_failed");
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=admin_permissions");
exit();
