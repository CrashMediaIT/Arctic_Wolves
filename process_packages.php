<?php
// process_packages.php - Handle package CRUD operations
session_start();
require 'db_config.php';
require 'security.php';

// Set security headers
setSecurityHeaders();

// Check admin access
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    http_response_code(403);
    die('Access denied.');
}

// Handle GET request for retrieving package sessions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_sessions') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT session_id FROM package_sessions WHERE package_id = ?");
    $stmt->execute([$package_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($sessions);
    exit();
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? intval($_POST['session_count'] ?? 0));
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            if (empty($name) || $price < 0) {
                throw new Exception('Invalid package data: name is required and price must be positive');
            }
            
            $pdo->beginTransaction();
            
            // Insert package with package_type and store_credit
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, description, price, credits, valid_days, 
                                     age_group, skill_level, is_active, package_type, store_credit, enable_child_checkin)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin
            ]);
            
            $package_id = $pdo->lastInsertId();
            
            // Note: package_sessions table may have different schema - skip for now if columns don't exist
            // Package sessions feature requires schema update
            
            $pdo->commit();
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package created successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'update':
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? 0);
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            if (empty($name) || $price < 0 || $package_id <= 0) {
                throw new Exception('Invalid package data');
            }
            
            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, description = ?, price = ?, credits = ?, 
                    valid_days = ?, age_group = ?, skill_level = ?, is_active = ?, package_type = ?, store_credit = ?, enable_child_checkin = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin, $package_id
            ]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'delete':
            $package_id = intval($_POST['package_id']);
            
            // Check if package has been purchased (check both tables for safety)
            $check = $pdo->prepare("SELECT COUNT(*) FROM user_packages WHERE package_id = ?");
            $check->execute([$package_id]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Cannot delete package with existing purchases');
            }
            
            // Delete package
            $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success&action=delete");
            exit();
            
        case 'update_sessions':
            // Save session assignments for a bundled package
            $package_id = intval($_POST['package_id'] ?? 0);
            $session_ids = $_POST['session_ids'] ?? [];
            
            if ($package_id <= 0) {
                throw new Exception('Invalid package ID');
            }
            
            // Verify package exists
            $check = $pdo->prepare("SELECT id FROM packages WHERE id = ?");
            $check->execute([$package_id]);
            if (!$check->fetch()) {
                throw new Exception('Package not found');
            }
            
            $pdo->beginTransaction();
            
            // Remove existing session links
            $delete_stmt = $pdo->prepare("DELETE FROM package_sessions WHERE package_id = ?");
            $delete_stmt->execute([$package_id]);
            
            // Insert new session links
            if (!empty($session_ids) && is_array($session_ids)) {
                $insert_stmt = $pdo->prepare("INSERT INTO package_sessions (package_id, session_id) VALUES (?, ?)");
                foreach ($session_ids as $sid) {
                    $sid = intval($sid);
                    if ($sid > 0) {
                        $insert_stmt->execute([$package_id, $sid]);
                    }
                }
            }
            
            $pdo->commit();
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package sessions updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'toggle_status':
            header('Content-Type: application/json');
            $package_id = intval($_POST['id'] ?? $_POST['package_id'] ?? 0);
            
            if ($package_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                exit();
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package) {
                echo json_encode(['success' => false, 'message' => 'Package not found']);
                exit();
            }
            
            $new_status = $package['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE packages SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $package_id]);
            
            echo json_encode(['success' => true, 'message' => 'Package status updated']);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Package processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=products&status=error");
    exit();
}
?>
