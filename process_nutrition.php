<?php
/**
 * Process Nutrition Actions
 * Handles CRUD operations for meals and nutrition plans
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

setSecurityHeaders();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check permissions
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['health_coach', 'coach', 'coach_plus', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Validate CSRF
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'create_meal':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $serving_size = trim($_POST['serving_size'] ?? '');
            $calories = !empty($_POST['calories']) ? floatval($_POST['calories']) : null;
            $protein_g = !empty($_POST['protein_g']) ? floatval($_POST['protein_g']) : null;
            $carbs_g = !empty($_POST['carbs_g']) ? floatval($_POST['carbs_g']) : null;
            $fat_g = !empty($_POST['fat_g']) ? floatval($_POST['fat_g']) : null;
            
            if (empty($name)) {
                throw new Exception('Meal name is required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO food_library (name, description, category, serving_size, calories, protein_g, carbs_g, fat_g, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description ?: null, $category ?: null, $serving_size ?: null, $calories, $protein_g, $carbs_g, $fat_g, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Meal created successfully']);
            break;
            
        case 'update_meal':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $serving_size = trim($_POST['serving_size'] ?? '');
            $calories = !empty($_POST['calories']) ? floatval($_POST['calories']) : null;
            $protein_g = !empty($_POST['protein_g']) ? floatval($_POST['protein_g']) : null;
            $carbs_g = !empty($_POST['carbs_g']) ? floatval($_POST['carbs_g']) : null;
            $fat_g = !empty($_POST['fat_g']) ? floatval($_POST['fat_g']) : null;
            
            if (empty($id) || empty($name)) {
                throw new Exception('Meal ID and name are required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE food_library 
                SET name = ?, description = ?, category = ?, serving_size = ?, calories = ?, protein_g = ?, carbs_g = ?, fat_g = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description ?: null, $category ?: null, $serving_size ?: null, $calories, $protein_g, $carbs_g, $fat_g, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Meal updated successfully']);
            break;
            
        case 'delete_meal':
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('Meal ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM food_library WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Meal deleted successfully']);
            break;
            
        case 'create_plan':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target_calories = !empty($_POST['target_calories']) ? intval($_POST['target_calories']) : null;
            $target_protein_g = !empty($_POST['target_protein_g']) ? intval($_POST['target_protein_g']) : null;
            $target_carbs_g = !empty($_POST['target_carbs_g']) ? intval($_POST['target_carbs_g']) : null;
            $target_fat_g = !empty($_POST['target_fat_g']) ? intval($_POST['target_fat_g']) : null;
            $meals = $_POST['meals'] ?? [];
            
            if (empty($name)) {
                throw new Exception('Plan name is required');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO nutrition_plans (name, description, target_calories, target_protein_g, target_carbs_g, target_fat_g, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description ?: null, $target_calories, $target_protein_g, $target_carbs_g, $target_fat_g, $user_id]);
            $plan_id = $pdo->lastInsertId();
            
            // Add meals to plan
            if (!empty($meals)) {
                $order = 0;
                foreach ($meals as $meal) {
                    if (!empty($meal['id'])) {
                        // Create meal entry
                        $meal_type = $meal['type'] ?? 'breakfast';
                        $stmt = $pdo->prepare("
                            INSERT INTO nutrition_plan_meals (nutrition_plan_id, meal_type, meal_order)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$plan_id, $meal_type, $order++]);
                        $plan_meal_id = $pdo->lastInsertId();
                        
                        // Link food to meal
                        $stmt = $pdo->prepare("
                            INSERT INTO nutrition_plan_meal_foods (meal_id, food_id, serving_quantity)
                            VALUES (?, ?, 1)
                        ");
                        $stmt->execute([$plan_meal_id, intval($meal['id'])]);
                    }
                }
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Nutrition plan created successfully']);
            break;
            
        case 'update_plan':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target_calories = !empty($_POST['target_calories']) ? intval($_POST['target_calories']) : null;
            $target_protein_g = !empty($_POST['target_protein_g']) ? intval($_POST['target_protein_g']) : null;
            $target_carbs_g = !empty($_POST['target_carbs_g']) ? intval($_POST['target_carbs_g']) : null;
            $target_fat_g = !empty($_POST['target_fat_g']) ? intval($_POST['target_fat_g']) : null;
            
            if (empty($id) || empty($name)) {
                throw new Exception('Plan ID and name are required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE nutrition_plans 
                SET name = ?, description = ?, target_calories = ?, target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description ?: null, $target_calories, $target_protein_g, $target_carbs_g, $target_fat_g, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Nutrition plan updated successfully']);
            break;
            
        case 'delete_plan':
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('Plan ID is required');
            }
            
            // Delete plan (cascade will handle related records)
            $stmt = $pdo->prepare("DELETE FROM nutrition_plans WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Nutrition plan deleted successfully']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
