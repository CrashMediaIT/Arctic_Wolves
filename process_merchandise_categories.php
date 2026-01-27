<?php
// process_merchandise_categories.php - Handle merchandise category CRUD operations
session_start();
require 'db_config.php';
require 'security.php';
require 'file_upload_validator.php';

// Set security headers
setSecurityHeaders();

// Check admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * Handle image upload for merchandise categories
 */
function handleCategoryImageUpload($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    // Validate the file
    $validation = FileUploadValidator::validate($file, 'image');
    if (!$validation['valid']) {
        throw new Exception('Image upload failed: ' . implode(', ', $validation['errors']));
    }
    
    // Generate safe filename
    $safeFilename = FileUploadValidator::generateSafeFilename($file['name']);
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/merchandise/categories/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $targetPath = $uploadDir . $safeFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save uploaded image');
    }
    
    // Return the relative URL path
    return 'uploads/merchandise/categories/' . $safeFilename;
}

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            // Handle image upload
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = handleCategoryImageUpload($_FILES['image']);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO merchandise_categories (name, description, image_url, display_order, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $description ?: null,
                $imageUrl,
                $displayOrder,
                $isActive,
                $_SESSION['user_id']
            ]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Category created successfully!', 'id' => $pdo->lastInsertId()]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_categories&status=success");
            exit();
            
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            // Handle image upload
            $imageUrl = null;
            $updateImage = false;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = handleCategoryImageUpload($_FILES['image']);
                $updateImage = true;
            }
            
            if ($updateImage) {
                $stmt = $pdo->prepare("
                    UPDATE merchandise_categories 
                    SET name = ?, description = ?, image_url = ?, display_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description ?: null, $imageUrl, $displayOrder, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE merchandise_categories 
                    SET name = ?, description = ?, display_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description ?: null, $displayOrder, $isActive, $id]);
            }
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Category updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_categories&status=success");
            exit();
            
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            // Check if category has products
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM merchandise_products WHERE category_id = ?");
            $checkStmt->execute([$id]);
            $productCount = $checkStmt->fetchColumn();
            
            if ($productCount > 0) {
                throw new Exception('Cannot delete category with existing products. Please move or delete the products first.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM merchandise_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_categories&status=success");
            exit();
            
        case 'toggle_status':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM merchandise_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new Exception('Category not found');
            }
            
            $newStatus = $category['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE merchandise_categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Category status updated!', 'new_status' => $newStatus]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_categories&status=success");
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Merchandise category processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=merchandise_categories&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
