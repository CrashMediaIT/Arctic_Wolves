<?php
/**
 * Process Wishlist Actions
 * Handles CRUD and reorder operations for the admin business wishlist.
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/csrf_protection.php';

// Helper function to check if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

if (isAjaxRequest()) {
    header('Content-Type: application/json');
}

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_role = $_SESSION['user_role'] ?? 'athlete';
if ($user_role !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Admin access required']));
}

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

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_wishlist` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `link` VARCHAR(2048) DEFAULT NULL COMMENT 'Purchase URL or distributor info',
        `display_order` INT DEFAULT 0,
        `purchased` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_display_order` (`display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
        error_log('Wishlist table creation error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_item':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = $_POST['price'] !== '' ? floatval($_POST['price']) : null;
                $link = trim($_POST['link'] ?? '');

                if (empty($name)) {
                    sendResponse(false, 'Item name is required');
                }

                // Get next display_order
                $orderStmt = $pdo->query("SELECT COALESCE(MAX(display_order), -1) + 1 FROM admin_wishlist");
                $nextOrder = (int)$orderStmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO admin_wishlist (name, description, price, link, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $price, $link ?: null, $nextOrder]);

                $newId = $pdo->lastInsertId();
                sendResponse(true, 'Item added to wishlist', ['id' => $newId]);
                break;

            case 'update_item':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = $_POST['price'] !== '' ? floatval($_POST['price']) : null;
                $link = trim($_POST['link'] ?? '');

                if (!$id || empty($name)) {
                    sendResponse(false, 'Item ID and name are required');
                }

                $stmt = $pdo->prepare("UPDATE admin_wishlist SET name = ?, description = ?, price = ?, link = ? WHERE id = ?");
                $stmt->execute([$name, $description ?: null, $price, $link ?: null, $id]);
                sendResponse(true, 'Item updated');
                break;

            case 'delete_item':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) {
                    sendResponse(false, 'Item ID is required');
                }
                $stmt = $pdo->prepare("DELETE FROM admin_wishlist WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(true, 'Item deleted');
                break;

            case 'toggle_purchased':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) {
                    sendResponse(false, 'Item ID is required');
                }
                $stmt = $pdo->prepare("UPDATE admin_wishlist SET purchased = NOT purchased WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(true, 'Status updated');
                break;

            case 'reorder_items':
                $orderData = json_decode($_POST['order'] ?? '[]', true);
                if (!is_array($orderData)) {
                    sendResponse(false, 'Invalid order data');
                }

                $stmt = $pdo->prepare("UPDATE admin_wishlist SET display_order = ? WHERE id = ?");
                foreach ($orderData as $item) {
                    if (!isset($item['id']) || !isset($item['display_order'])) {
                        sendResponse(false, 'Invalid order item structure');
                    }
                    $stmt->execute([intval($item['display_order']), intval($item['id'])]);
                }
                sendResponse(true, 'Order updated');
                break;

            default:
                sendResponse(false, 'Unknown action: ' . htmlspecialchars($action));
        }
    } catch (PDOException $e) {
        logError('Wishlist error: ' . $e->getMessage());
        sendResponse(false, 'Database error occurred');
    }
} else {
    sendResponse(false, 'Invalid request method');
}
