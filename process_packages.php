<?php
// process_packages.php - Handle package CRUD operations
session_start();
require 'db_config.php';
require 'security.php';

// Set security headers
setSecurityHeaders();

// Check admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
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
            $package_type = $_POST['package_type'];
            $price = floatval($_POST['price']);
            $credits = $package_type === 'credits' ? intval($_POST['credits']) : null;
            $valid_days = intval($_POST['valid_days']);
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || $price < 0) {
                throw new Exception('Invalid package data');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, description, package_type, price, credits, valid_days, 
                                     age_group_id, skill_level_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $description, $package_type, $price, $credits, $valid_days,
                $age_group_id, $skill_level_id, $is_active
            ]);
            
            header("Location: dashboard.php?page=admin_packages&status=success");
            exit();
            
        case 'update':
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $package_type = $_POST['package_type'];
            $price = floatval($_POST['price']);
            $credits = $package_type === 'credits' ? intval($_POST['credits']) : null;
            $valid_days = intval($_POST['valid_days']);
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || $price < 0 || $package_id <= 0) {
                throw new Exception('Invalid package data');
            }
            
            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, description = ?, package_type = ?, price = ?, credits = ?, 
                    valid_days = ?, age_group_id = ?, skill_level_id = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $package_type, $price, $credits, $valid_days,
                $age_group_id, $skill_level_id, $is_active, $package_id
            ]);
            
            header("Location: dashboard.php?page=admin_packages&status=success");
            exit();
            
        case 'delete':
            $package_id = intval($_POST['package_id']);
            
            // Check if package has been purchased
            $check = $pdo->prepare("SELECT COUNT(*) FROM user_package_credits WHERE package_id = ?");
            $check->execute([$package_id]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Cannot delete package with existing purchases');
            }
            
            // Delete package and related sessions
            $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            
            header("Location: dashboard.php?page=admin_packages&status=success&action=delete");
            exit();
            
        case 'update_sessions':
            $package_id = intval($_POST['package_id']);
            $session_ids = isset($_POST['session_ids']) ? array_map('intval', $_POST['session_ids']) : [];
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Remove all existing sessions
            $stmt = $pdo->prepare("DELETE FROM package_sessions WHERE package_id = ?");
            $stmt->execute([$package_id]);
            
            // Add new sessions
            if (!empty($session_ids)) {
                $stmt = $pdo->prepare("INSERT INTO package_sessions (package_id, session_id) VALUES (?, ?)");
                foreach ($session_ids as $session_id) {
                    $stmt->execute([$package_id, $session_id]);
                }
            }
            
            $pdo->commit();
            
            header("Location: dashboard.php?page=admin_packages&status=success");
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Package processing error: " . $e->getMessage());
    header("Location: dashboard.php?page=admin_packages&status=error");
    exit();
}
?>
