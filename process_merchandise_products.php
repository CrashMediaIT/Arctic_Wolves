<?php
// process_merchandise_products.php - Handle merchandise product CRUD operations
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/file_upload_validator.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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

// Handle GET requests for fetching data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_sizes') {
        $productId = intval($_GET['product_id'] ?? 0);
        
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, size, quantity FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id ASC");
            $stmt->execute([$productId]);
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'sizes' => $sizes]);
            exit();
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error fetching sizes']);
            exit();
        }
    }
    
    if ($action === 'get_stock_movements') {
        $productId = intval($_GET['product_id'] ?? 0);
        
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT sm.*, mps.size, u.first_name, u.last_name
                FROM merchandise_stock_movements sm
                LEFT JOIN merchandise_product_sizes mps ON sm.size_id = mps.id
                LEFT JOIN users u ON sm.created_by = u.id
                WHERE sm.product_id = ?
                ORDER BY sm.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$productId]);
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'movements' => $movements]);
            exit();
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error fetching stock movements']);
            exit();
        }
    }
    
    if ($action === 'get_audit_history') {
        $productId = intval($_GET['product_id'] ?? 0);
        
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT sa.*, u.first_name, u.last_name,
                    (SELECT GROUP_CONCAT(
                        CONCAT(mps.size, ':', sai.system_quantity, ':', sai.actual_quantity, ':', sai.discrepancy)
                        SEPARATOR '|'
                    )
                    FROM merchandise_stock_audit_items sai
                    JOIN merchandise_product_sizes mps ON sai.size_id = mps.id
                    WHERE sai.audit_id = sa.id
                    ) as items_data
                FROM merchandise_stock_audits sa
                LEFT JOIN users u ON sa.created_by = u.id
                WHERE sa.product_id = ?
                ORDER BY sa.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$productId]);
            $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse items_data into structured array
            foreach ($audits as &$audit) {
                $audit['items'] = [];
                if (!empty($audit['items_data'])) {
                    $items = explode('|', $audit['items_data']);
                    foreach ($items as $item) {
                        $parts = explode(':', $item);
                        if (count($parts) === 4) {
                            $audit['items'][] = [
                                'size' => $parts[0],
                                'system_quantity' => intval($parts[1]),
                                'actual_quantity' => intval($parts[2]),
                                'discrepancy' => intval($parts[3])
                            ];
                        }
                    }
                }
                unset($audit['items_data']);
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'audits' => $audits]);
            exit();
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error fetching audit history']);
            exit();
        }
    }
    
    // Invalid GET action
    http_response_code(400);
    die('Invalid request');
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

/**
 * Handle image upload for merchandise products
 */
function handleProductImageUpload($file) {
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
    $uploadDir = __DIR__ . '/uploads/merchandise/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $targetPath = $uploadDir . $safeFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save uploaded image');
    }
    
    // Return the relative URL path
    return 'uploads/merchandise/products/' . $safeFilename;
}

/**
 * Handle sizes for a product
 */
