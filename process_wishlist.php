<?php
/**
 * Process Wishlist Actions
 * Handles CRUD and reorder for admin wishlist items
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Helper function to check if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Set JSON content type for AJAX requests early
if (isAjaxRequest()) {
    header('Content-Type: application/json');
}

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'athlete';

// Only admins can manage wishlist
if ($user_role !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Admin access required']));
}

// Ensure wishlist_items table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wishlist_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `link` VARCHAR(2048) DEFAULT NULL COMMENT 'URL to purchase or distributor',
        `display_order` INT DEFAULT 0,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_display_order` (`display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table may already exist; log other errors
    if (strpos($e->getMessage(), 'already exists') === false) {
        error_log("Wishlist table creation error: " . $e->getMessage());
    }
}

// Helper function to send response (either redirect or JSON)
function sendResponse($success, $message, $data = []) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    } else {
        $status = $success ? 'success' : 'error';
        header("Location: dashboard.php?page=admin_wishlist&status={$status}&message=" . urlencode($message));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = isset($_POST['price']) && $_POST['price'] !== '' ? floatval($_POST['price']) : null;
                $link = trim($_POST['link'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Item name is required');
                }
                
                // Get next display_order
                $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), -1) + 1 FROM wishlist_items");
                $nextOrder = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    INSERT INTO wishlist_items (name, description, price, link, display_order, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description ?: null, $price, $link ?: null, $nextOrder, $user_id]);
                $item_id = $pdo->lastInsertId();
                
                Auditor::log($pdo, $user_id, 'create', 'wishlist_items', $item_id, ['action' => 'Created wishlist item', 'name' => $name]);
                
                sendResponse(true, 'Item added to wishlist', ['item_id' => $item_id]);
                break;
                
            case 'update':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = isset($_POST['price']) && $_POST['price'] !== '' ? floatval($_POST['price']) : null;
                $link = trim($_POST['link'] ?? '');
                
                if ($id <= 0) {
                    throw new Exception('Invalid item ID');
                }
                if (empty($name)) {
                    throw new Exception('Item name is required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE wishlist_items 
                    SET name = ?, description = ?, price = ?, link = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description ?: null, $price, $link ?: null, $id]);
                
                Auditor::log($pdo, $user_id, 'update', 'wishlist_items', $id, ['action' => 'Updated wishlist item', 'name' => $name]);
                
                sendResponse(true, 'Item updated successfully');
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid item ID');
                }
                
                // Get item name for audit log
                $stmt = $pdo->prepare("SELECT name FROM wishlist_items WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ?");
                $stmt->execute([$id]);
                
                Auditor::log($pdo, $user_id, 'delete', 'wishlist_items', $id, ['action' => 'Deleted wishlist item', 'name' => $item['name'] ?? '']);
                
                sendResponse(true, 'Item deleted from wishlist');
                break;
                
            case 'reorder':
                $order = $_POST['order'] ?? '';
                $items = json_decode($order, true);
                
                if (!is_array($items)) {
                    throw new Exception('Invalid order data');
                }
                
                $stmt = $pdo->prepare("UPDATE wishlist_items SET display_order = ? WHERE id = ?");
                foreach ($items as $item) {
                    $stmt->execute([intval($item['display_order']), intval($item['id'])]);
                }
                
                sendResponse(true, 'Order updated successfully');
                break;
                
            default:
                throw new Exception('Unknown action: ' . htmlspecialchars($action));
        }
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
} else {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}
