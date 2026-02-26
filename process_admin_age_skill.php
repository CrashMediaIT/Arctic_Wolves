<?php
// process_admin_age_skill.php - Process age group and skill level management
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Helper function to respond with JSON or redirect
function respond($success, $message, $redirectPage = 'admin_age_skill', $successCode = '', $additionalParams = '') {
    global $isAjax;
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit();
    } else {
        $url = "dashboard.php?page=" . urlencode($redirectPage);
        if ($additionalParams) {
            $url .= "&" . $additionalParams;
        }
        if ($success && $successCode) {
            $url .= "&success=" . urlencode($successCode);
        } else if (!$success) {
            $url .= "&error=" . urlencode($message);
        }
        header("Location: " . $url);
        exit();
    }
}

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    }
    header("Location: login.php");
    exit();
}

// Check permission
requirePermission($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 'manage_sessions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method');
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    respond(false, 'Invalid security token');
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Determine redirect target - if a redirect_page was specified, use it
$age_group_redirect = 'admin_age_skill';
$age_group_redirect_params = '';
if (!empty($_POST['redirect_page']) && $_POST['redirect_page'] === 'categories') {
    $age_group_redirect = 'categories';
    $age_group_redirect_params = 'tab=age_groups';
}

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
            Auditor::log($pdo, $user_id, 'create', 'age_groups', $pdo->lastInsertId(), ['action' => "Created age group: $name"]);
            
            logSecurityEvent('age_group_created', "Created age group: $name", $_SESSION['user_id']);
            
            respond(true, "Age group '$name' created successfully!", $age_group_redirect, 'age_group_created', $age_group_redirect_params);
            break;

        case 'update_age_group':
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $min_age = !empty($_POST['min_age']) ? intval($_POST['min_age']) : null;
            $max_age = !empty($_POST['max_age']) ? intval($_POST['max_age']) : null;
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE age_groups SET name = ?, min_age = ?, max_age = ?, description = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $min_age, $max_age, $description, $display_order, $id]);
            Auditor::log($pdo, $user_id, 'update', 'age_groups', $id, ['action' => "Updated age group: $name"]);
            
            logSecurityEvent('age_group_updated', "Updated age group: $name", $_SESSION['user_id']);
            
            respond(true, "Age group '$name' updated successfully!", $age_group_redirect, 'age_group_updated', $age_group_redirect_params);
            break;
            
        case 'delete_age_group':
            $id = intval($_POST['id']);
            
            // Get name for logging
            $stmt = $pdo->prepare("SELECT name FROM age_groups WHERE id = ?");
            $stmt->execute([$id]);
            $ag = $stmt->fetch();
            
            if ($ag) {
                $pdo->prepare("DELETE FROM age_groups WHERE id = ?")->execute([$id]);
                Auditor::log($pdo, $user_id, 'delete', 'age_groups', $id, ['action' => "Deleted age group: {$ag['name']}"]);
                logSecurityEvent('age_group_deleted', "Deleted age group: {$ag['name']}", $_SESSION['user_id']);
            }
            
            respond(true, 'Age group deleted successfully!', $age_group_redirect, 'age_group_deleted', $age_group_redirect_params);
            break;
            
        case 'create_skill_level':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $stmt = $pdo->prepare("INSERT INTO skill_levels (name, description, display_order) 
                                   VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $display_order]);
            Auditor::log($pdo, $user_id, 'create', 'skill_levels', $pdo->lastInsertId(), ['action' => "Created skill level: $name"]);
            
            logSecurityEvent('skill_level_created', "Created skill level: $name", $_SESSION['user_id']);
            
            respond(true, "Skill level '$name' created successfully!", 'categories', 'skill_level_created', 'tab=skill_levels');
            break;

        case 'update_skill_level':
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE skill_levels SET name = ?, description = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $description, $display_order, $id]);
            Auditor::log($pdo, $user_id, 'update', 'skill_levels', $id, ['action' => "Updated skill level: $name"]);
            
            logSecurityEvent('skill_level_updated', "Updated skill level: $name", $_SESSION['user_id']);
            
            respond(true, "Skill level '$name' updated successfully!", 'admin_age_skill', 'skill_level_updated');
            break;
            
        case 'delete_skill_level':
            $id = intval($_POST['id']);
            
            // Get name for logging
            $stmt = $pdo->prepare("SELECT name FROM skill_levels WHERE id = ?");
            $stmt->execute([$id]);
            $sl = $stmt->fetch();
            
            if ($sl) {
                $pdo->prepare("DELETE FROM skill_levels WHERE id = ?")->execute([$id]);
                Auditor::log($pdo, $user_id, 'delete', 'skill_levels', $id, ['action' => "Deleted skill level: {$sl['name']}"]);
                logSecurityEvent('skill_level_deleted', "Deleted skill level: {$sl['name']}", $_SESSION['user_id']);
            }
            
            respond(true, 'Skill level deleted successfully!', 'categories', 'skill_level_deleted', 'tab=skill_levels');
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
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => "Updated tax settings: $tax_name = $tax_rate%"]);
            
            logSecurityEvent('tax_settings_updated', "Updated tax settings: $tax_name = $tax_rate%", $_SESSION['user_id']);
            
            respond(true, 'Tax settings updated successfully!', 'admin_settings', 'tax_updated');
            break;
            
        default:
            respond(false, 'Invalid action');
            break;
    }
    
} catch (PDOException $e) {
    ErrorLogger::error("Age/skill management error: " . $e->getMessage());
    logSecurityEvent('age_skill_error', "Error in age/skill management: " . $e->getMessage(), $_SESSION['user_id']);
    respond(false, $e->getMessage());
}

exit();
?>
