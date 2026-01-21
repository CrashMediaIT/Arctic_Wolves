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
                
                // Get next display order
                $max_order = $pdo->query("SELECT MAX(display_order) as max_order FROM eval_categories")->fetch();
                $display_order = ($max_order['max_order'] ?? 0) + 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_categories (name, description, display_order, is_active, created_at)
                    VALUES (?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$name, $description, $display_order]);
                
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
                $category_ids = json_decode($_POST['category_ids'], true);
                
                if (!is_array($category_ids)) {
                    throw new Exception('Invalid category order data');
                }
                
                $stmt = $pdo->prepare("UPDATE eval_categories SET display_order = ? WHERE id = ?");
                foreach ($category_ids as $order => $category_id) {
                    $stmt->execute([$order + 1, intval($category_id)]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Categories reordered successfully'
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
                
                // Get next display order for this category
                $max_order = $pdo->prepare("SELECT MAX(display_order) as max_order FROM eval_skills WHERE category_id = ?");
                $max_order->execute([$category_id]);
                $display_order = ($max_order->fetch()['max_order'] ?? 0) + 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_skills (category_id, name, description, criteria, display_order, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$category_id, $name, $description, $criteria, $display_order]);
                
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
                $skill_ids = json_decode($_POST['skill_ids'], true);
                
                if (!is_array($skill_ids)) {
                    throw new Exception('Invalid skill order data');
                }
                
                $stmt = $pdo->prepare("UPDATE eval_skills SET display_order = ? WHERE id = ?");
                foreach ($skill_ids as $order => $skill_id) {
                    $stmt->execute([$order + 1, intval($skill_id)]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Skills reordered successfully'
                ]);
                break;
                
            case 'toggle_active':
                $type = $_POST['type']; // 'category' or 'skill'
                $id = intval($_POST['id']);
                $active = intval($_POST['active']);
                
                if (!in_array($type, ['category', 'skill'])) {
                    throw new Exception('Invalid type');
                }
                
                $table = $type === 'category' ? 'eval_categories' : 'eval_skills';
                $stmt = $pdo->prepare("UPDATE $table SET is_active = ? WHERE id = ?");
                $stmt->execute([$active, $id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($type) . ' ' . ($active ? 'activated' : 'deactivated')
                ]);
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
