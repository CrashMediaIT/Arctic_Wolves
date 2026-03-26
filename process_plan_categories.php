<?php
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Apply security headers
setSecurityHeaders();

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

requirePermission($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 'admin.manage_settings');

$user_id = $_SESSION['user_id'] ?? 0;

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';
$category_type = $_POST['category_type'] ?? '';

// Validate category type
$valid_types = ['workout', 'nutrition', 'practice'];
if (!in_array($category_type, $valid_types)) {
    header("Location: dashboard.php?page=admin_plan_categories&error=" . urlencode("Invalid category type"));
    exit;
}

// Get the appropriate table name
$table_map = [
    'workout' => 'workout_plan_categories',
    'nutrition' => 'nutrition_plan_categories',
    'practice' => 'practice_plan_categories'
];

$table = $table_map[$category_type];

// Pre-build queries keyed by table name to avoid dynamic SQL interpolation
$queries = [
    'workout_plan_categories' => [
        'select_by_name'   => 'SELECT id FROM workout_plan_categories WHERE name = ?',
        'insert'           => 'INSERT INTO workout_plan_categories (name, description, display_order) VALUES (?, ?, ?)',
        'select_by_id'     => 'SELECT name FROM workout_plan_categories WHERE id = ?',
        'delete'           => 'DELETE FROM workout_plan_categories WHERE id = ?',
        'select_dup'       => 'SELECT id FROM workout_plan_categories WHERE name = ? AND id != ?',
        'update'           => 'UPDATE workout_plan_categories SET name = ?, description = ?, display_order = ? WHERE id = ?',
    ],
    'nutrition_plan_categories' => [
        'select_by_name'   => 'SELECT id FROM nutrition_plan_categories WHERE name = ?',
        'insert'           => 'INSERT INTO nutrition_plan_categories (name, description, display_order) VALUES (?, ?, ?)',
        'select_by_id'     => 'SELECT name FROM nutrition_plan_categories WHERE id = ?',
        'delete'           => 'DELETE FROM nutrition_plan_categories WHERE id = ?',
        'select_dup'       => 'SELECT id FROM nutrition_plan_categories WHERE name = ? AND id != ?',
        'update'           => 'UPDATE nutrition_plan_categories SET name = ?, description = ?, display_order = ? WHERE id = ?',
    ],
    'practice_plan_categories' => [
        'select_by_name'   => 'SELECT id FROM practice_plan_categories WHERE name = ?',
        'insert'           => 'INSERT INTO practice_plan_categories (name, description, display_order) VALUES (?, ?, ?)',
        'select_by_id'     => 'SELECT name FROM practice_plan_categories WHERE id = ?',
        'delete'           => 'DELETE FROM practice_plan_categories WHERE id = ?',
        'select_dup'       => 'SELECT id FROM practice_plan_categories WHERE name = ? AND id != ?',
        'update'           => 'UPDATE practice_plan_categories SET name = ?, description = ?, display_order = ? WHERE id = ?',
    ],
];
$sql = $queries[$table];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

try {
    if ($action === 'create') {
        // Create new category
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);

        if (empty($name)) {
            throw new Exception("Category name is required");
        }

        // Check if category already exists
        $stmt = $pdo->prepare($sql['select_by_name']);
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception("A category with this name already exists");
        }

        // Insert new category
        $stmt = $pdo->prepare($sql['insert']);
        $stmt->execute([$name, $description, $display_order]);
        $new_category_id = $pdo->lastInsertId();

        Auditor::log($pdo, $user_id, 'create', $table, $new_category_id, ['action' => "Created {$category_type} plan category: {$name}"]);

        // Log the action
        logSecurityEvent($_SESSION['user_id'], 'category_created', 
            "Created {$category_type} plan category: {$name}");

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Category '{$name}' created successfully!"]);
            exit;
        }
        header("Location: dashboard.php?page=admin_plan_categories&success=" . 
            urlencode("Category '{$name}' created successfully"));
        exit;

    } elseif ($action === 'delete') {
        // Delete category
        $category_id = intval($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            throw new Exception("Invalid category ID");
        }

        // Get category name for logging
        $stmt = $pdo->prepare($sql['select_by_id']);
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            throw new Exception("Category not found");
        }

        // Delete the category (plans using it will have category_id set to NULL due to ON DELETE SET NULL)
        $stmt = $pdo->prepare($sql['delete']);
        $stmt->execute([$category_id]);

        Auditor::log($pdo, $user_id, 'delete', $table, $category_id, ['action' => "Deleted {$category_type} plan category: {$category['name']}"]);

        // Log the action
        logSecurityEvent($_SESSION['user_id'], 'category_deleted', 
            "Deleted {$category_type} plan category: {$category['name']}");

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Category '{$category['name']}' deleted successfully!"]);
            exit;
        }
        header("Location: dashboard.php?page=admin_plan_categories&success=" . 
            urlencode("Category '{$category['name']}' deleted successfully"));
        exit;

    } elseif ($action === 'update') {
        // Update category
        $category_id = intval($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);

        if ($category_id <= 0) {
            throw new Exception("Invalid category ID");
        }

        if (empty($name)) {
            throw new Exception("Category name is required");
        }

        // Check if another category with this name already exists
        $stmt = $pdo->prepare($sql['select_dup']);
        $stmt->execute([$name, $category_id]);
        if ($stmt->fetch()) {
            throw new Exception("Another category with this name already exists");
        }

        // Update the category
        $stmt = $pdo->prepare($sql['update']);
        $stmt->execute([$name, $description, $display_order, $category_id]);

        Auditor::log($pdo, $user_id, 'update', $table, $category_id, ['action' => "Updated {$category_type} plan category: {$name}"]);

        // Log the action
        logSecurityEvent($_SESSION['user_id'], 'category_updated', 
            "Updated {$category_type} plan category: {$name}");

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Category '{$name}' updated successfully!"]);
            exit;
        }
        header("Location: dashboard.php?page=admin_plan_categories&success=" . 
            urlencode("Category '{$name}' updated successfully"));
        exit;

    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    // Log the error
    logSecurityEvent($_SESSION['user_id'], 'category_error', 
        "Error managing {$category_type} category: " . $e->getMessage());

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header("Location: dashboard.php?page=admin_plan_categories&error=" . urlencode($e->getMessage()));
    exit;
}
?>
