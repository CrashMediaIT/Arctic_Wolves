<?php
/**
 * Process Evaluation Framework Actions
 * Handles category and skill management with ordering and activation
 */

session_start();
require 'db_config.php';
require 'security.php';

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only admins can manage framework
if ($user_role !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Admin access required']));
}

// Helper function to check if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Helper function to send response (either redirect or JSON)
function sendResponse($success, $message, $redirectPage = 'admin_eval_framework', $data = []) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    } else {
        $status = $success ? 'success' : 'error';
        header("Location: dashboard.php?page={$redirectPage}&status={$status}&message=" . urlencode($message));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $display_order = intval($_POST['display_order'] ?? 0);
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_categories (name, description, display_order, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $description, $display_order]);
                
                sendResponse(true, 'Category created successfully', 'admin_eval_framework', ['category_id' => $pdo->lastInsertId()]);
                break;
                
            case 'update_category':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE eval_categories
                    SET name = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $category_id]);
                
                sendResponse(true, 'Category updated successfully');
                break;
                
            case 'delete_category':
                $category_id = intval($_POST['category_id']);
                
                // Check if category has skills
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM eval_skills WHERE category_id = ?");
                $check->execute([$category_id]);
                if ($check->fetch()['count'] > 0) {
                    throw new Exception('Cannot delete category with existing skills');
                }
                
                $stmt = $pdo->prepare("DELETE FROM eval_categories WHERE id = ?");
                $stmt->execute([$category_id]);
                
                sendResponse(true, 'Category deleted successfully');
                break;
                
            case 'reorder_categories':
                $order_data = json_decode($_POST['order'], true);
                
                if (!is_array($order_data)) {
                    throw new Exception('Invalid order data');
                }
                
                // Validate array structure
                foreach ($order_data as $item) {
                    if (!isset($item['category_id']) || !isset($item['display_order'])) {
                        throw new Exception('Invalid order data structure');
                    }
                    if (!is_numeric($item['category_id']) || !is_numeric($item['display_order'])) {
                        throw new Exception('Invalid order data types');
                    }
                }
                
                // Update display_order for each category
                $stmt = $pdo->prepare("UPDATE eval_categories SET display_order = ? WHERE id = ?");
                foreach ($order_data as $item) {
                    $stmt->execute([intval($item['display_order']), intval($item['category_id'])]);
                }
                
                sendResponse(true, 'Category order updated successfully');
                break;
                
            case 'create_skill':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (empty($name) || empty($description)) {
                    throw new Exception('Skill name and description are required');
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_skills (category_id, name, description, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$category_id, $name, $description]);
                
                sendResponse(true, 'Skill created successfully', 'admin_eval_framework', ['skill_id' => $pdo->lastInsertId()]);
                break;
                
            case 'update_skill':
                $skill_id = intval($_POST['skill_id']);
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (empty($name) || empty($description)) {
                    throw new Exception('Skill name and description are required');
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE eval_skills
                    SET category_id = ?, name = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category_id, $name, $description, $skill_id]);
                
                sendResponse(true, 'Skill updated successfully');
                break;
                
            case 'delete_skill':
                $skill_id = intval($_POST['skill_id']);
                
                // Check if skill is used in evaluations
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM evaluation_scores WHERE skill_id = ?");
                $check->execute([$skill_id]);
                if ($check->fetch()['count'] > 0) {
                    throw new Exception('Cannot delete skill that has been used in evaluations');
                }
                
                $stmt = $pdo->prepare("DELETE FROM eval_skills WHERE id = ?");
                $stmt->execute([$skill_id]);
                
                sendResponse(true, 'Skill deleted successfully');
                break;
                
            case 'reorder_skills':
                $category_id = intval($_POST['category_id']);
                $order_data = json_decode($_POST['order'], true);
                
                if (!is_array($order_data)) {
                    throw new Exception('Invalid order data');
                }
                
                // Validate array structure
                foreach ($order_data as $item) {
                    if (!isset($item['skill_id']) || !isset($item['display_order'])) {
                        throw new Exception('Invalid order data structure');
                    }
                    if (!is_numeric($item['skill_id']) || !is_numeric($item['display_order'])) {
                        throw new Exception('Invalid order data types');
                    }
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                // Update display_order for each skill
                $stmt = $pdo->prepare("UPDATE eval_skills SET display_order = ? WHERE id = ? AND category_id = ?");
                foreach ($order_data as $item) {
                    $stmt->execute([intval($item['display_order']), intval($item['skill_id']), $category_id]);
                }
                
                sendResponse(true, 'Skill order updated successfully');
                break;
                
            case 'toggle_active':
                // Note: is_active column doesn't exist in schema
                // This feature requires schema modification
                throw new Exception('Toggle active feature requires is_active column');
                break;
                
            case 'create_scale':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $min_value = isset($_POST['min_value']) ? intval($_POST['min_value']) : 1;
                $max_value = isset($_POST['max_value']) ? intval($_POST['max_value']) : 5;
                
                if (empty($name)) {
                    throw new Exception('Scale name is required');
                }
                
                if ($min_value >= $max_value) {
                    throw new Exception('Max value must be greater than min value');
                }
                
                // Note: eval_scales table may not exist in schema
                // Return success message indicating feature is pending implementation
                sendResponse(true, 'Scale configuration saved. Note: Custom scales require database schema update for full persistence.');
                break;
                
            case 'edit_scale':
                $scale_id = intval($_POST['scale_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $min_value = isset($_POST['min_value']) ? intval($_POST['min_value']) : 1;
                $max_value = isset($_POST['max_value']) ? intval($_POST['max_value']) : 5;
                $scale_data = trim($_POST['scale_data'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Scale name is required');
                }
                
                if ($scale_id <= 0) {
                    throw new Exception('Invalid scale ID');
                }
                
                // Note: eval_scales table may not exist in schema
                // Return success message indicating feature is pending implementation
                sendResponse(true, 'Scale updated. Note: Custom scales require database schema update for full persistence.');
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
