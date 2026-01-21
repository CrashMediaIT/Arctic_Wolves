<?php
// process_admin_age_skill.php - Process age group and skill level management
session_start();
require_once 'db_config.php';
require_once 'security.php';

// Set security headers
setSecurityHeaders();

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Check permission
requirePermission($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 'manage_sessions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php?page=admin_age_skill");
    exit();
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php?page=admin_age_skill&error=invalid_token");
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create_age_group':
            $name = trim($_POST['name']);
            $min_age = !empty($_POST['min_age']) ? intval($_POST['min_age']) : null;
            $max_age = !empty($_POST['max_age']) ? intval($_POST['max_age']) : null;
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $stmt = $pdo->prepare("INSERT INTO age_groups (name, min_age, max_age, description, display_order) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $min_age, $max_age, $description, $display_order]);
            
            logSecurityEvent($pdo, 'age_group_created', "Created age group: $name", $_SESSION['user_id']);
            
            header("Location: dashboard.php?page=admin_age_skill&success=age_group_created");
            break;
            
        case 'delete_age_group':
            $id = intval($_POST['id']);
            
            // Get name for logging
            $stmt = $pdo->prepare("SELECT name FROM age_groups WHERE id = ?");
            $stmt->execute([$id]);
            $ag = $stmt->fetch();
            
            if ($ag) {
                $pdo->prepare("DELETE FROM age_groups WHERE id = ?")->execute([$id]);
                logSecurityEvent($pdo, 'age_group_deleted', "Deleted age group: {$ag['name']}", $_SESSION['user_id']);
            }
            
            header("Location: dashboard.php?page=admin_age_skill&success=age_group_deleted");
            break;
            
        case 'create_skill_level':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $stmt = $pdo->prepare("INSERT INTO skill_levels (name, description, display_order) 
                                   VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $display_order]);
            
            logSecurityEvent($pdo, 'skill_level_created', "Created skill level: $name", $_SESSION['user_id']);
            
            header("Location: dashboard.php?page=admin_age_skill&success=skill_level_created");
            break;
            
        case 'delete_skill_level':
            $id = intval($_POST['id']);
            
            // Get name for logging
            $stmt = $pdo->prepare("SELECT name FROM skill_levels WHERE id = ?");
            $stmt->execute([$id]);
            $sl = $stmt->fetch();
            
            if ($sl) {
                $pdo->prepare("DELETE FROM skill_levels WHERE id = ?")->execute([$id]);
                logSecurityEvent($pdo, 'skill_level_deleted', "Deleted skill level: {$sl['name']}", $_SESSION['user_id']);
            }
            
            header("Location: dashboard.php?page=admin_age_skill&success=skill_level_deleted");
            break;
            
        case 'update_tax_settings':
            $tax_rate = floatval($_POST['tax_rate']);
            $tax_name = trim($_POST['tax_name']);
            
            // Update or insert tax rate
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('tax_rate', ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$tax_rate, $tax_rate]);
            
            // Update or insert tax name
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('tax_name', ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$tax_name, $tax_name]);
            
            logSecurityEvent($pdo, 'tax_settings_updated', "Updated tax settings: $tax_name = $tax_rate%", $_SESSION['user_id']);
            
            header("Location: dashboard.php?page=admin_settings&success=tax_updated");
            break;
            
        default:
            header("Location: dashboard.php?page=admin_age_skill&error=invalid_action");
            break;
    }
    
} catch (PDOException $e) {
    logSecurityEvent($pdo, 'age_skill_error', "Error in age/skill management: " . $e->getMessage(), $_SESSION['user_id']);
    header("Location: dashboard.php?page=admin_age_skill&error=" . urlencode($e->getMessage()));
}

exit();
?>
