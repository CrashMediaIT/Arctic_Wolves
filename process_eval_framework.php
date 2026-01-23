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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                // Note: display_order and is_active columns don't exist in schema
                // Removing these references per governance: fix code to match schema
                $stmt = $pdo->prepare("
                    INSERT INTO eval_categories (name, description, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$name, $description]);
                
                echo json_encode([
                    'success' => true,
                    'category_id' => $pdo->lastInsertId(),
                    'message' => 'Category created successfully'
                ]);
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
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Category updated successfully'
                ]);
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
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ]);
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
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Category order updated successfully'
                ]);
                break;
                
            case 'create_skill':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $criteria = trim($_POST['criteria'] ?? '');
                
                if (empty($name) || empty($description)) {
                    throw new Exception('Skill name and description are required');
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                // Note: display_order, is_active, and criteria columns don't exist in schema
                // Removing these references per governance: fix code to match schema
                $stmt = $pdo->prepare("
                    INSERT INTO eval_skills (category_id, name, description, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$category_id, $name, $description]);
                
                echo json_encode([
                    'success' => true,
                    'skill_id' => $pdo->lastInsertId(),
                    'message' => 'Skill created successfully'
                ]);
                break;
                
            case 'update_skill':
                $skill_id = intval($_POST['skill_id']);
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $criteria = trim($_POST['criteria'] ?? '');
                
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
                    SET category_id = ?, name = ?, description = ?, criteria = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category_id, $name, $description, $criteria, $skill_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Skill updated successfully'
                ]);
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
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Skill deleted successfully'
                ]);
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
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Skill order updated successfully'
                ]);
                break;
                
            case 'toggle_active':
                // Note: is_active column doesn't exist in schema
                // This feature requires schema modification
                throw new Exception('Toggle active feature requires is_active column');
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