function handleProductSizes($pdo, $productId, $sizes, $quantities, $sizeIds = []) {
    // Delete existing sizes if updating (except ones with valid IDs)
    if (!empty($sizeIds)) {
        // Keep track of which size IDs we want to keep
        $validSizeIds = array_filter($sizeIds, function($id) { return !empty($id) && intval($id) > 0; });
        
        if (!empty($validSizeIds)) {
            $placeholders = implode(',', array_fill(0, count($validSizeIds), '?'));
            $deleteStmt = $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ? AND id NOT IN ($placeholders)");
            $params = array_merge([$productId], array_values($validSizeIds));
            $deleteStmt->execute($params);
        } else {
            // Delete all existing sizes for this product
            $deleteStmt = $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ?");
            $deleteStmt->execute([$productId]);
        }
    }
    
    // Insert or update sizes
    $insertStmt = $pdo->prepare("
        INSERT INTO merchandise_product_sizes (product_id, size, quantity)
        VALUES (?, ?, ?)
    ");
    $updateStmt = $pdo->prepare("
        UPDATE merchandise_product_sizes 
        SET size = ?, quantity = ?
        WHERE id = ? AND product_id = ?
    ");
    
    for ($i = 0; $i < count($sizes); $i++) {
        $size = trim($sizes[$i] ?? '');
        $quantity = intval($quantities[$i] ?? 0);
        $sizeId = isset($sizeIds[$i]) ? intval($sizeIds[$i]) : 0;
        
        if (empty($size)) {
            continue;
        }
        
        if ($sizeId > 0) {
            // Update existing size
            $updateStmt->execute([$size, $quantity, $sizeId, $productId]);
        } else {
            // Insert new size
            $insertStmt->execute([$productId, $size, $quantity]);
        }
    }
}

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $costPrice = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
            $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $trackInventory = intval($_POST['track_inventory'] ?? 1);
            
            if (empty($name)) {
                throw new Exception('Product name is required');
            }
            
            if ($price < 0) {
                throw new Exception('Price must be a positive number');
            }
            
            // Handle image upload
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = handleProductImageUpload($_FILES['image']);
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO merchandise_products (name, sku, category_id, description, price, cost_price, image_url, is_active, track_inventory, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $sku ?: null,
                $categoryId,
                $description ?: null,
                $price,
                $costPrice,
                $imageUrl,
                $isActive,
                $trackInventory,
                $_SESSION['user_id']
            ]);
            
            $productId = $pdo->lastInsertId();
            
            // Handle sizes
            $sizes = $_POST['sizes'] ?? [];
            $quantities = $_POST['quantities'] ?? [];
            
            if (!empty($sizes)) {
                handleProductSizes($pdo, $productId, $sizes, $quantities);
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'CREATE', 'merchandise_products', $productId, ['action' => 'Created merchandise product', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product created successfully!', 'id' => $productId]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $costPrice = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
            $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $trackInventory = isset($_POST['track_inventory']) ? 1 : 0;
            
            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            if (empty($name)) {
                throw new Exception('Product name is required');
            }
            
            if ($price < 0) {
                throw new Exception('Price must be a positive number');
            }
            
            $pdo->beginTransaction();
            
            // Handle image upload
            $imageUrl = null;
            $updateImage = false;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = handleProductImageUpload($_FILES['image']);
                $updateImage = true;
            }
            
            if ($updateImage) {
                $stmt = $pdo->prepare("
                    UPDATE merchandise_products 
                    SET name = ?, sku = ?, category_id = ?, description = ?, price = ?, cost_price = ?, image_url = ?, is_active = ?, track_inventory = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $sku ?: null, $categoryId, $description ?: null, $price, $costPrice, $imageUrl, $isActive, $trackInventory, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE merchandise_products 
                    SET name = ?, sku = ?, category_id = ?, description = ?, price = ?, cost_price = ?, is_active = ?, track_inventory = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $sku ?: null, $categoryId, $description ?: null, $price, $costPrice, $isActive, $trackInventory, $id]);
            }
            
            // Handle sizes if provided
            $sizes = $_POST['sizes'] ?? [];
            $quantities = $_POST['quantities'] ?? [];
            $sizeIds = $_POST['size_ids'] ?? [];
            
            if (!empty($sizes)) {
                handleProductSizes($pdo, $id, $sizes, $quantities, $sizeIds);
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'merchandise_products', $id, ['action' => 'Updated merchandise product', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'update_inventory':
            $productId = intval($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $sizes = $_POST['sizes'] ?? [];
            $quantities = $_POST['quantities'] ?? [];
            $sizeIds = $_POST['size_ids'] ?? [];
            
            $pdo->beginTransaction();
            
            // Delete sizes that are no longer present
            $validSizeIds = array_filter($sizeIds, function($id) { return !empty($id) && intval($id) > 0; });
            
            if (!empty($validSizeIds)) {
                $placeholders = implode(',', array_fill(0, count($validSizeIds), '?'));
                $deleteStmt = $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ? AND id NOT IN ($placeholders)");
                $params = array_merge([$productId], array_map('intval', $validSizeIds));
                $deleteStmt->execute($params);
            } else {
                // Delete all if no valid IDs remain
                $deleteStmt = $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ?");
                $deleteStmt->execute([$productId]);
            }
            
            // Insert or update sizes
            $insertStmt = $pdo->prepare("
                INSERT INTO merchandise_product_sizes (product_id, size, quantity)
                VALUES (?, ?, ?)
            ");
            $updateStmt = $pdo->prepare("
                UPDATE merchandise_product_sizes 
                SET size = ?, quantity = ?
                WHERE id = ? AND product_id = ?
            ");
            
            for ($i = 0; $i < count($sizes); $i++) {
                $size = trim($sizes[$i] ?? '');
                $quantity = intval($quantities[$i] ?? 0);
                $sizeId = isset($sizeIds[$i]) ? intval($sizeIds[$i]) : 0;
                
                if (empty($size)) {
                    continue;
                }
                
                if ($sizeId > 0) {
                    // Update existing size
                    $updateStmt->execute([$size, $quantity, $sizeId, $productId]);
                } else {
                    // Insert new size
                    $insertStmt->execute([$productId, $size, $quantity]);
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'merchandise_product_sizes', $productId, ['action' => 'Updated product inventory']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Inventory updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $pdo->beginTransaction();
            
            // Delete product sizes first (they have foreign key cascade but let's be explicit)
            $deletesSizes = $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ?");
            $deletesSizes->execute([$id]);
            
            // Delete product images
            $deleteImages = $pdo->prepare("DELETE FROM merchandise_product_images WHERE product_id = ?");
            $deleteImages->execute([$id]);
            
            // Delete the product
            $stmt = $pdo->prepare("DELETE FROM merchandise_products WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'DELETE', 'merchandise_products', $id, ['action' => 'Deleted merchandise product']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'toggle_status':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM merchandise_products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            $newStatus = $product['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE merchandise_products SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            Auditor::log($pdo, $user_id, 'UPDATE', 'merchandise_products', $id, ['action' => 'Toggled product status', 'new_status' => $newStatus]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product status updated!', 'new_status' => $newStatus]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'record_shipment':
            $productId = intval($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $sizeIds = $_POST['size_ids'] ?? [];
            $shipmentQuantities = $_POST['shipment_quantities'] ?? [];
            $reference = trim($_POST['reference'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($sizeIds)) {
                throw new Exception('No sizes provided for shipment');
            }
            
            $pdo->beginTransaction();
            
            $updateStmt = $pdo->prepare("UPDATE merchandise_product_sizes SET quantity = quantity + ? WHERE id = ? AND product_id = ?");
            $getQtyStmt = $pdo->prepare("SELECT quantity FROM merchandise_product_sizes WHERE id = ? AND product_id = ?");
            $movementStmt = $pdo->prepare("
                INSERT INTO merchandise_stock_movements (product_id, size_id, movement_type, quantity_before, quantity_change, quantity_after, reference, notes, created_by)
                VALUES (?, ?, 'shipment', ?, ?, ?, ?, ?, ?)
            ");
            
            $totalAdded = 0;
            for ($i = 0; $i < count($sizeIds); $i++) {
                $sizeId = intval($sizeIds[$i]);
                $qty = intval($shipmentQuantities[$i] ?? 0);
                
                if ($sizeId <= 0 || $qty <= 0) {
                    continue;
                }
                
                // Get current quantity before update
                $getQtyStmt->execute([$sizeId, $productId]);
                $currentQty = intval($getQtyStmt->fetchColumn());
                
                // Update the stock level
                $updateStmt->execute([$qty, $sizeId, $productId]);
                
                // Record the movement
                $movementStmt->execute([
                    $productId,
                    $sizeId,
                    $currentQty,
                    $qty,
                    $currentQty + $qty,
                    $reference ?: null,
                    $notes ?: null,
                    $_SESSION['user_id']
                ]);
                
                $totalAdded += $qty;
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'CREATE', 'merchandise_stock_movements', $productId, ['action' => 'Recorded product shipment', 'total_added' => $totalAdded]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Shipment recorded! $totalAdded items added to stock."]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        case 'stock_audit':
            $productId = intval($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $sizeIds = $_POST['size_ids'] ?? [];
            $actualQuantities = $_POST['actual_quantities'] ?? [];
            $notes = trim($_POST['audit_notes'] ?? '');
            
            if (empty($sizeIds)) {
                throw new Exception('No sizes provided for audit');
            }
            
            $pdo->beginTransaction();
            
            // Create the audit record
            $auditStmt = $pdo->prepare("
                INSERT INTO merchandise_stock_audits (product_id, status, notes, created_by)
                VALUES (?, 'completed', ?, ?)
            ");
            $auditStmt->execute([$productId, $notes ?: null, $_SESSION['user_id']]);
            $auditId = $pdo->lastInsertId();
            
            $getQtyStmt = $pdo->prepare("SELECT quantity FROM merchandise_product_sizes WHERE id = ? AND product_id = ?");
            $auditItemStmt = $pdo->prepare("
                INSERT INTO merchandise_stock_audit_items (audit_id, size_id, system_quantity, actual_quantity, discrepancy)
                VALUES (?, ?, ?, ?, ?)
            ");
            $updateStmt = $pdo->prepare("UPDATE merchandise_product_sizes SET quantity = ? WHERE id = ? AND product_id = ?");
            $movementStmt = $pdo->prepare("
                INSERT INTO merchandise_stock_movements (product_id, size_id, movement_type, quantity_before, quantity_change, quantity_after, reference, notes, created_by)
                VALUES (?, ?, 'audit_adjustment', ?, ?, ?, ?, ?, ?)
            ");
            
            $discrepancies = 0;
            for ($i = 0; $i < count($sizeIds); $i++) {
                $sizeId = intval($sizeIds[$i]);
                $actualQty = intval($actualQuantities[$i] ?? 0);
                
                if ($sizeId <= 0) {
                    continue;
                }
                
                // Get current system quantity
                $getQtyStmt->execute([$sizeId, $productId]);
                $systemQty = intval($getQtyStmt->fetchColumn());
                
                $discrepancy = $actualQty - $systemQty;
                
                // Record audit item
                $auditItemStmt->execute([$auditId, $sizeId, $systemQty, $actualQty, $discrepancy]);
                
                // If there's a discrepancy, adjust the stock and record movement
                if ($discrepancy !== 0) {
                    $discrepancies++;
                    $updateStmt->execute([$actualQty, $sizeId, $productId]);
                    
                    $movementStmt->execute([
                        $productId,
                        $sizeId,
                        $systemQty,
                        $discrepancy,
                        $actualQty,
                        'Audit #' . $auditId,
                        $notes ?: null,
                        $_SESSION['user_id']
                    ]);
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'CREATE', 'merchandise_stock_audits', $auditId, ['action' => 'Completed stock audit', 'discrepancies' => $discrepancies]);
            
            $message = "Stock audit completed. ";
            if ($discrepancies > 0) {
                $message .= "$discrepancies size(s) had discrepancies and were adjusted.";
            } else {
                $message .= "All stock levels match - no discrepancies found.";
            }
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $message, 'audit_id' => $auditId, 'discrepancies' => $discrepancies]);
                exit();
            }
            header("Location: dashboard.php?page=merchandise_products&status=success");
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    ErrorLogger::error("Merchandise product processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=merchandise_products&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
