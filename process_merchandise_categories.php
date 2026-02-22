<?php
// process_merchandise_categories.php - Handle merchandise category CRUD operations
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/file_upload_validator.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
$user_id = $_SESSION['user_id'] ?? 0;

/**
 * Handle image upload for merchandise categories
 */
function handleCategoryImageUpload($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    // Validate the file
    $validation = FileUploadValidator::validateImage($file, 100);
    if (!$validation['valid']) {
        throw new Exception('Image upload failed: ' . ($validation['error'] ?? 'Unknown error'));
    }
    
    // Generate safe filename
    $safeFilename = FileUploadValidator::generateUniqueFilename($file['name']);
    
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
            $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            $slug = trim($_POST['slug'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            // Generate slug if not provided
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
                $slug = trim($slug, '-');
            }
            
            // Handle image upload
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = handleCategoryImageUpload($_FILES['image']);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO merchandise_categories (parent_id, name, slug, description, image_url, display_order, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $parentId,
                $name,
                $slug,
                $description ?: null,
                $imageUrl,
                $displayOrder,
                $isActive,
                $_SESSION['user_id']
            ]);
            Auditor::log($pdo, $user_id, 'CREATE', 'merchandise_categories', $pdo->lastInsertId(), ['action' => 'Created merchandise category', 'name' => $name]);
            
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
            $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            $slug = trim($_POST['slug'] ?? '');
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            // Prevent setting self as parent
            if ($parentId === $id) {
                throw new Exception('Category cannot be its own parent');
            }
            
            // Prevent circular references - check if parentId is a descendant of this category
            if ($parentId !== null) {
                $checkId = $parentId;
                $visited = [$id];
                while ($checkId !== null) {
                    if (in_array($checkId, $visited)) {
                        throw new Exception('Cannot set parent: would create a circular reference');
                    }
                    $visited[] = $checkId;
                    $ancestorStmt = $pdo->prepare("SELECT parent_id FROM merchandise_categories WHERE id = ?");
                    $ancestorStmt->execute([$checkId]);
                    $ancestor = $ancestorStmt->fetch(PDO::FETCH_ASSOC);
                    $checkId = ($ancestor && !empty($ancestor['parent_id'])) ? intval($ancestor['parent_id']) : null;
                }
            }
            
            // Generate slug if not provided
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
                $slug = trim($slug, '-');
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
                    SET parent_id = ?, name = ?, slug = ?, description = ?, image_url = ?, display_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$parentId, $name, $slug, $description ?: null, $imageUrl, $displayOrder, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE merchandise_categories 
                    SET parent_id = ?, name = ?, slug = ?, description = ?, display_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$parentId, $name, $slug, $description ?: null, $displayOrder, $isActive, $id]);
            }
            Auditor::log($pdo, $user_id, 'UPDATE', 'merchandise_categories', $id, ['action' => 'Updated merchandise category', 'name' => $name]);
            
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
            
            // Check if category has subcategories
            $checkSubStmt = $pdo->prepare("SELECT COUNT(*) FROM merchandise_categories WHERE parent_id = ?");
            $checkSubStmt->execute([$id]);
            $subCount = $checkSubStmt->fetchColumn();
            
            if ($subCount > 0) {
                throw new Exception('Cannot delete category with subcategories. Please move or delete the subcategories first.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM merchandise_categories WHERE id = ?");
            $stmt->execute([$id]);
            Auditor::log($pdo, $user_id, 'DELETE', 'merchandise_categories', $id, ['action' => 'Deleted merchandise category']);
            
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
            Auditor::log($pdo, $user_id, 'UPDATE', 'merchandise_categories', $id, ['action' => 'Toggled merchandise category status', 'new_status' => $newStatus]);
            
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
    ErrorLogger::error("Merchandise category processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=merchandise_categories&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
