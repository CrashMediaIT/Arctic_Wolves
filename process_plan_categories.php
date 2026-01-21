<?php
session_start();
require_once 'db_config.php';
require_once 'security.php';

// Apply security headers
applySecurityHeaders();

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

requirePermission($pdo, $_SESSION['user_id'], $_SESSION['role'], 'admin.manage_settings');

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?page=admin_plan_categories&error=" . urlencode("Invalid security token"));
    exit;
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
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception("A category with this name already exists");
        }

        // Insert new category
        $stmt = $pdo->prepare("
            INSERT INTO $table (name, description, display_order)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $description, $display_order]);

        // Log the action
        logSecurityEvent($pdo, $_SESSION['user_id'], 'category_created', 
            "Created {$category_type} plan category: {$name}");

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
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            throw new Exception("Category not found");
        }

        // Delete the category (plans using it will have category_id set to NULL due to ON DELETE SET NULL)
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$category_id]);

        // Log the action
        logSecurityEvent($pdo, $_SESSION['user_id'], 'category_deleted', 
            "Deleted {$category_type} plan category: {$category['name']}");

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
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE name = ? AND id != ?");
        $stmt->execute([$name, $category_id]);
        if ($stmt->fetch()) {
            throw new Exception("Another category with this name already exists");
        }

        // Update the category
        $stmt = $pdo->prepare("
            UPDATE $table 
            SET name = ?, description = ?, display_order = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $display_order, $category_id]);

        // Log the action
        logSecurityEvent($pdo, $_SESSION['user_id'], 'category_updated', 
            "Updated {$category_type} plan category: {$name}");

        header("Location: dashboard.php?page=admin_plan_categories&success=" . 
            urlencode("Category '{$name}' updated successfully"));
        exit;

    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    // Log the error
    logSecurityEvent($pdo, $_SESSION['user_id'], 'category_error', 
        "Error managing {$category_type} category: " . $e->getMessage());

    header("Location: dashboard.php?page=admin_plan_categories&error=" . urlencode($e->getMessage()));
    exit;
}
?>
